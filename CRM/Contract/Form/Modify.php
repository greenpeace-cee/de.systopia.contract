<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

class CRM_Contract_Form_Modify extends CRM_Core_Form {

    private static $payment_instruments = [
        "sepa_mandate" => "CRM_Contract_PaymentInstrument_SepaMandate",
    ];

    private $change_class;
    private $contact;
    private $medium_ids;
    private $membership;
    private $membership_types;
    private $modify_action;
    private $payment_instrument;
    private $payment_instrument_class;
    private $recurring_contribution;

    function preProcess () {

        // File download
        $download = CRM_Utils_Request::retrieve(
            "ct_dl",
            "String",
            CRM_Core_DAO::$_nullObject,
            false,
            "",
            "GET"
        );

        if (isset($download)) {
            if (CRM_Contract_Utils::downloadContractFile($download)) {
                CRM_Utils_System::civiExit();
            }

            CRM_Core_Error::fatal("File does not exist");
        }

        // Contract
        $contract_id = CRM_Utils_Request::retrieve("id", "Integer");

        if (empty($contract_id)) {
            CRM_Core_Error::fatal("Missing the contract ID");
        }

        $this->set("id", $contract_id);

        $modifications = civicrm_api3("Contract", "get_open_modification_counts", [
            "id" => $contract_id,
        ])["values"];

        if ($modifications["scheduled"] || $modifications["needs_review"]) {
            CRM_Core_Session::setStatus(
                "Some updates have already been scheduled for this contract.
                Please ensure that this new update will not conflict with existing updates.",
                "Scheduled updates exist!",
                "alert",
                [ "expires" => 0 ]
            );
        }

        // Membership
        try {
            $this->membership = civicrm_api3("Membership", "getsingle", [ "id" => $contract_id ]);
        } catch (Exception $e) {
            CRM_Core_Error::fatal("Not a valid contract ID");
        }

        // Modify action
        $this->modify_action = strtolower(CRM_Utils_Request::retrieve("modify_action", "String"));
        $this->change_class = CRM_Contract_Change::getClassByAction($this->modify_action);

        if (empty($this->change_class)) {
            CRM_Core_Error::fatal(E::ts("Unknown action '%1'", [ 1 => $this->modify_action ]));
        }

        // Title
        CRM_Utils_System::setTitle($this->change_class::getChangeTitle());

        // Contact
        $contact_id = $this->membership["contact_id"];
        $this->assign("cid", $contact_id);
        $this->contact = civicrm_api3("Contact", "getsingle", [ "id" => $contact_id ]);

        // Destination
        $this->controller->_destination = CRM_Utils_System::url(
            "civicrm/contact/view",
            "reset=1&cid=$contact_id&selectedChild=member"
        );

        // Current cycle day
        $rc_custom_field_id = CRM_Contract_Utils::getCustomFieldId("membership_payment.membership_recurring_contribution");
        $rc_id = $this->membership[$rc_custom_field_id];
        $current_cycle_day = CRM_Contract_RecurringContribution::getCycleDay($rc_id);
        $this->assign("current_cycle_day", $current_cycle_day);

        // Validate membership status
        $status_id = $this->membership["status_id"];
        $membership_status =  CRM_Contract_Utils::getMembershipStatusName($status_id);
        $status_list = $this->change_class::getStartStatusList();

        if (!in_array($membership_status, $status_list)) {
            CRM_Core_Error::fatal(
                E::ts("Invalid modification for status '%1'", [ 1 => $membership_status ])
            );
        }

        // Medium IDs
        $this->medium_ids = civicrm_api3("Activity", "getoptions", [
            "field" => "activity_medium_id",
            "options" => [
            "limit" => 0,
            "sort" => "weight",
            ],
        ])["values"];

        // Membership types
        $this->membership_types = [];

        $membership_types_result = civicrm_api3(
            "MembershipType",
            "get",
            [
            "options" => [
                "limit" => 0,
                "sort" => "weight",
            ],
            ]
        )["values"];

        foreach ($membership_types_result as $mem_type) {
            $this->membership_types[$mem_type["id"]] = $mem_type["name"];
        }

        // Recurring contribution
        $this->recurring_contribution = civicrm_api3("ContributionRecur", "getsingle", [ "id" => $rc_id ]);

        // Payment instrumtent
        foreach (self::$payment_instruments as $pi_name => $pi_class) {
            if ($pi_class::isInstance($this->recurring_contribution["payment_instrument_id"])) {
                $this->payment_instrument = $pi_class::loadByRecurringContributionId($rc_id);
                $this->payment_instrument_class = $pi_class;
                break;
            }
        }

        // JS variables
        $cycle_days =
            isset($this->payment_instrument_class)
            ? $this->payment_instrument_class::getCycleDays()
            : range(1, 28);

        $grace_end = CRM_Contract_RecurringContribution::getNextInstallmentDate($rc_id, $cycle_days);
        $current_contract = CRM_Contract_RecurringContribution::getCurrentContract($contact_id, $rc_id);
        $frequencies = CRM_Contract_RecurringContribution::getPaymentFrequencies();

        $current_frequency = [
            "interval" => $this->recurring_contribution["frequency_interval"],
            "unit"     => $this->recurring_contribution["frequency_unit"],
        ];

        $recurring_contributions = CRM_Contract_RecurringContribution::getAllForContact($contact_id, true);

        $resources = CRM_Core_Resources::singleton();

        $resources->addVars("de.systopia.contract", [
            "action"                  => $this->modify_action,
            "cid"                     => $contact_id,
            "current_amount"          => $this->recurring_contribution["amount"],
            "current_contract"        => $current_contract,
            "current_cycle_day"       => $this->recurring_contribution["cycle_day"],
            "current_frequency"       => $current_frequency,
            "current_recurring"       => $rc_id,
            "debitor_name"            => $this->contact["display_name"],
            "frequencies"             => $frequencies,
            "grace_end"               => $grace_end,
            "recurring_contributions" => $recurring_contributions,
        ]);

        foreach (self::$payment_instruments as $pi_name => $pi_class) {
            $resources->addVars(
                "de.systopia.contract/$pi_name",
                $pi_class::formVars($this->payment_instrument)
            );
        }

    }

