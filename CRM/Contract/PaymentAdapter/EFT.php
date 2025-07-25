<?php

use Civi\Api4;
use Civi\Api4\ContributionRecur;

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

        $params["start_date"] = self::startDate([
            "cycle_day" => CRM_Utils_Array::value("cycle_day", $params),
            "min_date"  => CRM_Utils_Array::value("start_date", $params),
        ])->format("Y-m-d");

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

        // Calculate the new start date
        $cycle_day = CRM_Utils_Array::value("cycle_day", $update, $current_rc_data["cycle_day"]);
        $defer_payment_start = CRM_Utils_Array::value("defer_payment_start", $update, TRUE);
        $min_date = CRM_Utils_Array::value("start_date", $update, $current_rc_data["start_date"]);

        $start_date = self::startDate([
            "cycle_day"           => $cycle_day,
            "defer_payment_start" => $defer_payment_start,
            "membership_id"       => $params["membership_id"],
            "min_date"            => $min_date,
        ]);

        $create_params = [
            "amount"             => CRM_Utils_Array::value("amount", $update, $current_rc_data["amount"]),
            "campaign_id"        => !empty($update["campaign_id"]) ? $update["campaign_id"] : $current_campaign_id,
            "contact_id"         => $current_rc_data["contact_id"],
            "create_date"        => date("Y-m-d H:i:s"),
            "currency"           => CRM_Utils_Array::value("currency", $update, "EUR"),
            "cycle_day"          => $cycle_day,
            "financial_type_id"  => $current_rc_data["financial_type_id"],
            "frequency_interval" => CRM_Utils_Array::value("frequency_interval", $update, $current_rc_data["frequency_interval"]),
            "frequency_unit"     => CRM_Utils_Array::value("frequency_unit", $update, $current_rc_data["frequency_unit"]),
            "start_date"         => $start_date->format("Y-m-d"),
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
        return range(1, 28);
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
        return [
            "cycle_days"          => self::cycleDays(),
            "default_currency"    => Civi::settings()->get('defaultCurrency'),
            "payment_frequencies" => CRM_Contract_RecurringContribution::getPaymentFrequencies([1, 2, 3, 4, 6, 12]),
        ];
    }

    public static function isInstance($recurringContributionID) {
      $paymentInstrument = Api4\ContributionRecur::get(FALSE)
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
     * Pause payment
     *
     * @param int $recurring_contribution_id
     *
     * @throws Exception
     *
     * @return void
     */
    public static function pause ($recurring_contribution_id) {
        Api4\ContributionRecur::update(FALSE)
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

        Api4\ContributionRecur::update(FALSE)
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
            'CRM_Activity_BAO_Activity',
            'activity_type_id',
            'Contract_Revived'
        );

        return self::update($recurring_contribution_id, $update, $revive_activity_type);
    }

    public static function startDate($params = [], $today = 'now') {
        $today = new DateTimeImmutable($today);
        $start_date = DateTime::createFromImmutable($today);

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

        if (isset($recurring_contribution)) {
            $params['cycle_day'] = CRM_Utils_Array::value(
                'cycle_day',
                $params,
                $recurring_contribution['cycle_day']
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
                [min((int) $latest_contribution_rc['cycle_day'], max(self::cycleDays()))],
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

        $cycle_day = (int) (
            isset($params['cycle_day'])
            ? $params['cycle_day']
            : $start_date->format('d')
        );

        if (!in_array($cycle_day, $allowed_cycle_days, TRUE)) {
            throw new Exception("Cycle day $cycle_day is not allowed for EFT payments");
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
        $now = date("Y-m-d H:i:s");

        ContributionRecur::update(FALSE)
          ->addValue('cancel_date', $now)
          ->addValue('end_date', $now)
          ->addValue('cancel_reason', $reason)
          ->addValue('contribution_status_id:name', 'Completed')
          ->addValue('next_sched_contribution_date', NULL)

          ->addWhere('id', '=', $recurring_contribution_id)
          ->execute();
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

        // Calculate the new start date
        $cycle_day = CRM_Utils_Array::value("cycle_day", $params, $current_rc_data["cycle_day"]);
        $defer_payment_start = CRM_Utils_Array::value("defer_payment_start", $params, TRUE);
        $min_date = CRM_Utils_Array::value("start_date", $params, $current_rc_data["start_date"]);

        $start_date = self::startDate([
            "cycle_day"           => $cycle_day,
            "defer_payment_start" => $defer_payment_start,
            "membership_id"       => $params["membership_id"],
            "min_date"            => $min_date,
        ]);

        // Terminate the current mandate (if necessary)
        $revive_activity_type = CRM_Core_PseudoConstant::getKey(
            "CRM_Activity_BAO_Activity",
            "activity_type_id",
            "Contract_Revived"
        );

        if ($activity_type_id !== $revive_activity_type) {
            self::terminate($recurring_contribution_id, "CHNG");
        }

        // Create a new EFT payment
        $create_params = [
            "amount"             => CRM_Utils_Array::value("amount", $params, $current_rc_data["amount"]),
            "campaign_id"        => empty($params["campaign_id"]) ? $current_campaign_id : $params["campaign_id"],
            "contact_id"         => $current_rc_data["contact_id"],
            "create_date"        => date('Y-m-d'),
            "currency"           => CRM_Utils_Array::value("currency", $params, $current_rc_data["currency"]),
            "cycle_day"          => $cycle_day,
            "financial_type_id"  => CRM_Utils_Array::value("financial_type_id", $params, $current_rc_data["financial_type_id"]),
            "frequency_interval" => CRM_Utils_Array::value("frequency_interval", $params, $current_rc_data["frequency_interval"]),
            "frequency_unit"     => CRM_Utils_Array::value("frequency_unit", $params, $current_rc_data["frequency_unit"]),
            "start_date"         => $start_date->format("Y-m-d"),
        ];

        return self::create($create_params);
    }

}

?>
