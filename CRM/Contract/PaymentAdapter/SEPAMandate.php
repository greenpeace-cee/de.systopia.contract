<?php

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

        $current_annual = CRM_Contract_Utils::calcAnnualAmount(
            (float) $current_rc_data["amount"],
            (int) $current_rc_data["frequency_interval"],
            (string) $current_rc_data["frequency_unit"]
        );

        // Calculate the new contribution amount & frequency
        $new_recurring_amount = CRM_Contract_Utils::calcRecurringAmount(
            (float) CRM_Utils_Array::value("annual", $update, $current_annual["annual"]),
            (int) CRM_Utils_Array::value("frequency", $update, $current_annual["frequency"])
        );

        // Get the current campaign ID
        $current_campaign_id = CRM_Utils_Array::value("campaign_id", $current_rc_data);

        // Get the creditor & currency
        $creditor = CRM_Sepa_Logic_Settings::defaultCreditor();

        $currency = civicrm_api3("SepaCreditor", "getvalue", [
            "return" => "currency",
            "id"     => $creditor->id,
        ]);

        // Calculate the new start date
        $new_start_date = CRM_Contract_RecurringContribution::getUpdateStartDate(
            [ "membership_payment.membership_recurring_contribution" => $recurring_contribution_id ],
            [ "contract_updates.ch_defer_payment_start" => CRM_Utils_Array::value("defer_payment_start", $update, false) ],
            [ "activity_type_id" => $activity_type_id ],
            self::cycleDays()
        );

        // Get bank account by ID
        $bank_account_id = CRM_Utils_Array::value("from_ba", $update);
        $bank_account = CRM_Contract_BankingLogic::getBankAccount($bank_account_id);

        $current_adapter_class = CRM_Contract_Utils::getPaymentAdapterClass($current_adapter);
        $current_adapter_class::terminate($recurring_contribution_id);

        $create_params = [
            "amount"                => $new_recurring_amount["amount"],
            "bic"                   => CRM_Utils_Array::value("bic", $bank_account),
            "campaign_id"           => !empty($update["campaign_id"]) ? $update["campaign_id"] : $current_campaign_id,
            "contact_id"            => $current_rc_data["contact_id"],
            "creation_date"         => date("Y-m-d H:i:s"),
            "creditor_id"           => $creditor->id,
            "currency"              => $currency,
            "cycle_day"             => CRM_Utils_Array::value("cycle_day", $update, $current_rc_data["cycle_day"]),
            "financial_type_id"     => $current_rc_data["financial_type_id"],
            "frequency_interval"    => $new_recurring_amount["frequency_interval"],
            "frequency_unit"        => $new_recurring_amount["frequency_unit"],
            "iban"                  => CRM_Utils_Array::value("iban", $bank_account),
            "payment_instrument_id" => CRM_Utils_Array::value("payment_instrument", $update),
            "start_date"            => $new_start_date,
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
        return CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $creditor->id);
    }

    /**
     * Get payment specific form field specifications
     *
     * @param int|null $recurring_contribution_id
     *
     * @return array - List of form field specifications
     */
    public static function formFields ($recurring_contribution_id = null) {
        $defaults = [];
        $payment_adapter = CRM_Contract_Utils::getPaymentAdapterForRecurringContribution($recurring_contribution_id);

        if (isset($recurring_contribution_id) && $payment_adapter === self::ADAPTER_ID) {
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
    public static function formVars ($params = []) {
        $result = [];

        // Creditor
        $default_creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
        $result["creditor"] = $default_creditor;

        // Cycle days
        $result["cycle_days"] = self::cycleDays();

        // Default creditor grace
        $result["default_creditor_grace"] = (int) CRM_Sepa_Logic_Settings::getSetting(
            "batching.RCUR.grace",
            $default_creditor->creditor_id
        );

        // Default creditor notice
        $result["default_creditor_notice"] = (int) CRM_Sepa_Logic_Settings::getSetting(
            "batching.RCUR.notice",
            $default_creditor->creditor_id
        );

        // Default currency
        $result["default_currency"] = $default_creditor->currency;

        // Next cycle day
        $result["next_cycle_day"] = self::nextCycleDay();

        return $result;
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

    /**
     * Get the next possible cycle day
     *
     * @return int - the next cycle day
     */
    public static function nextCycleDay () {
        $creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
        $notice = (int) CRM_Sepa_Logic_Settings::getSetting("batching.FRST.notice", $creditor->id);
        $buffer_days = (int) CRM_Sepa_Logic_Settings::getSetting("pp_buffer_days") + $notice;
        $cycle_days = self::cycleDays();

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

        if (count($update) > 0) {
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
        return self::update($recurring_contribution_id, $update);
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
        civicrm_api3("ContributionRecur", "create", [
            "id"            => $recurring_contribution_id,
            "cancel_reason" => $reason,
        ]);
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

        // Calculate the current annual contribution amount & frequency
        $current_annual_amount = CRM_Contract_Utils::calcAnnualAmount(
            (float) $current_rc_data["amount"],
            (int) $current_rc_data["frequency_interval"],
            (string) $current_rc_data["frequency_unit"]
        );

        $current_rc_data["annual"] = $current_annual_amount["annual"];
        $current_rc_data["frequency"] = $current_annual_amount["frequency"];

        // Calculate the new contribution amount & frequency
        $new_recurring_amount = CRM_Contract_Utils::calcRecurringAmount(
            (float) CRM_Utils_Array::value("annual", $params, $current_rc_data["annual"]),
            (int) CRM_Utils_Array::value("frequency", $params, $current_rc_data["frequency"])
        );

        // Calculate the new start date
        $new_start_date = CRM_Contract_RecurringContribution::getUpdateStartDate(
            [ "membership_payment.membership_recurring_contribution" => $recurring_contribution_id ],
            [ "contract_updates.ch_defer_payment_start" => CRM_Utils_Array::value("defer_payment_start", $params, "1") ],
            [ "activity_type_id" => $activity_type_id ],
            self::cycleDays()
        );

        // Get bank account by ID
        $bank_account_id = CRM_Utils_Array::value("from_ba", $params);
        $bank_account = CRM_Contract_BankingLogic::getBankAccount($bank_account_id);
        $current_bic = CRM_Utils_Array::value("bic", $current_mandate_data);

        // Terminate the current mandate
        self::terminate($recurring_contribution_id, "CHNG");

        // Create a new mandate
        $create_params = [
            "amount"             => $new_recurring_amount["amount"],
            "bic"                => CRM_Utils_Array::value("bic", $bank_account, $current_bic),
            "campaign_id"        => !empty($params["campaign_id"]) ? $params["campaign_id"] : $current_campaign_id,
            "contact_id"         => $current_rc_data["contact_id"],
            "creation_date"      => date("Y-m-d H:i:s"),
            "creditor_id"        => CRM_Utils_Array::value("creditor_id", $params, $current_mandate_data["creditor_id"]),
            "currency"           => CRM_Utils_Array::value("currency", $params, $current_rc_data["currency"]),
            "cycle_day"          => CRM_Utils_Array::value("cycle_day", $params, $current_rc_data["cycle_day"]),
            "financial_type_id"  => CRM_Utils_Array::value("financial_type_id", $params, $current_rc_data["financial_type_id"]),
            "frequency_interval" => $new_recurring_amount["frequency_interval"],
            "frequency_unit"     => $new_recurring_amount["frequency_unit"],
            "iban"               => CRM_Utils_Array::value("iban", $bank_account, $current_mandate_data["iban"]),
            "reference"          => CRM_Utils_Array::value("reference", $params),
            "start_date"         => $new_start_date,
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