    function buildQuickForm () {

        // Currency
        $this->assign("currency", CRM_Sepa_Logic_Settings::defaultCreditor()->currency);

        // Payment change (payment_change)
        $this->add("select", "payment_change", ts("Payment change"), [
            "no_change"       => ts("No change"),
            "create"          => ts("New payment method"),
            "modify"          => ts("Modify current payment method"),
            "select_existing" => ts("Select existing contribution"),
        ]);

        // Payment method (payment_instrument)
        $payment_instrument_options = [];

        foreach (self::$payment_instruments as $pi_name => $pi_class) {
            $payment_instrument_options[$pi_name] = $pi_class::displayName();
        }

        $this->add(
            "select",
            "payment_instrument",
            ts("Payment method"),
            $payment_instrument_options,
            false
        );

        // Payment-instrument-specific fields
        $pif_template_var = [];

        foreach (self::$payment_instruments as $pi_name => $pi_class) {
            $pif_template_var[$pi_name] = [];

            foreach ($pi_class::formFields() as $field) {
                if (!$field["enabled"]) continue;

                $field_name = $field["name"];
                $field_id = "pi-$pi_name-$field_name";
                $field_settings = isset($field["settings"]) ? $field["settings"] : [];

                array_push($pif_template_var[$pi_name], $field_id);

                switch ($field["type"]) {
                    case "select":
                        $this->add(
                            "select",
                            $field_id,
                            ts($field["display_name"]),
                            $field["options"]
                        );

                        break;

                    case "text":
                        $this->add("text", $field_id, ts($field["display_name"]), $field_settings);
                        break;
                }
            }

            $this->add(
                "select",
                "pi-$pi_name-cycle_day",
                ts("Cycle day"),
                $pi_class::getCycleDays()
            );
        }

        $this->assign("payment_instrument_fields", $pif_template_var);
        $this->assign("payment_instrument_fields_json", json_encode($pif_template_var));

        // Recurring contribution (recurring_contribution)
        $formUtils = new CRM_Contract_FormUtils($this, "Membership");

        $formUtils->addPaymentContractSelect2(
            "recurring_contribution",
            $this->contact["id"],
            false
        );

        // Installment amount
        $this->add("text", "amount", ts("Installment amount"), [ "size" => 6 ]);

        // Payment frequency (frequency)
        $this->add(
            "select",
            "frequency",
            ts("Payment frequency"),
            CRM_Contract_RecurringContribution::getPaymentFrequencies()
        );

        // Membership type (membership_type_id)
        $membership_type_id_options = [ "" => ts("- none -") ] + $this->membership_types;

        $this->add(
            "select",
            "membership_type_id",
            ts("Membership type"),
            $membership_type_id_options,
            true,
            [ "class" => "crm-select2" ]
        );

        // Campaign (campaign_id)
        $this->add(
            "select",
            "campaign_id",
            ts("Campaign"),
            CRM_Contract_Configuration::getCampaignList(),
            false,
            [ "class" => "crm-select2" ]
        );

        // Schedule date (activity_date)
        $this->add(
            "datepicker",
            "activity_date",
            ts("Schedule date"),
            [],
            true,
            [ "time" => true ]
        );

        // Source media (medium_id)
        $medium_id_options = [ "" => ts("- none -") ] + $this->medium_ids;

        $this->add(
            "select",
            "medium_id",
            ts("Source media"),
            $medium_id_options,
            false,
            [ "class" => "crm-select2" ]
        );

        // Notes (notes)
        if (version_compare(CRM_Utils_System::version(), "4.7", "<")) {
            $this->addWysiwyg("activity_details", ts("Notes"), []);
        } else {
            $this->add("wysiwyg", "activity_details", ts("Notes"));
        }
            // Form buttons
        $this->addButtons([
            [
                "type" => "cancel",
                "name" => ts("Cancel"),
                "submitOnce" => true,
            ],
            [
                "type" => "submit",
                "name" => ts("Create"),
                "submitOnce" => true,
            ],
        ]);

        $this->setDefaults();

    }

