<?php

class CRM_Contract_PaymentAdapter_EFT implements CRM_Contract_PaymentAdapter {

    const ADAPTER_ID = "eft";
    const DISPLAY_NAME = "EFT";

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
        $params["payment_instrument_id"] = "EFT";

        $create_result = civicrm_api3("ContributionRecur", "create", $params);

        $rc_id = (string) $create_result["id"];
        $rc_data = $create_result["values"][$rc_id];

        CRM_Core_Session::setStatus(
            "New EFT payment created. (Recurring contribution ID: $rc_id)",
            "Success",
            "info"
        );

        return [
            "recurring_contribution_id" => $rc_id,
        ];
    }

    /**
     * Get a list of possible cycle days
     *
     * @return array - list of cycle days as integers
     */
    public static function cycleDays () {
        return range(1, 31);
    }

    /**
     * Get payment specific form field specifications
     *
     * @return array - List of form field specifications
     */
    public static function formFields () {
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
        return [];
    }

    /**
     * Get payment specific JS variables for forms
     *
     * @return array - Form variables
     */
    public static function formVars () {
        // ...

        return [
            "next_cycle_day" => date("d"), // Today
        ];
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

                $result = [
                    "payment_method.amount"             => CRM_Contract_Utils::formatMoney($submitted["amount"]),
                    "payment_method.campaign_id"        => $submitted["campaign_id"],
                    "payment_method.create_date"        => $now,
                    "payment_method.currency"           => $submitted["currency"],
                    "payment_method.cycle_day"          => $submitted["pa-eft-cycle_day"],
                    "payment_method.financial_type_id"  => 2, // = Member dues
                    "payment_method.frequency_interval" => 12 / (int) $submitted["frequency"],
                    "payment_method.frequency_unit"     => "month",
                    "payment_method.validation_date"    => $now,
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
            "activity_type_id"              => "activity_type_id",
            "contract_updates.ch_annual"    => "annual",
            "contract_updates.ch_cycle_day" => "cycle_day",
            "contract_updates.ch_frequency" => "frequency",
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
        // Nothing to do here
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
        // Nothing to do here
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
        $now = date("Y-m-d H:i:s");

        $update_result = civicrm_api3("ContributionRecur", "create", [
            "id"                     => $recurring_contribution_id,
            "cancel_date"            => $now,
            "cancel_reason"          => $reason,
            "contribution_status_id" => "Completed",
            "end_date"               => $now,
        ]);

        if ($update_result["is_error"]) {
            $error_message = $update_result["error_message"];
            throw new Exception("Contribution cannot be terminated: $error_message");
        }
    }

    /**
     * Update payment data
     *
     * @param int $recurring_contribution_id
     * @param array $params - Parameters depend on the implementation
     *
     * @throws Exception
     *
     * @return int - Recurring contribution ID
     */
    public static function update ($recurring_contribution_id, $params) {
        // Load current recurring contribution data
        $current_rc_data = civicrm_api3("ContributionRecur", "getsingle", [
            "id" => $recurring_contribution_id,
        ]);

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

        // Make API call
        $update_result = civicrm_api3("ContributionRecur", "create", [
            "id"                 => $recurring_contribution_id,
            "amount"             => $new_recurring_amount["amount"],
            "cycle_day"          => CRM_Utils_Array::value("cycle_day", $params, $current_rc_data["cycle_day"]),
            "frequency_interval" => $new_recurring_amount["frequency_interval"],
            "frequency_unit"     => $new_recurring_amount["frequency_unit"],
        ]);

        if ($update_result["is_error"]) {
            $error_message = $update_result["error_message"];
            throw new Exception("Contribution cannot be updated: $error_message");
        }

        return $recurring_contribution_id;
    }

}

?>
