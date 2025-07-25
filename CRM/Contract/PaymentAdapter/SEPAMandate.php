<?php

use Civi\Api4;
use Civi\Api4\ContributionRecur;

class CRM_Contract_PaymentAdapter_SEPAMandate implements CRM_Contract_PaymentAdapter {

    const ADAPTER_ID = "sepa_mandate";
    const DISPLAY_NAME = "SEPA mandate";

    private static $organisation_ibans = [];

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
     * Get metadata about the payment adapter
     *
     * @see CRM_Contract_PaymentAdapter::adapterInfo
     */
    public static function adapterInfo () {
        return [
            "display_name" => self::DISPLAY_NAME,
            "id"           => self::ADAPTER_ID,
        ];
    }

    /**
     * Create a new payment
     */
    public static function create ($params) {
        $create_result = civicrm_api3("SepaMandate", "createfull", $params);

        if ($create_result["is_error"]) {
            $error_message = $create_result["error_message"];
            throw new Exception("SEPA mandate could not be created: $error_message");
        }

        $mandate_id = (string) $create_result["id"];
        $mandate_data = $create_result["values"][$mandate_id];
        $mandate_url = CRM_Utils_System::url("civicrm/sepa/xmandate", "mid=$mandate_id");
        $mandate_reference = $mandate_data["reference"];

        $requested_start_date = CRM_Utils_Array::value("start_date", $params, 'now');
        $minimum_change_date = CRM_Contract_DateHelper::minimumChangeDate($requested_start_date, TRUE);

        $start_date = self::startDate([
            "cycle_day" => CRM_Utils_Array::value("cycle_day", $params),
            "min_date"  => $minimum_change_date->format('Y-m-d'),
        ]);

        civicrm_api3("ContributionRecur", "create", [
            "cycle_day"  => $start_date->format("d"),
            "id"         => $mandate_data["entity_id"],
            "start_date" => $start_date->format("Y-m-d"),
        ]);

        CRM_Core_Session::setStatus(
            "New SEPA Mandate <a href=\"$mandate_url\">$mandate_reference</a> created.",
            "Success",
            "info"
        );

        return $mandate_data["entity_id"];
    }

    /**
     * Create a new payment by merging an existing payment and an update,
     * The existing payment will be terminated.
     *
     * @param int $recurring_contribution_id
     * @param string $current_adapter
     * @param array $update
     * @param int $activity_type_id
     *
     * @throws Exception
     *
     * @return int - ID of the newly created recurring contribution
     */
    public static function createFromUpdate ($recurring_contribution_id, $current_adapter, $update, $activity_type_id = null) {
        $current_rc_data = civicrm_api3("ContributionRecur", "getsingle", [
            "id" => $recurring_contribution_id,
        ]);

        // Get the current campaign ID
        $current_campaign_id = CRM_Utils_Array::value("campaign_id", $current_rc_data);

        // Get the creditor & currency
        $creditor = CRM_Sepa_Logic_Settings::defaultCreditor();

        $currency = civicrm_api3("SepaCreditor", "getvalue", [
            "return" => "currency",
            "id"     => $creditor->id,
        ]);

        // Calculate the new start date
        $cycle_day = CRM_Utils_Array::value("cycle_day", $update, $current_rc_data["cycle_day"]);
        $defer_payment_start = CRM_Utils_Array::value("defer_payment_start", $update, TRUE);
        $min_date = CRM_Utils_Array::value("start_date", $update, $current_rc_data["start_date"]);

        $new_start_date = self::startDate([
            "cycle_day"           => $cycle_day,
            "defer_payment_start" => $defer_payment_start,
            "membership_id"       => $params["membership_id"],
            "min_date"            => $min_date,
        ]);

        // Get bank account by ID
        $bank_account_id = CRM_Utils_Array::value("from_ba", $update);
        $bank_account = CRM_Contract_BankingLogic::getBankAccount($bank_account_id);

        $current_adapter_class = CRM_Contract_Utils::getPaymentAdapterClass($current_adapter);
        $current_adapter_class::terminate($recurring_contribution_id);

        $create_params = [
            "amount"                => CRM_Utils_Array::value("amount", $update, $current_rc_data["amount"]),
            "bic"                   => CRM_Utils_Array::value("bic", $bank_account),
            "campaign_id"           => !empty($update["campaign_id"]) ? $update["campaign_id"] : $current_campaign_id,
            "contact_id"            => $current_rc_data["contact_id"],
            "creation_date"         => date("Y-m-d H:i:s"),
            "creditor_id"           => $creditor->id,
            "currency"              => $currency,
            "cycle_day"             => $cycle_day,
            "financial_type_id"     => $current_rc_data["financial_type_id"],
            "frequency_interval"    => CRM_Utils_Array::value("frequency_interval", $update, $current_rc_data["frequency_interval"]),
            "frequency_unit"        => CRM_Utils_Array::value("frequency_unit", $update, $current_rc_data["frequency_unit"]),
            "iban"                  => CRM_Utils_Array::value("iban", $bank_account),
            "payment_instrument_id" => CRM_Utils_Array::value("payment_instrument", $update),
            "start_date"            => $new_start_date->format("Y-m-d"),
            "type"                  => "RCUR",
            "validation_date"       => date("Y-m-d H:i:s"),
        ];

        return self::create($create_params);
    }