    function setDefaults ($defaultValues = null, $filter = null) {
        $defaults = [];

        // Recurring contribution (recurring_contribution)
        $defaults["recurring_contribution"] = $this->recurring_contribution["id"];

        // Payment frequency (frequency)
        $defaults["frequency"] = "12"; // monthly

        // Source media (medium_id)
        $defaults["medium_id"] = "7"; // Back Office

        // Membership type (membership_type_id)
        $defaults["membership_type_id"] = $this->membership["membership_type_id"];

        // Schedule date (activity_date)
        $tomorrow = date("Y-m-d 00:00:00", strtotime("+1 day"));
        $minimum_change_date = Civi::settings()->get("contract_minimum_change_date");
        $default_change_date = null;

        if (isset($minimum_change_date) && strtotime($tomorrow) < strtotime($minimum_change_date)) {
            $default_change_date = $minimum_change_date;
            $this->assign("default_to_minimum_change_date", true);
        } else {
            $default_change_date = $tomorrow;
            $this->assign("default_to_minimum_change_date", false);
        }

        list($date, $time) = CRM_Utils_Date::setDateDefaults($default_change_date, "activityDateTime");
        $defaults["activity_date"] = $date;
        $defaults["activity_date_time"] = $time;

        // Payment-instrument-specific defaults
        foreach (self::$payment_instruments as $pi_name => $pi_class) {
            foreach ($pi_class::formFields() as $field_name => $field) {
                if (!$field["enabled"] || empty($field["default"])) continue;

                $defaults["pi-$pi_name-$field_name"] = $field["default"];
            }

            $defaults["pi-$pi_name-cycle_day"] = $pi_class::nextCycleDay();
        }

        parent::setDefaults($defaults);
    }

    function validate() {
        $submitted = $this->exportValues();

        // Schedule date (activity_date)
        $activity_date = CRM_Utils_Date::processDate(
            $submitted["activity_date"],
            $submitted["activity_date_time"]
        );

        $minimum_change_date = Civi::settings()->get("contract_minimum_change_date");

        if (isset($minimum_change_date) && strtotime($activity_date) < strtotime($minimum_change_date)) {
            $formatted_date = date("j M Y h:i a", strtotime($minimum_change_date));

            HTML_QuickForm::setElementError(
                "activity_date",
                "Activity date must be after the minimum change date $formatted_date"
            );
        }

        $midnight_this_morning = date("Ymd000000");

        if(strtotime($activity_date) < strtotime($midnight_this_morning)){
            HTML_QuickForm::setElementError(
                "activity_date",
                "Activity date must be either today (which will execute the change now) or in the future"
            );
        }

        switch ($submitted["payment_change"]) {
            case "no_change":
                break;

            case "create":
            case "modify":
                if (empty($submitted["amount"])) {
                    HTML_QuickForm::setElementError("amount", "Please specify a payment amount");
                }

                if (empty($submitted["frequency"])) {
                    HTML_QuickForm::setElementError("frequency", "Please specify a payment frequency");
                }

                $pi_name = $submitted["payment_instrument"];
                $pi_class = self::$payment_instruments[$pi_name];

                foreach ($pi_class::formFields() as $field_name => $field) {
                    if (!$field["enabled"] || empty($field["validate"])) continue;

                    $field_id = "pi-$pi_name-$field_name";

                    try {
                        call_user_func($field["validate"], $submitted[$field_id], $field["required"]);
                    } catch (Exception $exception) {
                        HTML_QuickForm::setElementError($field_id, $exception->getMessage());
                    }
                }

                break;

            case "select_existing":
                if (empty($submitted["recurring_contribution"])) {
                    HTML_QuickForm::setElementError(
                        "recurring_contribution",
                        ts("%1 is a required field", [ 1 => ts("Recurring Contribution") ])
                    );
                }

                break;
        }

        return parent::validate();
    }

