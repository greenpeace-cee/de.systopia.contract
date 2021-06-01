<?php

class CRM_Contract_PaymentAdapter_PSPSEPA implements CRM_Contract_PaymentAdapter {

    const ADAPTER_ID = "psp_sepa";
    const DISPLAY_NAME = "PSP";

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
        $params["bic"] = $params["account_name"];
        unset($params["account_name"]);
        $params["iban"] = $params["account_reference"];
        unset($params["account_reference"]);

        $create_result = civicrm_api3("SepaMandate", "createfull", $params);

        $mandate_id = (string) $create_result["id"];
        $mandate_data = $create_result["values"][$mandate_id];
        $mandate_url = CRM_Utils_System::url("civicrm/sepa/xmandate", "mid=$mandate_id");
        $mandate_reference = $mandate_data["reference"];

        CRM_Core_Session::setStatus(
            "New PSP SEPA Mandate <a href=\"$mandate_url\">$mandate_reference</a> created.",
            "Success",
            "info"
        );

        return [
            "recurring_contribution_id" => $mandate_data["entity_id"],
        ];
    }

    /**
     * Get a list of possible cycle days
     *
     * @param array $params - array containing a creditor_id
     *
     * @return array - list of cycle days as integers
     */
    public static function cycleDays ($params = []) {
        if (empty($params["creditor_id"])) return [];

        return CRM_Sepa_Logic_Settings::getListSetting(
            "cycledays",
            range(1, 28),
            $params["creditor_id"]
        );
    }

    /**
     * Get payment specific form field specifications
     *
     * @return array - List of form field specifications
     */
    public static function formFields () {
        $psp_creditors = civicrm_api3("SepaCreditor", "get", [
            "creditor_type" => "PSP",
            "sequential"    => 1,
            "return"        => ["id", "label", "sepa_file_format_id"],
        ])["values"];

        $creditor_options = [];

        foreach ($psp_creditors as $pc) {
            $pain_version = civicrm_api3("OptionValue", "getvalue", [
                "option_group_id" => "sepa_file_format",
                "value"           => 12,
                "return"          => "label",
            ]);

            $label = $pc["label"];
            $creditor_options[$pc["id"]] = "$label [$pain_version]";
        }

        $pi_option_values = civicrm_api3("OptionValue", "get", [
            "option_group_id" => "payment_instrument",
            "sequential"      => 1,
            "return"          => ["value", "label"],
        ])["values"];

        $payment_instrument_options = [];

        foreach ($pi_option_values as $pi) {
            $payment_instrument_options[$pi["value"]] = $pi["label"];
        }

        return [
            "creditor" => [
                "display_name" => "Creditor",
                "enabled"      => true,
                "name"         => "creditor",
                "options"      => $creditor_options,
                "required"     => true,
                "type"         => "select",
            ],
            "payment_instrument" => [
                "display_name" => "Payment instrument",
                "enabled"      => true,
                "name"         => "payment_instrument",
                "options"      => $payment_instrument_options,
                "required"     => true,
                "type"         => "select",
            ],
            "account_reference" => [
                "display_name" => "Account reference",
                "enabled"      => true,
                "name"         => "account_reference",
                "required"     => true,
                "settings"     => [ "class" => "huge" ],
                "type"         => "text",
            ],
            "account_name" => [
                "display_name" => "Account name",
                "enabled"      => true,
                "name"         => "account_name",
                "required"     => true,
                "type"         => "text",
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

        // Creditor-specific cycle days & payment instruments
        $psp_creditors = civicrm_api3("SepaCreditor", "get", [
            "creditor_type" => "PSP",
            "sequential"    => 1,
            "return"        => ["id", "label", "pi_rcur"],
        ])["values"];

        $cycle_days = [];
        $payment_instruments = [];

        foreach ($psp_creditors as $creditor) {
            $cycle_days[$creditor["id"]] = self::cycleDays([ "creditor_id" => $creditor["id"] ]);

            $pi_ids = explode(",", $creditor["pi_rcur"]);

            $pi_option_values = civicrm_api3("OptionValue", "get", [
                "option_group_id" => "payment_instrument",
                "value"           => [ "IN" => $pi_ids ],
                "sequential"      => 1,
                "return"          => ["value", "label"],
            ])["values"];

            $payment_instruments[$creditor["id"]] = [];

            foreach ($pi_option_values as $pi_opt) {
                $payment_instruments[$creditor["id"]][$pi_opt["value"]] = $pi_opt["label"];
            }
        }

        $result["cycle_days"] = $cycle_days;
        $result["payment_instruments"] = $payment_instruments;

        if (empty($params["recurring_contribution_id"])) return $result;

        // Current payment parameters
        $mandates_result = civicrm_api3("SepaMandate", "get", [
            "entity_table" => "civicrm_contribution_recur",
            "entity_id"    => $params["recurring_contribution_id"],
            "options"      => [ "sort" => "creation_date" ],
            "sequential"   => 1,
            "return"       => ["iban", "bic"],
        ]);

        $current_mandate_data = end($mandates_result["values"]);

        $result["current_account_reference"] = $current_mandate_data["iban"];
        $result["current_account_name"] = $current_mandate_data["bic"];

        return $result;
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

                $currency = civicrm_api3("SepaCreditor", "getvalue", [
                    "return" => "currency",
                    "id"     => $submitted["pa-psp_sepa-creditor"],
                ]);

                $start_date = CRM_Utils_Date::processDate(
                    $submitted["start_date"],
                    null,
                    null,
                    "Y-m-d H:i:s"
                );

                $result = [
                    "payment_method.account_name"          => $submitted["pa-psp_sepa-account_name"],
                    "payment_method.account_reference"     => $submitted["pa-psp_sepa-account_reference"],
                    "payment_method.amount"                => CRM_Contract_Utils::formatMoney($submitted["amount"]),
                    "payment_method.campaign_id"           => $submitted["campaign_id"],
                    "payment_method.creation_date"         => $now,
                    "payment_method.creditor_id"           => $submitted["pa-psp_sepa-creditor"],
                    "payment_method.currency"              => $currency,
                    "payment_method.cycle_day"             => $submitted["pa-psp_sepa-cycle_day"],
                    "payment_method.date"                  => $start_date,
                    "payment_method.financial_type_id"     => 2, // = Member dues
                    "payment_method.frequency_interval"    => 12 / (int) $submitted["frequency"],
                    "payment_method.frequency_unit"        => "month",
                    "payment_method.payment_instrument_id" => $submitted["pa-psp_sepa-payment_instrument"],
                    "payment_method.start_date"            => $start_date,
                    "payment_method.type"                  => "RCUR",
                    "payment_method.validation_date"       => $now,
                ];

                return $result;
            }

            case "Contract.modify": {
                // Frequency
                $frequency = (int) $submitted["frequency"];

                // Annual amount
                $amount = (float) CRM_Contract_Utils::formatMoney($submitted["amount"]);
                $annual_amount = $frequency * $amount;

                $result = [
                    "membership_payment.cycle_day"            => $submitted["pa-psp_sepa-cycle_day"],
                    "membership_payment.membership_annual"    => $annual_amount,
                    "membership_payment.membership_frequency" => $frequency,
                    "payment_method.account_name"             => $submitted["pa-psp_sepa-account_name"],
                    "payment_method.account_reference"        => $submitted["pa-psp_sepa-account_reference"],
                    "payment_method.payment_instrument"       => $submitted["pa-psp_sepa-payment_instrument"],
                ];

                return $result;
            }

            default: {
                return [];
            }
        }
    }

    /**
     * Map update parameters to payment adapter parameters
     *
     * @param array $update_params
     *
     * @return array - Payment adapter parameters
     */
    public static function mapUpdateParameters ($update_params) {
        $mapping = [
            "activity_type_id"                        => "activity_type_id",
            "contract_updates.ch_annual"              => "annual",
            "contract_updates.ch_cycle_day"           => "cycle_day",
            "contract_updates.ch_defer_payment_start" => "defer_payment_start",
            "contract_updates.ch_frequency"           => "frequency",
            "contract_updates.ch_payment_instrument"  => "payment_instrument",
            "payment_method.account_name"             => "account_name",
            "payment_method.account_reference"        => "account_reference",
            "payment_method.creditor_id"              => "creditor_id",
            "payment_method.currency"                 => "currency",
            "payment_method.financial_type_id"        => "financial_type_id",
        ];

        $result = [];

        foreach ($mapping as $update_key => $result_key) {
            if (isset($update_params[$update_key])) {
                $result[$result_key] = $update_params[$update_key];
            }
        }

        return $result;
    }

    /**
     * Get the next possible cycle day
     *
     * @return int - the next cycle day
     */
    public static function nextCycleDay () {
        return date("d");
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
            throw new Exception("PSP SEPA mandate cannot be paused: Mandate is not active");
        }

        $update_result = civicrm_api3("SepaMandate", "create", [
            "id"     => $mandate["id"],
            "status" => "ONHOLD",
        ]);

        if ($update_result["is_error"]) {
            $error_message = $update_result["error_message"];
            throw new Exception("PSP SEPA mandate cannot be paused: $error_message");
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
            throw new Exception("PSP SEPA mandate cannot be resumed: Mandate is not paused");
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
            throw new Exception("PSP SEPA mandate cannot be resumed: $error_message");
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
    public static function terminate ($recurring_contribution_id, $reason) {
        $mandate_id = civicrm_api3("SepaMandate", "getvalue", [
            "entity_id"    => $recurring_contribution_id,
            "entity_table" => "civicrm_contribution_recur",
            "return"       => "id",
        ]);

        $termination_date = date("Y-m-d H:i:s", strtotime("today"));

        CRM_Sepa_BAO_SEPAMandate::terminateMandate($mandate_id, $termination_date, $reason);
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
            (int) CRM_Utils_Array::value("frequency", $params, $current_rc_data["frequency"]),
        );

        // Calculate the new start date
        $new_start_date = CRM_Contract_RecurringContribution::getUpdateStartDate(
            [ "membership_payment.membership_recurring_contribution" => $recurring_contribution_id ],
            [ "contract_updates.ch_defer_payment_start" => CRM_Utils_Array::value("defer_payment_start", $params, false) ],
            [ "activity_type_id" => $activity_type_id ],
            self::cycleDays(),
        );

        // Terminate the current mandate
        self::terminate($recurring_contribution_id, "CHNG");

        // Create a new mandate
        $create_params = [
            "account_name"          => CRM_Utils_Array::value("account_name", $params, $current_mandate_data["bic"]),
            "account_reference"     => CRM_Utils_Array::value("account_reference", $params, $current_mandate_data["iban"]),
            "amount"                => $new_recurring_amount["amount"],
            "campaign_id"           => CRM_Utils_Array::value("campaign_id", $params, $current_rc_data["campaign_id"]),
            "contact_id"            => $current_rc_data["contact_id"],
            "creation_date"         => date("Y-m-d H:i:s"),
            "creditor_id"           => CRM_Utils_Array::value("creditor_id", $params, $current_mandate_data["creditor_id"]),
            "currency"              => CRM_Utils_Array::value("currency", $params, $current_rc_data["currency"]),
            "cycle_day"             => CRM_Utils_Array::value("cycle_day", $params, $current_rc_data["cycle_day"]),
            "financial_type_id"     => CRM_Utils_Array::value("financial_type_id", $params, $current_rc_data["financial_type_id"]),
            "frequency_interval"    => $new_recurring_amount["frequency_interval"],
            "frequency_unit"        => $new_recurring_amount["frequency_unit"],
            "payment_instrument_id" => CRM_Utils_Array::value("payment_instrument", $params, $current_rc_data["payment_instrument_id"]),
            "start_date"            => $new_start_date,
            "type"                  => "RCUR",
            "validation_date"       => date("Y-m-d H:i:s"),
        ];

        $create_result = self::create($create_params);

        return $create_result["recurring_contribution_id"];
    }

}

?>