    /**
     * Get a list of possible cycle days
     *
     * @param array $params - not used
     *
     * @return array - list of cycle days as integers
     */
    public static function cycleDays ($params = []) {
        $creditor = CRM_Sepa_Logic_Settings::defaultCreditor();

        $cycle_days_setting = CRM_Sepa_Logic_Settings::getListSetting(
            'cycledays',
            range(1, 28),
            $creditor->id
        );

        return array_map(fn($n) => (int) $n, array_values($cycle_days_setting));
    }

    /**
     * Get payment specific form field specifications
     *
     * @param array $params - Optional parameters, depending on the implementation
     *
     * @return array - List of form field specifications
     */
    public static function formFields ($params = []) {
        $defaults = [];
        $recurring_contribution_id = CRM_Utils_Array::value('recurring_contribution_id', $params, NULL);

        if (isset($recurring_contribution_id) && self::isInstance($recurring_contribution_id)) {
            $mandate_data = civicrm_api3("SepaMandate", "getsingle", [
                "entity_table" => "civicrm_contribution_recur",
                "entity_id"    => $recurring_contribution_id,
            ]);

            $defaults["iban"] = $mandate_data["iban"];
            $defaults["bic"] = $mandate_data["bic"];
        }

        return [
            "iban" => [
                "default"      => CRM_Utils_Array::value("iban", $defaults),
                "display_name" => "IBAN",
                "enabled"      => true,
                "name"         => "iban",
                "required"     => true,
                "settings"     => [ "class" => "huge" ],
                "type"         => "text",
                "validate"     => "CRM_Contract_PaymentAdapter_SEPAMandate::validateIBAN",
            ],
            "bic" => [
                "default"      => CRM_Utils_Array::value("bic", $defaults),
                "display_name" => "BIC",
                "enabled"      => CRM_Contract_Utils::isDefaultCreditorUsesBic(),
                "name"         => "bic",
                "required"     => CRM_Contract_Utils::isDefaultCreditorUsesBic(),
                "type"         => "text",
                "validate"     => "CRM_Contract_PaymentAdapter_SEPAMandate::validateBIC",
            ],
        ];
    }

