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

    private $change_class;
    private $contact_id;
    private $medium_ids;
    private $membership;
    private $membership_types;
    private $modify_action;
    private $payment_adapters;
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

        // Change class
        $this->modify_action = strtolower(CRM_Utils_Request::retrieve("modify_action", "String"));
        $this->change_class = CRM_Contract_Change::getClassByAction($this->modify_action);

        if (empty($this->change_class)) {
            CRM_Core_Error::fatal(E::ts("Unknown action '%1'", [ 1 => $this->modify_action ]));
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

        // Title
        CRM_Utils_System::setTitle($this->change_class::getChangeTitle());

        // Contact
        $contact_id = $this->membership["contact_id"];
        $this->contact_id = $contact_id;
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

        // Payment adapters
        $this->payment_adapters = CRM_Contract_FormUtils::getPaymentAdapters();

        $resources = CRM_Core_Resources::singleton();

        foreach ($this->payment_adapters as $pa_name => $pa_class) {
            $resources->addVars("de.systopia.contract/$pa_name", $pa_class::formVars([
                "recurring_contribution_id" => $rc_id,
            ]));
        }

        $current_contract = CRM_Contract_RecurringContribution::getCurrentContract($contact_id, $rc_id);
        $frequencies = CRM_Contract_RecurringContribution::getPaymentFrequencies();

        $recurring_contributions = CRM_Contract_RecurringContribution::getAllForContact($contact_id, true);

        $resources->addVars("de.systopia.contract", [
            "action"                     => $this->modify_action,
            "cid"                        => $contact_id,
            "current_amount"             => $this->recurring_contribution["amount"],
            "current_contract"           => $current_contract,
            "current_cycle_day"          => (int) $this->recurring_contribution["cycle_day"],
            "current_payment_adapter"    => CRM_Contract_Utils::getPaymentAdapterForRecurringContribution($rc_id),
            "current_recurring"          => $rc_id,
            "debitor_name"               => $this->contact["display_name"],
            "default_currency"           => CRM_Sepa_Logic_Settings::defaultCreditor()->currency,
            "frequencies"                => $frequencies,
            "recurring_contributions"    => $recurring_contributions,
        ]);
    }

    function buildQuickForm () {

        // Currency
        $this->assign("currency", CRM_Sepa_Logic_Settings::defaultCreditor()->currency);

        // Payment change (payment_change)
        $payment_change_options = [
            "modify"          => ts("Modify payment"),
            "select_existing" => ts("Select existing contribution"),
        ];

        if ($this->modify_action !== "revive") {
            $payment_change_options = [ "no_change" => ts("No change") ] + $payment_change_options;
        }

        $this->add("select", "payment_change", ts("Payment change"), $payment_change_options);

        // Payment method (payment_adapter)
        $payment_adapter_options = [ "" => "- none -" ];

        foreach ($this->payment_adapters as $pa_name => $pa_class) {
            $payment_adapter_options[$pa_name] = $pa_class::adapterInfo()["display_name"];
        }

        $this->add(
            "select",
            "payment_adapter",
            ts("Payment method"),
            $payment_adapter_options,
            true
        );

        // Payment-adapter-specific fields
        $paf_template_var = [];

        foreach ($this->payment_adapters as $pa_name => $pa_class) {
            $paf_template_var[$pa_name] = [];

            foreach ($pa_class::formFields() as $field) {
                if (!$field["enabled"]) continue;

                $field_name = $field["name"];
                $field_id = "pa-$pa_name-$field_name";
                $field_settings = isset($field["settings"]) ? $field["settings"] : [];

                array_push($paf_template_var[$pa_name], $field_id);

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
        }

        $this->assign("payment_adapter_fields", $paf_template_var);
        $this->assign("payment_adapter_fields_json", json_encode($paf_template_var));

        // Recurring contribution (recurring_contribution)
        $formUtils = new CRM_Contract_FormUtils($this, "Membership");

        $formUtils->addPaymentContractSelect2(
            "recurring_contribution",
            $this->contact["id"],
            false
        );

        // Cycle day (cycle_day)
        $cycle_day_options = [ "" => "- none -" ];

        foreach (range(1, 31) as $cycle_day) {
            $cycle_day_options[$cycle_day] = $cycle_day;
        }

        $this->add("select", "cycle_day", ts("Cycle day"), $cycle_day_options, true);

        // Installment amount
        $this->add("text", "amount", ts("Installment amount"), [ "size" => 6 ]);

        // Payment frequency (frequency)
        $this->add(
            "select",
            "frequency",
            ts("Payment frequency"),
            CRM_Contract_RecurringContribution::getPaymentFrequencies()
        );

        // Defer payment start (defer_payment_start)
        $this->add(
            "checkbox",
            "defer_payment_start",
            ts("Defer payment start based on last collection?")
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
            true,
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

        // Payment change (payment_change)
        $defaults["payment_change"] = "modify";

        // Recurring contribution (recurring_contribution)
        $rc_id = $this->recurring_contribution["id"];
        $defaults["recurring_contribution"] = $rc_id;

        // Installment amount (amount)
        $defaults["amount"] = $this->recurring_contribution["amount"];

        // Payment frequency (frequency)
        $defaults["frequency"] = CRM_Contract_FormUtils::numberOfAnnualPayments($this->recurring_contribution);

        // Defer payment start (defer_payment_start)
        $defaults["defer_payment_start"] = $this->modify_action === "update";

        // Membership type (membership_type_id)
        $defaults["membership_type_id"] = $this->membership["membership_type_id"];

        // Schedule date (activity_date)
        $tomorrow = date("Y-m-d 00:00:00", strtotime("+1 day"));
        $default_change_date = CRM_Contract_Utils::getDefaultContractChangeDate($tomorrow);
        list($date, $time) = explode(" ", $default_change_date);
        $defaults["activity_date"] = $date;
        $defaults["activity_date_time"] = $time;

        $min_change_date = Civi::settings()->get("contract_minimum_change_date");
        $this->assign("default_to_minimum_change_date", $default_change_date === $min_change_date);

        // Payment-adapter-specific defaults
        foreach ($this->payment_adapters as $pa_name => $pa_class) {
            $form_fields = $pa_class::formFields([ 'recurring_contribution_id' => $rc_id ]);

            foreach ($form_fields as $field_name => $field) {
                if (!$field["enabled"] || empty($field["default"])) continue;

                $defaults["pa-$pa_name-$field_name"] = $field["default"];
            }
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

            case "modify":
                if (empty($submitted["amount"])) {
                    HTML_QuickForm::setElementError("amount", "Please specify a payment amount");
                }

                if (!preg_match('/^\d+((\.|,)\d+)?$/', $submitted["amount"])) {
                    HTML_QuickForm::setElementError ("amount", "Please enter a valid payment amount");
                }

                if (empty($submitted["frequency"])) {
                    HTML_QuickForm::setElementError("frequency", "Please specify a payment frequency");
                }

                $pa_name = $submitted["payment_adapter"];
                $pa_class = $this->payment_adapters[$pa_name];

                if (empty($submitted["cycle_day"])) {
                    HTML_QuickForm::setElementError("cycle_day", "Please select a cycle day");
                }

                foreach ($pa_class::formFields() as $field_name => $field) {
                    if (!$field["enabled"] || empty($field["validate"])) continue;

                    $field_id = "pa-$pa_name-$field_name";

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
            "medium_id" => $submitted["medium_id"],
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

        $contract_modify_params["membership_type_id"] = $submitted["membership_type_id"];
        $contract_modify_params["campaign_id"] = $submitted["campaign_id"];

        switch ($submitted["payment_change"]) {
            case "no_change":
                break;

            case "modify":
                $contract_modify_params["membership_payment.defer_payment_start"] = $submitted["defer_payment_start"] ?? "0";

                $pa_id = $submitted["payment_adapter"];
                $contract_modify_params["payment_method.adapter"] = $pa_id;
                $payment_adapter = CRM_Contract_Utils::getPaymentAdapterClass($pa_id);

                if (empty($payment_adapter)) break;

                $submitted["contact_id"] = $this->contact_id;
                $mapped_values = $payment_adapter::mapSubmittedFormValues("Contract.modify", $submitted);

                foreach ($mapped_values as $key => $value) {
                    $contract_modify_params[$key] = $value;
                }

                break;

            case "select_existing":
                $contract_modify_params["membership_payment.membership_recurring_contribution"] =
                    $submitted["recurring_contribution"];

                break;
        }

        civicrm_api3("Contract", "modify", $contract_modify_params);
        civicrm_api3("Contract", "process_scheduled_modifications", [ "id" => $this->get("id") ]);
    }

}

?>
