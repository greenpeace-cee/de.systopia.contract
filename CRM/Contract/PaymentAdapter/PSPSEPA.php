<?php

use Civi\Api4;

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

        civicrm_api3("ContributionRecur", "create", [
            "id"                    => $mandate_data["entity_id"],
            "payment_instrument_id" => $params["payment_instrument_id"],
        ]);

        CRM_Core_Session::setStatus(
            "New PSP SEPA Mandate <a href=\"$mandate_url\">$mandate_reference</a> created.",
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

        // Get the creditor ID & currency
        $creditor_id = CRM_Utils_Array::value("creditor_id", $update);
        $currency = null;

        if (isset($creditor_id)) {
            $currency = civicrm_api3("SepaCreditor", "getvalue", [
                "return" => "currency",
                "id"     => $creditor_id,
            ]);
        }

        // Calculate the new start date
        $new_start_date = CRM_Contract_RecurringContribution::getUpdateStartDate(
            [ "membership_payment.membership_recurring_contribution" => $recurring_contribution_id ],
            [ "contract_updates.ch_defer_payment_start" => CRM_Utils_Array::value("defer_payment_start", $update, false) ],
            [ "activity_type_id" => $activity_type_id ],
            self::cycleDays()
        );

        $current_adapter_class = CRM_Contract_Utils::getPaymentAdapterClass($current_adapter);
        $current_adapter_class::terminate($recurring_contribution_id);

        $create_params = [
            "account_name"          => CRM_Utils_Array::value("account_name", $update),
            "account_reference"     => CRM_Utils_Array::value("account_reference", $update),
            "amount"                => CRM_Utils_Array::value("amount", $update, $current_rc_data["amount"]),
            "campaign_id"           => !empty($update["campaign_id"]) ? $update["campaign_id"] : $current_campaign_id,
            "contact_id"            => $current_rc_data["contact_id"],
            "creation_date"         => date("Y-m-d H:i:s"),
            "creditor_id"           => $creditor_id,
            "currency"              => $currency,
            "cycle_day"             => CRM_Utils_Array::value("cycle_day", $update, $current_rc_data["cycle_day"]),
            "financial_type_id"     => $current_rc_data["financial_type_id"],
            "frequency_interval"    => CRM_Utils_Array::value("frequency_interval", $update, $current_rc_data["frequency_interval"]),
            "frequency_unit"        => CRM_Utils_Array::value("frequency_unit", $update, $current_rc_data["frequency_unit"]),
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
     * @param array $params - array containing a creditor_id
     *
     * @return array - list of cycle days as integers
     */
    public static function cycleDays ($params = []) {
        if (empty($params['creditor_id'])) return [];

        $cycle_days_setting = CRM_Sepa_Logic_Settings::getListSetting(
            'cycledays',
            range(1, 28),
            $params['creditor_id']
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
        $psp_creditors = civicrm_api3("SepaCreditor", "get", [
            "creditor_type" => "PSP",
            "sequential"    => 1,
            "return"        => ["id", "label", "sepa_file_format_id"],
        ])["values"];

        $creditor_options = [];

        foreach ($psp_creditors as $pc) {
            $pain_version = civicrm_api3("OptionValue", "getvalue", [
                "option_group_id" => "sepa_file_format",
                "value"           => $pc['sepa_file_format_id'],
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

        $defaults = [];
        $recurring_contribution_id = CRM_Utils_Array::value("recurring_contribution_id", $params, NULL);

        if (isset($recurring_contribution_id) && self::isInstance($recurring_contribution_id)) {
            $rc_data = civicrm_api3("ContributionRecur", "getsingle", [
                "id" => $recurring_contribution_id,
            ]);

            $mandate_data = civicrm_api3("SepaMandate", "getsingle", [
                "entity_table" => "civicrm_contribution_recur",
                "entity_id"    => $recurring_contribution_id,
            ]);

            $defaults["creditor"] = $mandate_data["creditor_id"];
            $defaults["payment_instrument"] = $rc_data["payment_instrument"];
            $defaults["account_reference"] = $mandate_data["iban"];
            $defaults["account_name"] = $mandate_data["bic"];
        }

        return [
            "creditor" => [
                "default"      => CRM_Utils_Array::value("creditor", $defaults),
                "display_name" => "Creditor",
                "enabled"      => true,
                "name"         => "creditor",
                "options"      => $creditor_options,
                "required"     => true,
                "type"         => "select",
            ],
            "payment_instrument" => [
                "default"      => CRM_Utils_Array::value("payment_instrument", $defaults),
                "display_name" => "Payment instrument",
                "enabled"      => true,
                "name"         => "payment_instrument",
                "options"      => $payment_instrument_options,
                "required"     => true,
                "type"         => "select",
            ],
            "account_reference" => [
                "default"      => CRM_Utils_Array::value("account_reference", $defaults),
                "display_name" => "Account reference",
                "enabled"      => true,
                "name"         => "account_reference",
                "required"     => true,
                "settings"     => [ "class" => "huge" ],
                "type"         => "text",
            ],
            "account_name" => [
                "default"      => CRM_Utils_Array::value("account_name", $defaults),
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

        // Creditor-specific data
        $psp_creditors = civicrm_api3("SepaCreditor", "get", [
            "creditor_type" => "PSP",
            "sequential"    => 1,
            "return"        => ["id", "currency", "label"],
        ])["values"];

        $pi_option_values = civicrm_api3("OptionValue", "get", [
            "option_group_id" => "payment_instrument",
            "sequential"      => 1,
            "return"          => ["value", "label"],
        ])["values"];

        $currencies = [];
        $cycle_days = [];
        $payment_instruments = [];

        foreach ($psp_creditors as $creditor) {
            // Currencies
            $currencies[$creditor["id"]] = $creditor["currency"];

            // Cycle days
            $cycle_days[$creditor["id"]] = self::cycleDays([ "creditor_id" => $creditor["id"] ]);

            // Payment instruments
            $creditor_id = $creditor["id"];

            $creditor_pi_rcur = CRM_Core_DAO::singleValueQuery(
                "SELECT pi_rcur FROM civicrm_sdd_creditor WHERE id = $creditor_id LIMIT 1"
            );

            $pi_ids = explode(",", $creditor_pi_rcur);
            $payment_instruments[$creditor["id"]] = [];

            foreach ($pi_option_values as $pi_opt) {
                if (!in_array($pi_opt["value"], $pi_ids)) continue;
                $payment_instruments[$creditor["id"]][$pi_opt["value"]] = $pi_opt["label"];
            }
        }

        $result["currencies"] = $currencies;
        $result["cycle_days"] = $cycle_days;
        $result["payment_instruments"] = $payment_instruments;

        if (empty($params["recurring_contribution_id"])) return $result;

        // Current payment parameters
        $mandates_result = civicrm_api3("SepaMandate", "get", [
            "entity_table" => "civicrm_contribution_recur",
            "entity_id"    => $params["recurring_contribution_id"],
            "options"      => [ "sort" => "creation_date" ],
            "sequential"   => 1,
            "return"       => ["iban", "bic", "creditor_id"],
        ]);

        $current_mandate_data = end($mandates_result["values"]);

        $result["current_account_reference"] = $current_mandate_data["iban"];
        $result["current_account_name"] = $current_mandate_data["bic"];
        $result["current_creditor"] = $current_mandate_data["creditor_id"];

        $result["current_payment_instrument"] = civicrm_api3('ContributionRecur', 'getsingle', [
            "id" => $params["recurring_contribution_id"],
        ])["payment_instrument_id"];

        return $result;
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

      return $creditorType === 'PSP';
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
                    "payment_method.cycle_day"             => $submitted["cycle_day"],
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
                    "membership_payment.cycle_day"            => $submitted["cycle_day"],
                    "membership_payment.membership_annual"    => $annual_amount,
                    "membership_payment.membership_frequency" => $frequency,
                    "payment_method.account_name"             => $submitted["pa-psp_sepa-account_name"],
                    "payment_method.account_reference"        => $submitted["pa-psp_sepa-account_reference"],
                    "payment_method.creditor_id"              => $submitted["pa-psp_sepa-creditor"],
                    "payment_method.payment_instrument"       => $submitted["pa-psp_sepa-payment_instrument"],
                ];

                return $result;
            }

            default: {
                return [];
            }
        }
    }

    public static function noticeDays($creditor_id) {
        $notice_setting = CRM_Sepa_Logic_Settings::getSetting(
            "batching.RCUR.notice",
            $creditor_id
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
            "CRM_Activity_BAO_Activity",
            "activity_type_id",
            "Contract_Revived"
        );

        return self::update($recurring_contribution_id, $update, $revive_activity_type);
    }

    public static function startDate($params = [], $today = 'now') {
        $today = new DateTimeImmutable($today);
        $start_date = DateTime::createFromImmutable($today);

        // Existing contract

        if (isset($params['membership_id'])) {
            $recurring_contribution = CRM_Contract_RecurringContribution::getById(
                $params['membership_id']
            );
        }

        if (isset($recurring_contribution)) {
            $sepa_mandate = Api4\SepaMandate::get()
                ->addWhere('entity_table', '=', 'civicrm_contribution_recur')
                ->addWhere('entity_id'   , '=', $recurring_contribution['id'])
                ->addSelect('creditor_id')
                ->setLimit(1)
                ->execute()
                ->first();

            $params['creditor_id'] = $sepa_mandate['creditor_id'];
        }

        // PSP creditor ID

        if (empty($params['creditor_id'])) return NULL;

        $creditor_id = (int) $params['creditor_id'];

        // Notice days

        $start_date->add(self::noticeDays($creditor_id));
        
        // Minimum date

        if (isset($params['min_date'])) {
            $min_date = new DateTimeImmutable($params['min_date']);
        }

        if (isset($min_date) && $start_date->getTimestamp() < $min_date->getTimestamp()) {
            $start_date = DateTime::createFromImmutable($min_date);
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

            $paid_until = CRM_Contract_DateHelper::nextRegularDate(
                $latest_contribution['receive_date'],
                $latest_contribution_rc['frequency_interval'],
                $latest_contribution_rc['frequency_unit']
            );

            if ($start_date->getTimestamp() < $paid_until->getTimestamp()) {
                $start_date = $paid_until;
            }
        }

        // Allowed cycle days

        $allowed_cycle_days = self::cycleDays([ 'creditor_id' => $params['creditor_id'] ]);

        $start_date = CRM_Contract_DateHelper::findNextOfDays(
            $allowed_cycle_days,
            $start_date->format('Y-m-d')
        );

        if (empty($params['cycle_day']) && isset($recurring_contribution)) {
            $params['cycle_day'] = $recurring_contribution['cycle_day'];
        }

        $cycle_day = (int) CRM_Utils_Array::value('cycle_day', $params, $start_date->format('d'));

        if (!in_array($cycle_day, $allowed_cycle_days, TRUE)) {
            throw new Exception("Cycle day $cycle_day is not allowed for this PSP creditor");
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

        // Get the current campaign ID
        $current_campaign_id = CRM_Utils_Array::value("campaign_id", $current_rc_data);

        // Get the creditor & payment currency
        $creditor_id = CRM_Utils_Array::value("creditor_id", $params, $current_mandate_data["creditor_id"]);

        $creditor_currency = civicrm_api3("SepaCreditor", "getvalue", [
            "id"     => $creditor_id,
            "return" => "currency",
        ]);

        // Calculate the new start date
        $new_start_date = CRM_Contract_RecurringContribution::getUpdateStartDate(
            [ "membership_payment.membership_recurring_contribution" => $recurring_contribution_id ],
            [ "contract_updates.ch_defer_payment_start" => CRM_Utils_Array::value("defer_payment_start", $params, false) ],
            [ "activity_type_id" => $activity_type_id ],
            self::cycleDays()
        );

        // Terminate the current mandate
        self::terminate($recurring_contribution_id, "CHNG");

        // Create a new mandate
        $create_params = [
            "account_name"          => CRM_Utils_Array::value("account_name", $params, $current_mandate_data["bic"]),
            "account_reference"     => CRM_Utils_Array::value("account_reference", $params, $current_mandate_data["iban"]),
            "amount"                => CRM_Utils_Array::value("amount", $params, $current_rc_data["amount"]),
            "campaign_id"           => empty($params["campaign_id"]) ? $current_campaign_id : $params["campaign_id"],
            "contact_id"            => $current_rc_data["contact_id"],
            "creation_date"         => date("Y-m-d H:i:s"),
            "creditor_id"           => $creditor_id,
            "currency"              => CRM_Utils_Array::value("currency", $params, $creditor_currency),
            "cycle_day"             => CRM_Utils_Array::value("cycle_day", $params, $current_rc_data["cycle_day"]),
            "financial_type_id"     => CRM_Utils_Array::value("financial_type_id", $params, $current_rc_data["financial_type_id"]),
            "frequency_interval"    => CRM_Utils_Array::value("frequency_interval", $params, $current_rc_data["frequency_interval"]),
            "frequency_unit"        => CRM_Utils_Array::value("frequency_unit", $params, $current_rc_data["frequency_unit"]),
            "payment_instrument_id" => CRM_Utils_Array::value("payment_instrument", $params, $current_rc_data["payment_instrument_id"]),
            "start_date"            => $new_start_date,
            "type"                  => "RCUR",
            "validation_date"       => date("Y-m-d H:i:s"),
        ];

        return self::create($create_params);
    }

}

?>