    /**
     * Get payment specific JS variables for forms
     *
     * @param array $params - Optional parameters, depending on the implementation
     *
     * @return array - Form variables
     */
    public static function formVars($params = []) {
        $bank_holidays = (array) Api4\SepaBankHoliday::get(FALSE)
            ->addSelect('bank_holiday_date')
            ->addOrderBy('bank_holiday_date', 'ASC')
            ->setLimit(25)
            ->execute();

        return [
            "bank_holidays"       => array_map(fn ($item) => $item["bank_holiday_date"], $bank_holidays),
            "creditor"            => CRM_Sepa_Logic_Settings::defaultCreditor(),
            "cycle_days"          => self::cycleDays(),
            "minimum_change_date" => CRM_Contract_DateHelper::minimumChangeDate()->format('Y-m-d'),
            "notice_days"         => self::noticeDays()->d,
            "payment_frequencies" => CRM_Contract_RecurringContribution::getPaymentFrequencies([1, 2, 4, 12]),
        ];
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

    public static function isInstance($recurringContributionID) {
      $sepaMandateResult = Api4\SepaMandate::get(FALSE)
        ->selectRowCount()
        ->addSelect('creditor_id.creditor_type')
        ->addWhere('entity_table', '=', 'civicrm_contribution_recur')
        ->addWhere('entity_id',    '=', $recurringContributionID)
        ->setLimit(1)
        ->execute();

      if ($sepaMandateResult->rowCount < 1) return FALSE;

      $creditorType = $sepaMandateResult->first()['creditor_id.creditor_type'];

      return $creditorType === 'SEPA';
    }

    /**
     * Map submitted form values to paramters for a specific API call
     *
     * @param string $apiEndpoint
     * @param array $submitted
     *
     * @throws Exception
     *
     * @return array - API parameters
     */
    public static function mapSubmittedFormValues ($apiEndpoint, $submitted) {
        switch ($apiEndpoint) {
            case "Contract.create": {
                $now = date("Y-m-d H:i:s");

                $start_date = CRM_Utils_Date::processDate(
                    $submitted["start_date"],
                    null,
                    null,
                    "Y-m-d H:i:s"
                );

                $result = [
                    "payment_method.amount"             => CRM_Contract_Utils::formatMoney($submitted["amount"]),
                    "payment_method.campaign_id"        => $submitted["campaign_id"],
                    "payment_method.creation_date"      => $now,
                    "payment_method.currency"           => CRM_Sepa_Logic_Settings::defaultCreditor()->currency,
                    "payment_method.cycle_day"          => $submitted["cycle_day"],
                    "payment_method.date"               => $start_date,
                    "payment_method.financial_type_id"  => 2, // = Member dues
                    "payment_method.frequency_interval" => 12 / (int) $submitted["frequency"],
                    "payment_method.frequency_unit"     => "month",
                    "payment_method.iban"               => $submitted["pa-sepa_mandate-iban"],
                    "payment_method.start_date"         => $start_date,
                    "payment_method.type"               => "RCUR",
                    "payment_method.validation_date"    => $now,
                ];

                if (CRM_Contract_Utils::isDefaultCreditorUsesBic()) {
                    $result["payment_method.bic"] = $submitted["pa-sepa_mandate-bic"];
                }

                return $result;
            }

            case "Contract.modify": {
                // Frequency
                $frequency = (int) $submitted["frequency"];

                // Annual amount
                $amount = (float) CRM_Contract_Utils::formatMoney($submitted["amount"]);
                $annual_amount = $frequency * $amount;

                // Bank account
                $bank_account_id = CRM_Contract_BankingLogic::getOrCreateBankAccount(
                    $submitted["contact_id"],
                    $submitted["pa-sepa_mandate-iban"]
                );

                $result = [
                    "membership_payment.cycle_day"            => $submitted["cycle_day"],
                    "membership_payment.from_ba"              => $bank_account_id,
                    "membership_payment.membership_annual"    => $annual_amount,
                    "membership_payment.membership_frequency" => $frequency,
                ];

                return $result;
            }

            default: {
                return [];
            }
        }
    }

    public static function noticeDays() {
        $creditor = CRM_Sepa_Logic_Settings::defaultCreditor();

        $notice_setting = CRM_Sepa_Logic_Settings::getSetting(
            "batching.RCUR.notice",
            $creditor->id
        );

        return new DateInterval("P{$notice_setting}D");
    }

    /**
     * Pause payment
     *
     * @param int $recurring_contribution_id
     *
     * @throws Exception
     *
     * @return void
     */
    public static function pause ($recurring_contribution_id) {
        $mandate = civicrm_api3("SepaMandate", "getsingle", [
            "entity_id"    => $recurring_contribution_id,
            "entity_table" => "civicrm_contribution_recur",
        ]);

        if (!in_array($mandate["status"], [ "RCUR", "FRST" ])) {
            throw new Exception("SEPA mandate cannot be paused: Mandate is not active");
        }

        $update_result = civicrm_api3("SepaMandate", "create", [
            "id"     => $mandate["id"],
            "status" => "ONHOLD",
        ]);

        if ($update_result["is_error"]) {
            $error_message = $update_result["error_message"];
            throw new Exception("SEPA mandate cannot be paused: $error_message");
        }

        ContributionRecur::update(FALSE)
            ->addWhere('id', '=', $recurring_contribution_id)
            ->addValue('contribution_status_id:name', 'Paused')
            ->execute();
    }

    /**
     * Resume paused payment
     *
     * @param int $recurring_contribution_id
     * @param array $update
     *
     * @throws Exception
     *
     * @return void
     */
    public static function resume ($recurring_contribution_id, $update = []) {
        $mandate = civicrm_api3("SepaMandate", "getsingle", [
            "entity_id"    => $recurring_contribution_id,
            "entity_table" => "civicrm_contribution_recur",
        ]);

        if ($mandate["status"] !== "ONHOLD") {
            throw new Exception("SEPA mandate cannot be resumed: Mandate is not paused");
        }

        $new_status = isset($mandate["first_contribution_id"]) ? "RCUR" : "FRST";

        if (count(array_diff_key($update, [ 'membership_id' => 1 ])) > 0) {
            $update_params = array_merge($update, [ "status" => $new_status ]);
            return self::update($recurring_contribution_id, $update_params);
        }

        $update_result = civicrm_api3("SepaMandate", "create", [
            "id"     => $mandate["id"],
            "status" => $new_status,
        ]);

        if ($update_result["is_error"]) {
            $error_message = $update_result["error_message"];
            throw new Exception("SEPA mandate cannot be resumed: $error_message");
        }

        ContributionRecur::update(FALSE)
            ->addWhere('id', '=', $recurring_contribution_id)
            ->addValue('contribution_status_id:name', 'Pending')
            ->execute();

        return $recurring_contribution_id;
    }

    /**
     * Revive a cancelled payment
     *
     * @param int $recurring_contribution_id
     * @param array $update
     *
     * @throws Exception
     *
     * @return int - Recurring contribution ID
     */
    public static function revive ($recurring_contribution_id, $update = []) {
        $revive_activity_type  = CRM_Core_PseudoConstant::getKey(
            "CRM_Activity_BAO_Activity",
            "activity_type_id",
            "Contract_Revived"
        );

        return self::update($recurring_contribution_id, $update, $revive_activity_type);
    }

    public static function startDate($params = [], $today = 'now') {
        $today = new DateTimeImmutable($today);
        $start_date = DateTime::createFromImmutable($today);

        // Notice days

        $start_date->add(self::noticeDays());

        // Minimum date

        if (isset($params['min_date'])) {
            $min_date = new DateTimeImmutable($params['min_date']);
        }

        if (isset($min_date) && $start_date->getTimestamp() < $min_date->getTimestamp()) {
            $start_date = DateTime::createFromImmutable($min_date);
        }

        // Existing contract

        if (isset($params['membership_id'])) {
            $membership = CRM_Contract_Utils::getMembershipByID($params['membership_id']);
            $membership_start_date = new DateTimeImmutable($membership['start_date']);

            if ($start_date->getTimestamp() < $membership_start_date->getTimestamp()) {
                $start_date = DateTime::createFromImmutable($membership_start_date);
            }

            $recurring_contribution = CRM_Contract_RecurringContribution::getCurrentForContract(
                $membership['id']
            );
        }

        // Defer payment start

        $defer_payment_start = CRM_Utils_Array::value('defer_payment_start', $params, FALSE);

        if ($defer_payment_start && isset($params['membership_id'])) {
            $latest_contribution = CRM_Contract_RecurringContribution::getLatestContribution(
                $params['membership_id']
            );
        }

        if (isset($latest_contribution)) {
            $latest_contribution_rc = CRM_Contract_RecurringContribution::getById(
                $latest_contribution['contribution_recur_id']
            );

            $last_regular_date = CRM_Contract_DateHelper::findLastOfDays(
                [(int) $latest_contribution_rc['cycle_day']],
                $latest_contribution['receive_date']
            );

            $paid_until = CRM_Contract_DateHelper::nextRegularDate(
                $last_regular_date->format('Y-m-d'),
                $latest_contribution_rc['frequency_interval'],
                $latest_contribution_rc['frequency_unit']
            );

            if ($start_date->getTimestamp() < $paid_until->getTimestamp()) {
                $start_date = $paid_until;
            }
        }

        // Allowed cycle days

        $allowed_cycle_days = self::cycleDays();

        $start_date = CRM_Contract_DateHelper::findNextOfDays(
            $allowed_cycle_days,
            $start_date->format('Y-m-d')
        );

        if (empty($params['cycle_day']) && isset($recurring_contribution)) {
            $params['cycle_day'] = $recurring_contribution['cycle_day'];
        }

        $cycle_day = (int) (
            isset($params['cycle_day'])
            ? $params['cycle_day']
            : $start_date->format('d')
        );

        if (!in_array($cycle_day, $allowed_cycle_days, TRUE)) {
            throw new Exception("Cycle day $cycle_day is not allowed for this SEPA creditor");
        }

        // Find next date for expected cycle day

        $start_date = CRM_Contract_DateHelper::findNextOfDays(
            [$cycle_day],
            $start_date->format('Y-m-d')
        );

        return $start_date;
    }

    /**
     * Terminate payment
     *
     * @param int $recurring_contribution_id
     * @param string $reason
     *
     * @throws Exception
     *
     * @return void
     */
    public static function terminate ($recurring_contribution_id, $reason = "CHNG") {
        $mandate_id = civicrm_api3("SepaMandate", "getvalue", [
            "entity_id"    => $recurring_contribution_id,
            "entity_table" => "civicrm_contribution_recur",
            "return"       => "id",
        ]);

        $termination_date = date("Y-m-d H:i:s", strtotime("today"));

        CRM_Sepa_BAO_SEPAMandate::terminateMandate($mandate_id, $termination_date, $reason);

        // Set the cancel_reason explicitly again since
        // CRM_Sepa_BAO_SEPAMandate::terminateMandate seems to ignore this parameter,
        ContributionRecur::update(FALSE)
          ->addValue('next_sched_contribution_date', NULL)
          ->addValue('cancel_reason', $reason)
          ->addWhere('id', '=', $recurring_contribution_id)
          ->execute();
    }

    /**
     * Update payment data
     *
     * @param int $recurring_contribution_id
     * @param array $params
     * @param int $activity_type_id
     *
     * @throws Exception
     *
     * @return int - Recurring contribution ID
     */
    public static function update ($recurring_contribution_id, $params, $activity_type_id = null) {
        // Load current recurring contribution / SEPA mandate data
        $current_rc_data = civicrm_api3("ContributionRecur", "getsingle", [
            "id" => $recurring_contribution_id,
        ]);

        $current_campaign_id = CRM_Utils_Array::value("campaign_id", $current_rc_data);

        $mandates_result = civicrm_api3("SepaMandate", "get", [
            "entity_table" => "civicrm_contribution_recur",
            "entity_id"    => $recurring_contribution_id,
            "options"      => [ "sort" => "creation_date" ],
            "sequential"   => 1,
        ]);

        $current_mandate_data = end($mandates_result["values"]);

        // Calculate the new start date
        $cycle_day = CRM_Utils_Array::value("cycle_day", $params, $current_rc_data["cycle_day"]);
        $defer_payment_start = CRM_Utils_Array::value("defer_payment_start", $params, TRUE);
        $min_date = CRM_Utils_Array::value("start_date", $params);

        $new_start_date = self::startDate([
            "cycle_day"           => $cycle_day,
            "defer_payment_start" => $defer_payment_start,
            "membership_id"       => $params["membership_id"],
            "min_date"            => $min_date,
        ]);

        // Get the creditor & payment currency
        $creditor_id = CRM_Utils_Array::value("creditor_id", $params, $current_mandate_data["creditor_id"]);

        $creditor_currency = civicrm_api3("SepaCreditor", "getvalue", [
            "id"     => $creditor_id,
            "return" => "currency",
        ]);

        // Get bank account by ID
        $bank_account_id = CRM_Utils_Array::value("from_ba", $params);
        $bank_account = CRM_Contract_BankingLogic::getBankAccount($bank_account_id);
        $current_bic = CRM_Utils_Array::value("bic", $current_mandate_data);

        // Terminate the current mandate (if necessary)
        $revive_activity_type = CRM_Core_PseudoConstant::getKey(
            "CRM_Activity_BAO_Activity",
            "activity_type_id",
            "Contract_Revived"
        );

        if ($activity_type_id !== $revive_activity_type) {
            self::terminate($recurring_contribution_id, "CHNG");
        }

        // Create a new mandate
        $create_params = [
            "amount"             => CRM_Utils_Array::value("amount", $params, $current_rc_data["amount"]),
            "bic"                => CRM_Utils_Array::value("bic", $bank_account, $current_bic),
            "campaign_id"        => empty($params["campaign_id"]) ? $current_campaign_id : $params["campaign_id"],
            "contact_id"         => $current_rc_data["contact_id"],
            "creation_date"      => date("Y-m-d H:i:s"),
            "creditor_id"        => $creditor_id,
            "currency"           => CRM_Utils_Array::value("currency", $params, $creditor_currency),
            "cycle_day"          => $cycle_day,
            "financial_type_id"  => CRM_Utils_Array::value("financial_type_id", $params, $current_rc_data["financial_type_id"]),
            "frequency_interval" => CRM_Utils_Array::value("frequency_interval", $params, $current_rc_data["frequency_interval"]),
            "frequency_unit"     => CRM_Utils_Array::value("frequency_unit", $params, $current_rc_data["frequency_unit"]),
            "iban"               => CRM_Utils_Array::value("iban", $bank_account, $current_mandate_data["iban"]),
            "reference"          => CRM_Utils_Array::value("reference", $params),
            "start_date"         => $new_start_date->format("Y-m-d"),
            "type"               => "RCUR",
            "validation_date"    => date("Y-m-d H:i:s"),
        ];

        return self::create($create_params);
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
