<?php

use Civi\Api4;

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
        $params["contribution_status_id"] = "Pending";

        $create_result = civicrm_api3("ContributionRecur", "create", $params);

        $rc_id = (string) $create_result["id"];
        $rc_data = $create_result["values"][$rc_id];

        CRM_Core_Session::setStatus(
            "New EFT payment created. (Recurring contribution ID: $rc_id)",
            "Success",
            "info"
        );

        return $rc_id;
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

        $current_adapter_class = CRM_Contract_Utils::getPaymentAdapterClass($current_adapter);
        $current_adapter_class::terminate($recurring_contribution_id);

        // Get the current campaign ID
        $current_campaign_id = CRM_Utils_Array::value("campaign_id", $current_rc_data);

        // Get current the date
        $now = date("Y-m-d H:i:s");

        $create_params = [
            "amount"             => CRM_Utils_Array::value("amount", $update, $current_rc_data["amount"]),
            "campaign_id"        => !empty($update["campaign_id"]) ? $update["campaign_id"] : $current_campaign_id,
            "contact_id"         => $current_rc_data["contact_id"],
            "create_date"        => date("Y-m-d H:i:s"),
            "currency"           => CRM_Utils_Array::value("currency", $update, "EUR"),
            "cycle_day"          => CRM_Utils_Array::value("cycle_day", $update, $current_rc_data["cycle_day"]),
            "financial_type_id"  => $current_rc_data["financial_type_id"],
            "frequency_interval" => CRM_Utils_Array::value("frequency_interval", $update, $current_rc_data["frequency_interval"]),
            "frequency_unit"     => CRM_Utils_Array::value("frequency_unit", $update, $current_rc_data["frequency_unit"]),
            "start_date"         => $now,
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
        return range(1, 31);
    }

    /**
     * Get payment specific form field specifications
     *
     * @param array $params - Optional parameters, depending on the implementation
     *
     * @return array - List of form field specifications
     */
    public static function formFields ($params = []) {
        return [];
    }

    /**
     * Get payment specific JS variables for forms
     *
     * @param array $params - Optional parameters, depending on the implementation
     *
     * @return array - Form variables
     */
    public static function formVars ($params = []) {
        // ...

        return [
            "cycle_days"       => self::cycleDays(),
            "default_currency" => "EUR",
            "next_cycle_day"   => date("d"), // Today
        ];
    }

    public static function isInstance($recurringContributionID) {
      $paymentInstrument = Api4\ContributionRecur::get()
        ->addSelect('payment_instrument_id:name')
        ->addWhere('id', '=', $recurringContributionID)
        ->setLimit(1)
        ->execute()
        ->first()['payment_instrument_id:name'];

      return $paymentInstrument === 'EFT';
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
                    "payment_method.cycle_day"          => $submitted["cycle_day"],
                    "payment_method.financial_type_id"  => 2, // = Member dues
                    "payment_method.frequency_interval" => 12 / (int) $submitted["frequency"],
                    "payment_method.frequency_unit"     => "month",
                    "payment_method.validation_date"    => $now,
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
                    "membership_payment.cycle_day"            => $submitted["cycle_day"],
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
        Api4\ContributionRecur::update()
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
        if (count($update) > 0) {
            return self::update($recurring_contribution_id, $update);
        }

        Api4\ContributionRecur::update()
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
     * @param array $params
     * @param int $activity_type_id - Not used
     *
     * @throws Exception
     *
     * @return int - Recurring contribution ID
     */
    public static function update ($recurring_contribution_id, $params, $activity_type_id = null) {
        // Load current recurring contribution data
        $current_rc_data = civicrm_api3("ContributionRecur", "getsingle", [
            "id" => $recurring_contribution_id,
        ]);

        // Get the current campaign ID
        $current_campaign_id = CRM_Utils_Array::value("campaign_id", $current_rc_data);

        // Get current the date
        $now = date("Y-m-d H:i:s");

        // Terminate the current mandate
        self::terminate($recurring_contribution_id, "CHNG");

        // Create a new EFT payment
        $create_params = [
            "amount"             => CRM_Utils_Array::value("amount", $params, $current_rc_data["amount"]),
            "campaign_id"        => empty($params["campaign_id"]) ? $current_campaign_id : $params["campaign_id"],
            "contact_id"         => $current_rc_data["contact_id"],
            "create_date"        => $now,
            "currency"           => CRM_Utils_Array::value("currency", $params, $current_rc_data["currency"]),
            "cycle_day"          => CRM_Utils_Array::value("cycle_day", $params, $current_rc_data["cycle_day"]),
            "financial_type_id"  => CRM_Utils_Array::value("financial_type_id", $params, $current_rc_data["financial_type_id"]),
            "frequency_interval" => CRM_Utils_Array::value("frequency_interval", $params, $current_rc_data["frequency_interval"]),
            "frequency_unit"     => CRM_Utils_Array::value("frequency_unit", $params, $current_rc_data["frequency_unit"]),
            "start_date"         => $now,
        ];

        return self::create($create_params);
    }

}

?>
