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
     * Get a list of allowed cycle days
     */
    public static function cycleDays () {
        $creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
        return CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $creditor->id);
    }

    /**
     * Get payment specific form field specifications
     *
     * @return array - List of form field specifications
     */
    public static function formFields () {
        // ...

        return [];
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
     * @param array $submitted
     * @param string $apiEndpoint
     *
     * @throws Exception
     *
     * @return array - API parameters
     */
    public static function mapToApiParameters ($submitted, $apiEndpoint) {
        // ...

        return [];
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
            "contract_updates.ch_from_ba"             => "from_ba",
            "contract_updates.ch_to_ba"               => "to_ba",
            "payment_method.creditor_id"              => "creditor_id",
            "payment_method.currency"                 => "currency",
            "payment_method.financial_type_id"        => "financial_type_id",
        ];

        foreach ($mapping as $update_key => $result_key) {
            if (isset($update_params[$update_key])) {
                $result[$result_key] = $update_params[$update_key];
            }
        }

        return $result;
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
    }

    /**
     * Update payment data
     *
     * @param int $recurring_contribution_id
     * @param array $params - Parameters depend on the implementation
     *
     * @throws Exception
     *
     * @return void
     */
    public static function update ($recurring_contribution_id, $params) {
        // Load current recurring contribution / SEPA mandate data
        $current_rc_data = civicrm_api3("ContributionRecur", "getsingle", [
            "id" => $recurring_contribution_id,
        ]);

        $current_mandate_data = civicrm_api3("SepaMandate", "getsingle", [
            "entity_id"    => $recurring_contribution_id,
            "entity_table" => "civicrm_contribution_recur",
            "status"       => [ "IN" => ["FRST", "RCUR"] ],
          ]);

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

        // Terminate the current mandate
        self::terminate($recurring_contribution_id, "CHNG");

        // Create a new mandate
        $create_params = [
            "amount"             => $new_recurring_amount["amount"],
            "bic"                => CRM_Utils_Array::value("bic", $bank_account, $current_mandate_data["bic"]),
            "campaign_id"        => CRM_Utils_Array::value("campaign_id", $params, $current_rc_data["campaign_id"]),
            "contact_id"         => $current_rc_data["contact_id"],
            "creation_date"      => date("Y-m-d H:i:s"),
            "creditor_id"        => CRM_Utils_Array::value("creditor_id", $params, $current_mandate_data["creditor_id"]),
            "currency"           => CRM_Utils_Array::value("currency", $params, $current_rc_data["currency"]),
            "cycle_day"          => CRM_Utils_Array::value("cycle_day", $params, $current_rc_data["cycle_day"]),
            "financial_type_id"  => CRM_Utils_Array::value("financial_type_id", $params, $current_rc_data["financial_type_id"]),
            "frequency_interval" => $new_recurring_amount["frequency_interval"],
            "frequency_unit"     => $new_recurring_amount["frequency_unit"],
            "iban"               => CRM_Utils_Array::value("iban", $bank_account, $current_mandate_data["iban"]),
            "start_date"         => $new_start_date,
            "type"               => "RCUR",
            "validation_date"    => date("Y-m-d H:i:s"),
        ];

        $create_result = self::create($create_params);

        return $create_result["recurring_contribution_id"];
    }

}

?>
