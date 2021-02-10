<?php

class CRM_Contract_PaymentInstrument_SepaMandate implements CRM_Contract_PaymentInstrument {

    const DISPLAY_NAME = "SEPA mandate";

    private static $organisation_ibans = null;

    private $bic;
    private $contact_id;
    private $entity_id;
    private $first_contribution_id;
    private $iban;
    private $id;
    private $reference;
    private $status;
    private $type;
    private $validation_date;

    /**
     * Prepare and inject the SEPA tools JS
     *
     * @return void
     */
    public static function addJsSepaTools() {
        $creditor_parameters = civicrm_api3("SepaCreditor", "get", [
            "options.limit" => 0,
        ])["values"];

        foreach ($creditor_parameters as &$creditor) {
            $creditor["grace"] = (int) CRM_Sepa_Logic_Settings::getSetting(
                "batching.RCUR.grace",
                $creditor["id"]
            );

            $creditor["notice"] = (int) CRM_Sepa_Logic_Settings::getSetting(
                "batching.RCUR.notice",
                $creditor["id"]
            );
        }

        $default_creditor_id = (int) CRM_Sepa_Logic_Settings::getSetting(
            "batching_default_creditor"
        );

        $creditor_parameters["default"] = $creditor_parameters[$default_creditor_id];

        $script = file_get_contents(__DIR__ . "/../../../js/sepa_tools.js");

        $script = str_replace(
            "SEPA_CREDITOR_PARAMETERS",
            json_encode($creditor_parameters),
            $script
        );

        CRM_Core_Region::instance("page-header")->add([ "script" => $script ]);
    }

    /**
     * Mark the new mandate as a replacement of the old mandate
     *
     * @param int $new_mandate_id - SEPA mandate id of new mandate
     * @param int $old_mandate_id - SEPA mandate id of old, terminated mandate
     * @param string $date        - Timestamp of change, default: "now"
     *
     * @return void
     */
    public static function addSepaMandateReplacedLink($new_mandate_id, $old_mandate_id, $date = "now") {
        if (method_exists("CRM_Sepa_BAO_SepaMandateLink", "addReplaceMandateLink")) {
            try {
                CRM_Sepa_BAO_SepaMandateLink::addReplaceMandateLink(
                    $new_mandate_id,
                    $old_mandate_id,
                    $date
                );
            } catch(Exception $ex) {
                CRM_Core_Error::debug_log_message(
                    "Contract: Couldn't create mandate replaced link: " . $ex->getMessage()
                );
            }
        } else {
            CRM_Core_Error::debug_log_message(
                "Contract: Couldn't create mandate replaced link, CiviSEPA version too old."
            );
        }
    }

    /**
     * @see CRM_Contract_PaymentInstrument::create
     */
    public static function create ($params) {
        $create_result = civicrm_api3("SepaMandate", "createfull", $params);

        $fetch_result = civicrm_api3("SepaMandate", "getsingle", [ "id" => $create_result["id"] ]);

        $instance = new self();
        $instance->setParameters($fetch_result);

        $mandate_url = CRM_Utils_System::url("civicrm/sepa/xmandate", "mid=$instance->id");

        CRM_Core_Session::setStatus(
            "New SEPA Mandate <a href=\"$mandate_url\">$instance->reference</a> created.",
            "Success",
            "info"
        );

        return $instance;
    }

    /**
     * @see CRM_Contract_PaymentInstrument::displayName
     */
    public static function displayName () {
        return self::DISPLAY_NAME;
    }

    /**
     * @see CRM_Contract_PaymentInstrument::formFields
     */
    public static function formFields () {
        return [
            "iban" => [
                "display_name" => "IBAN",
                "enabled"      => true,
                "name"         => "iban",
                "required"     => true,
                "settings"     => [ "class" => "huge" ],
                "type"         => "text",
                "validate"     => "CRM_Contract_PaymentInstrument_SepaMandate::validateIBAN",
            ],

            "bic" => [
                "display_name" => "BIC",
                "enabled"      => CRM_Contract_Utils::isDefaultCreditorUsesBic(),
                "name"         => "bic",
                "required"     => CRM_Contract_Utils::isDefaultCreditorUsesBic(),
                "type"         => "text",
                "validate"     => "CRM_Contract_PaymentInstrument_SepaMandate::validateBIC",
            ],
        ];
    }