    function postProcess() {
        $submitted = $this->exportValues();

        $contract_modify_params = [
            "action"    => $this->modify_action,
            "id"        => $this->get("id"),
            "medium_id" => $submitted["activity_medium"],
            "note"      => $submitted["activity_details"],
        ];

        if ($submitted["activity_date"]) {
            $contract_modify_params["date"] = CRM_Utils_Date::processDate(
                $submitted["activity_date"],
                $submitted["activity_date_time"],
                false,
                "Y-m-d H:i:s"
            );
        }

        switch ($submitted["payment_change"]) {
            case "no_change":
                break;

            case "create":
                $payment_instrument = $submitted["payment_instrument"];
                $pi_class = self::$payment_instruments[$payment_instrument];

                $submitted["contact_id"] = $this->contact["id"];
                $submitted["start_date"] = $submitted["activity_date"];

                $payment_params = $pi_class::mapSubmittedValues($submitted);
                $new_payment = $pi_class::create($payment_params);

                $entity_id = $new_payment->getParameters()["entity_id"];
                $contract_params["membership_payment.membership_recurring_contribution"] = $entity_id;

                $frequency = (int) $submitted["frequency"];
                $contract_modify_params["membership_payment.membership_frequency"] = $frequency;

                $amount = (float) CRM_Contract_Utils::formatMoney($submitted["amount"]);
                $annual_amount = $frequency * $amount;
                $contract_modify_params["membership_payment.membership_annual"] = CRM_Contract_Utils::formatMoney($annual_amount);

                $contract_modify_params["membership_payment.cycle_day"] = $submitted["pi-$payment_instrument-cycle_day"];

                if ($submitted["payment_instrument"] === "sepa_mandate") {
                    $contract_modify_params["membership_payment.to_ba"]   = CRM_Contract_BankingLogic::getCreditorBankAccount();

                    $bic = isset($submitted["pi-sepa_mandate-bic"]) ? $submitted["pi-sepa_mandate-bic"] : null;

                    $contract_modify_params["membership_payment.from_ba"] = CRM_Contract_BankingLogic::getOrCreateBankAccount(
                        $this->membership["contact_id"],
                        $submitted["pi-sepa_mandate-iban"],
                        $bic
                    );
                }

                break;

            case "modify":
                $frequency = (int) $submitted["frequency"];
                $contract_modify_params["membership_payment.membership_frequency"] = $frequency;

                $amount = (float) CRM_Contract_Utils::formatMoney($submitted["amount"]);
                $annual_amount = $frequency * $amount;
                $contract_modify_params["membership_payment.membership_annual"] = CRM_Contract_Utils::formatMoney($annual_amount);

                $payment_instrument = $submitted["payment_instrument"];
                $contract_modify_params["membership_payment.cycle_day"] = $submitted["pi-$payment_instrument-cycle_day"];

                if ($submitted["payment_instrument"] === "sepa_mandate") {
                    $contract_modify_params["membership_payment.to_ba"]   = CRM_Contract_BankingLogic::getCreditorBankAccount();

                    $bic = isset($submitted["pi-sepa_mandate-bic"]) ? $submitted["pi-sepa_mandate-bic"] : null;

                    $contract_modify_params["membership_payment.from_ba"] = CRM_Contract_BankingLogic::getOrCreateBankAccount(
                        $this->membership["contact_id"],
                        $submitted["pi-sepa_mandate-iban"],
                        $bic
                    );
                }

                break;

            case "select_existing":
                $contract_modify_params["membership_payment.membership_recurring_contribution"] =
                    $submitted["recurring_contribution"];

                break;
        }

        $contract_modify_params["membership_type_id"] = $submitted["membership_type_id"];
        $contract_modify_params["campaign_id"] = $submitted["campaign_id"];

        civicrm_api3("Contract", "modify", $contract_modify_params);
        civicrm_api3("Contract", "process_scheduled_modifications", [ "id" => $this->get("id") ]);
    }

}

?>
