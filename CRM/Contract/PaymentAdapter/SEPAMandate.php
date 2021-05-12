<?php

class CRM_Contract_PaymentAdapter_SEPAMandate implements CRM_Contract_PaymentAdapter {

    const ADAPTER_ID = "sepa_mandate";
    const DISPLAY_NAME = "SEPA mandate";

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

        return [
            "recurring_contribution_id" => $mandate_data["entity_id"],
        ];
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
     * @return array - List of form field specifications
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
                "validate"     => "CRM_Contract_PaymentAdapter_SEPAMandate::validateIBAN",
            ],

            "bic" => [
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
     * Get necessary JS files for forms
     *
     * @return array - Paths to the files
     */
    public static function formScripts () {
        // ...

        return [];
    }

    /**
     * Get necessary templates for forms
     *
     * @return array - Paths to the templates
     */
    public static function formTemplates () {
        // ...

        return [];
    }

    /**
     * Get payment specific JS variables for forms
     *
     * @return array - Form variables
     */
    public static function formVars () {
        // ...

        return [];
    }

    /**
     * Check if a recurring contribution is associated with the implemented
     * payment method
     *
     * @param int $recurring_contribution_id
     *
     * @throws Exception
     *
     * @return boolean
     */
    public static function isInstance ($recurring_contribution_id) {
        // ...

        return false;
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
                    "payment_method.cycle_day"          => $submitted["pa-sepa_mandate-cycle_day"],
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
            "campaign_id"                             => "campaign_id",
            "contract_updates.ch_annual"              => "annual",
            "contract_updates.ch_cycle_day"           => "cycle_day",
            "contract_updates.ch_defer_payment_start" => "defer_payment_start",
            "contract_updates.ch_frequency"           => "frequency",
            "contract_updates.ch_from_ba"             => "from_ba",
            "contract_updates.ch_to_ba"               => "to_ba",
            "payment_method.creditor_id"              => "creditor_id",
            "payment_method.currency"                 => "currency",
            "payment_method.financial_type_id"        => "financial_type_id",
            "payment_method.reference"                => "reference",
        ];

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
     *
     * @throws Exception
     *
     * @return void
     */
    public static function resume ($recurring_contribution_id) {
        $mandate = civicrm_api3("SepaMandate", "getsingle", [
            "entity_id"    => $recurring_contribution_id,
            "entity_table" => "civicrm_contribution_recur",
        ]);

        if ($mandate["status"] !== "ONHOLD") {
            throw new Exception("SEPA mandate cannot be resumed: Mandate is not paused");
        }

        $new_status = isset($mandate["first_contribution_id"]) ? "RCUR" : "FRST";

        $update_result = civicrm_api3("SepaMandate", "create", [
            "id"     => $mandate["id"],
            "status" => $new_status,
        ]);

        if ($update_result["is_error"]) {
            $error_message = $update_result["error_message"];
            throw new Exception("SEPA mandate cannot be resumed: $error_message");
        }
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
     * @param boolean $terminate_current - Terminate the current contribution/payment
     *
     * @throws Exception
     *
     * @return int - Recurring contribution ID
     */
    public static function update ($recurring_contribution_id, $params, $terminate_current = true) {
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
            (int) CRM_Utils_Array::value("frequency", $params, $current_rc_data["frequency"]),
        );

        // Calculate the new start date
        $new_start_date = CRM_Contract_RecurringContribution::getUpdateStartDate(
            [ "membership_payment.membership_recurring_contribution" => $recurring_contribution_id ],
            [ "contract_updates.ch_defer_payment_start" => CRM_Utils_Array::value("defer_payment_start", $params, false) ],
            [ "activity_type_id" => CRM_Utils_Array::value("activity_type_id", $params) ],
            self::cycleDays(),
        );

        // Get bank account by ID
        $bank_account_id = CRM_Utils_Array::value("from_ba", $params);
        $bank_account = CRM_Contract_BankingLogic::getBankAccount($bank_account_id);
        $current_bic = CRM_Utils_Array::value("bic", $current_mandate_data);

        // Terminate the current mandate
        if ($terminate_current) self::terminate($recurring_contribution_id, "CHNG");

        // Create a new mandate
        $create_params = [
            "amount"             => $new_recurring_amount["amount"],
            "bic"                => CRM_Utils_Array::value("bic", $bank_account, $current_bic),
            "campaign_id"        => CRM_Utils_Array::value("campaign_id", $params, $current_campaign_id),
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

        $create_result = self::create($create_params);

        return $create_result["recurring_contribution_id"];
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