    /**
     * @see CRM_Contract_PaymentInstrument::formVars
     */
    public static function formVars ($instance = null) {
        $default_creditor = CRM_Sepa_Logic_Settings::defaultCreditor();

        $default_creditor_grace = (int) CRM_Sepa_Logic_Settings::getSetting(
            "batching.RCUR.grace",
            $default_creditor->creditor_id
        );

        $default_creditor_notice = (int) CRM_Sepa_Logic_Settings::getSetting(
            "batching.RCUR.notice",
            $default_creditor->creditor_id
        );

        $current_iban = null;

        if (isset($instance)) {
            $current_iban = $instance->getParameters()["iban"];
        }

        return [
            "creditor"                => $default_creditor,
            "current_iban"            => $current_iban,
            "default_creditor_grace"  => $default_creditor_grace,
            "default_creditor_notice" => $default_creditor_notice,
        ];
    }

    /**
     * @see CRM_Contract_PaymentInstrument::getCycleDays
     */
    public static function getCycleDays () {
        $creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
        return CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $creditor->id);
    }

    /**
     * @see CRM_Contract_PaymentInstrument::getParameters
     */
    public function getParameters () {
        return [
            "bic"                   => $this->bic,
            "contact_id"            => $this->contact_id,
            "entity_id"             => $this->entity_id,
            "first_contribution_id" => $this->first_contribution_id,
            "iban"                  => $this->iban,
            "id"                    => $this->id,
            "reference"             => $this->reference,
            "status"                => $this->status,
            "type"                  => $this->type,
            "validation_date"       => $this->validation_date,
        ];
    }

    /**
     * @see CRM_Contract_PaymentInstrument::isInstance
     */
    public static function isInstance ($payment_instrument_id) {
        $accepted_pi_values= civicrm_api3("OptionValue", "get", [
            "option_group_id" => "payment_instrument",
            "name"            => [ "IN" => [ "FRST", "RCUR", "OOFF" ] ],
            "return"          => [ "value" ],
            "sequential"      => 1,
        ])["values"];

        foreach ($accepted_pi_values as $pi_value) {
            if ($pi_value["value"] === $payment_instrument_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IBAN is one of the organisation's own
     *
     * @param string $iban
     *
     * @return boolean
     */
    public static function isOrganisationIBAN($iban) {
        if (empty($iban)) return false;

        if (self::$organisation_ibans === null) {
            self::$organisation_ibans = [];

            $query = CRM_Core_DAO::executeQuery(
                "SELECT reference
                FROM civicrm_bank_account_reference
                LEFT JOIN civicrm_bank_account ON civicrm_bank_account_reference.ba_id = civicrm_bank_account.id
                LEFT JOIN civicrm_option_value ON civicrm_bank_account_reference.reference_type_id = civicrm_option_value.id
                WHERE civicrm_bank_account.contact_id = 1 AND civicrm_option_value.name = 'IBAN'"
            );

            while ($query->fetch()) {
                self::$organisation_ibans[] = $query->reference;
            }

            $query->free();
        }

        return in_array($iban, self::$organisation_ibans);
    }

    /**
     * @see CRM_Contract_PaymentInstrument::loadByRecurringContributionId
     */
    public static function loadByRecurringContributionId ($recurring_contribution_id) {
        if (empty($recurring_contribution_id)) {
            throw new Exception("SEPA mandate cannot be loaded: No recurring contribution ID");
        }

        $query_result = civicrm_api3("SepaMandate", "get", [
            "entity_id"    => $recurring_contribution_id,
            "entity_table" => "civicrm_contribution_recur",
            "type"         => "RCUR",
        ]);

        if ($query_result["count"] !== 1) return null;

        $params = reset($query_result["values"]);
        $instance = new self();
        $instance->setParameters($params);

        return $instance;
    }

    /**
     * @see CRM_Contract_PaymentInstrument::mapSubmittedValues
     */
    public static function mapSubmittedValues ($submitted) {
        $cycle_day = $submitted["pi-sepa_mandate-cycle_day"];

        if ($cycle_day < 1 || $cycle_day > 30) {
            $cycle_day = self::nextCycleDay();
        }

        $amount = (float) CRM_Contract_Utils::formatMoney($submitted["amount"]);
        $frequency = (int) $submitted["frequency"];
        $now = date("YmdHis");

        $start_date = CRM_Utils_Date::processDate(
            $submitted["start_date"],
            null,
            null,
            "Y-m-d H:i:s"
        );

        $new_mandate_params = [
            "amount"             => $amount,
            "campaign_id"        => $submitted["campaign_id"],
            "contact_id"         => $submitted["contact_id"],
            "creation_date"      => $now,
            "currency"           => CRM_Sepa_Logic_Settings::defaultCreditor()->currency,
            "cycle_day"          => $cycle_day,
            "date"               => $start_date,
            "financial_type_id"  => 2,
            "iban"               => $submitted["pi-sepa_mandate-iban"],
            "frequency_interval" => 12 / $frequency,
            "frequency_unit"     => "month",
            "start_date"         => $start_date,
            "type"               => "RCUR",
            "validation_date"    => $now,
        ];

        if (CRM_Contract_Utils::isDefaultCreditorUsesBic()) {
            $new_mandate_params["bic"] = $submitted["pi-sepa_mandate-bic"];
        }

        return $new_mandate_params;
}

    /**
     * @see CRM_Contract_PaymentInstrument::nextCycleDay
     */
    public static function nextCycleDay () {
        $creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
        $notice = (int) CRM_Sepa_Logic_Settings::getSetting("batching.FRST.notice", $creditor->id);
        $buffer_days = (int) CRM_Sepa_Logic_Settings::getSetting("pp_buffer_days") + $notice;
        $cycle_days = self::getCycleDays();

        $safety_counter = 32;
        $start_date = strtotime("+{$buffer_days} day", strtotime("now"));

        while (!in_array(date("d", $start_date), $cycle_days)) {
            $start_date = strtotime("+ 1 day", $start_date);
            $safety_counter -= 1;

            if ($safety_counter == 0) {
                throw new Exception("There's something wrong with the nextCycleDay method.");
            }
        }

        return (int) date("d", $start_date);
    }

    /**
     * @see CRM_Contract_PaymentInstrument::pause
     */
    public function pause () {
        if (empty($this->id)) {
            throw new Exception("SEPA mandate cannot be paused: No mandate ID");
        }

        if (empty($this->status)) {
            throw new Exception("SEPA mandate cannot be paused: No mandate status");
        }

        if (!in_array($this->status, [ "RCUR", "FRST" ])) {
            throw new Exception("SEPA mandate cannot be paused: Mandate is not active");
        }

        $update_result = civicrm_api3("SepaMandate", "create", [
            "id"     => $this->id,
            "status" => "ONHOLD",
        ]);

        if ($update_result["is_error"]) {
            $error_message = $update_result["error_message"];
            throw new Exception("SEPA mandate cannot be paused: $error_message");
        }
    }

    /**
     * @see CRM_Contract_PaymentInstrument::resume
     */
    public function resume () {
        if (empty($this->id)) {
            throw new Exception("SEPA mandate cannot be resumed: No mandate ID");
        }

        if (empty($this->status)) {
            throw new Exception("SEPA mandate cannot be resumed: No mandate status");
        }

        if ($this->status !== "ONHOLD") {
            throw new Exception("SEPA mandate cannot be resumed: Mandate is not paused");
        }

        $new_status = empty($this->first_contribution_id) ? "FRST" : "RCUR";

        $update_result = civicrm_api3("SepaMandate", "create", [
            "id"     => $this->id,
            "status" => $new_status,
        ]);

        if ($update_result["is_error"]) {
            $error_message = $update_result["error_message"];
            throw new Exception("SEPA mandate cannot be resumed: $error_message");
        }
    }

    /**
     * @see CRM_Contract_PaymentInstrument::setParameters
     */
    public function setParameters ($params) {
        foreach ($this->getParameters() as $name => $current_value) {
            $this->$name = isset($params[$name]) ? $params[$name] : $current_value;
        }
    }

    /**
     * @see CRM_Contract_PaymentInstrument::terminate
     */
    public function terminate ($reason = "CHNG") {
        if (empty($this->id)) {
            throw new Exception("SEPA mandate cannot be terminated: No mandate ID");
        }

        CRM_Sepa_BAO_SEPAMandate::terminateMandate($this->id, "today", $reason);
    }

    /**
     * Validate BIC
     *
     * @param mixed $value
     * @param boolean $required
     *
     * @throws Exception
     *
     * @return void
     */
    public static function validateBIC ($value, $required = false) {
        if (empty($value)) {
            if ($required) {
                throw new Exception(ts("%1 is a required field", [ 1 => "BIC" ]));
            } else return;
        }

        if (CRM_Sepa_Logic_Verification::verifyBIC($value) !== null) {
            throw new Exception("Please enter a valid BIC");
        }
    }

    /**
     * Validate IBAN
     *
     * @param mixed $value
     * @param boolean $required = false
     *
     * @throws Exception
     *
     * @return void
     */
    public static function validateIBAN ($value, $required = false) {
        if (empty($value)) {
            if ($required) {
                throw new Exception(ts("%1 is a required field", [ 1 => "IBAN" ]));
            } else return;
        }

        if (CRM_Sepa_Logic_Verification::verifyIBAN($value) !== null) {
            throw new Exception("Please enter a valid IBAN");
        }

        if (self::isOrganisationIBAN($value)) {
            throw new Exception("Do not use any of the organisation's own IBANs");
        }
    }

}

?>
