<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Form_Create extends CRM_Core_Form {

    private $change_class;
    private $contact;
    private $mediums;
    private $membership_channels;
    private $membership_types;
    private $payment_adapters;

    function preProcess () {

        // Change class
        $this->change_class = CRM_Contract_Change::getClassByAction("sign");

        // Title
        CRM_Utils_System::setTitle($this->change_class::getChangeTitle());

        // Destination
        $this->controller->_destination = CRM_Utils_System::url(
            "civicrm/contact/view",
            "reset=1&cid={$this->contact_id}&selectedChild=member"
        );

        // Contact
        $contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');

        if (empty($contact_id)) {
            $contact_id = $this->get("cid");
        } else {
            $this->set("cid", $contact_id);
        }

        if (empty($contact_id)) {
            CRM_Core_Error::statusBounce("You have to specify a contact ID to create a new contract");
        }

        $this->contact = civicrm_api3("Contact", "getsingle", [ "id" => $contact_id ]);

        // Mediums
        $this->mediums = CRM_Contract_FormUtils::getOptionValueLabels("encounter_medium");

        // Membership channels
        $this->membership_channels = CRM_Contract_FormUtils::getOptionValueLabels("contact_channel");

        // Membership types
        $this->membership_types = CRM_Contract_FormUtils::getMembershipTypes();

        // Payment adapters
        $this->payment_adapters = CRM_Contract_Configuration::$paymentAdapters;
        $resources = CRM_Core_Resources::singleton();
        $paymentAdapterFields = [];

        foreach ($this->payment_adapters as $paName => $paClass) {
            $resources->addVars("de.systopia.contract/$paName", $paClass::formVars());

            $paymentAdapterFields[$paName] = [];
            $formFields = $paClass::formFields([ "form" => "sign" ]);

            foreach ($formFields as $field) {
                if (!$field["enabled"]) continue;

                $fieldName = $field["name"];
                $fieldID = "pa-$paName-$fieldName";

                $paymentAdapterFields[$paName][] = $fieldID;
            }
        }

        // JS variables
        $resources->addVars("de.systopia.contract", [
            "action"                  => "sign",
            "cid"                     => $this->contact["id"],
            "debitor_name"            => $this->contact["display_name"],
            "default_currency"        => CRM_Sepa_Logic_Settings::defaultCreditor()->currency,
            "ext_base_url"            => rtrim($resources->getUrl("de.systopia.contract"), "/"),
            "frequencies"             => CRM_Contract_RecurringContribution::getPaymentFrequencies(),
            "grace_end"               => NULL,
            "payment_adapter_fields"  => $paymentAdapterFields,
            "payment_adapters"        => CRM_Contract_Configuration::$paymentAdapters,
            "recurring_contributions" => CRM_Contract_RecurringContribution::getAllForContact($this->contact["id"]),
        ]);

    }

    function buildQuickForm () {
        $this->assign("bic_lookup_accessible", CRM_Sepa_Logic_Settings::isLittleBicExtensionAccessible());
        $this->assign("cid", $this->contact["id"]);
        $this->assign("contact", $this->contact);
        $this->assign("currency", CRM_Sepa_Logic_Settings::defaultCreditor()->currency);
        $this->assign("is_enable_bic", CRM_Contract_Utils::isDefaultCreditorUsesBic());

        // Payment (payment_option)
        $this->add(
            "select",
            "payment_option",
            ts("Payment"),
            [
                "create" => ts("Create new payment"),
                "select" => ts("Select existing contribution"),
            ]
        );

        // Payment adapter (payment_adapter)
        $payment_adapter_options = [];

        foreach ($this->payment_adapters as $pa_name => $pa_class) {
            $payment_adapter_options[$pa_name] = $pa_class::adapterInfo()["display_name"];
        }

        $this->add(
            "select",
            "payment_adapter",
            ts("Payment adapter"),
            $payment_adapter_options
        );

        // Payment-adapter-specific fields
        $pa_form_template_var = [];

        foreach ($this->payment_adapters as $pa_name => $pa_class) {
            $pa_form_template_var[$pa_name] = [];

            $form_fields = $pa_class::formFields([
              "contact_id" => $this->contact["id"],
              "form"       => "sign",
            ]);

            foreach ($form_fields as $field) {
                if (!$field["enabled"]) continue;

                $field["id"] = "pa-$pa_name-" . $field["name"];

                array_push($pa_form_template_var[$pa_name], $field["id"]);

                CRM_Contract_FormUtils::addFormField($this, $field);
            }
        }

        $this->assign("payment_adapter_fields", $pa_form_template_var);

        // Recurring contribution (existing_recurring_contribution)
        $form_utils = new CRM_Contract_FormUtils($this, "Membership");

        $form_utils->addPaymentContractSelect2(
            "existing_recurring_contribution",
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

        // Member since (join_date)
        $this->add("datepicker", "join_date", ts("Member since"), [], true, [ "time" => false ]);

        // Membership start date (start_date)
        $this->add(
            "datepicker",
            "start_date",
            ts("Membership start date"),
            [],
            true,
            [ "time" => false ]
        );

        // End date (end_date)
        $this->add("datepicker", "end_date", ts("End date"), [], false, [ "time" => false ]);

        // Campaign (campaign_id)
        $this->add(
            "select",
            "campaign_id",
            ts("Campaign"),
            CRM_Contract_Configuration::getCampaignList(),
            false,
            [ "class" => "crm-select2" ]
        );

        // Membership type (membership_type_id)
        $membership_type_options = [ "" => "- none -" ] + $this->membership_types;

        $this->add(
            "select",
            "membership_type_id",
            ts("Membership type"),
            $membership_type_options,
            true,
            [ "class" => "crm-select2" ]
        );

        // Source media (activity_medium)
        $activity_medium_options = [ "" => "- none -" ] + $this->mediums;

        $this->add(
            "select",
            "activity_medium",
            ts("Source media"),
            $activity_medium_options,
            true,
            [ "class" => "crm-select2" ]
        );

        // Reference number (membership_reference)
        $this->add("text", "membership_reference", ts("Reference number"));

        // Contract number (membership_contract)
        $this->add("text", "membership_contract", ts("Contract number"));

        // DD-Fundraiser (membership_dialoger)
        $this->addEntityRef(
            "membership_dialoger",
            ts("DD-Fundraiser"),
            [
                "api" => [
                    "params" => [
                        "contact_type"     => "Individual",
                        "contact_sub_type" => "Dialoger",
                    ],
                ],
            ]
        );

        // Membership channel (membership_channel)
        $membership_channel_options = [ "" => "- none -" ] + $this->membership_channels;

        $this->add(
            "select",
            "membership_channel",
            ts("Membership channel"),
            $membership_channel_options,
            false,
            [ "class" => "crm-select2" ]
        );

        // Notes (activity_details)
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

    function validate () {
        $submitted = $this->exportValues();

        if ($submitted["payment_option"] === "create") {
            if (empty($submitted["amount"])) {
                HTML_QuickForm::setElementError ("amount", "Please specify a payment amount");
            }

            if (!preg_match('/^\d+((\.|,)\d+)?$/', $submitted["amount"])) {
                HTML_QuickForm::setElementError ("amount", "Please enter a valid payment amount");
            }

            if (empty($submitted["frequency"])) {
                HTML_QuickForm::setElementError ("frequency", "Please specify a payment frequency");
            }

            $pa_name = $submitted["payment_adapter"];
            $pa_class = $this->payment_adapters[$pa_name];
            $form_fields = $pa_class::formFields([ "form" => "sign" ]);

            foreach ($form_fields as $field_name => $field) {
                if (!$field["enabled"]) continue;

                $field_id = "pa-$pa_name-$field_name";
                $value = $submitted[$field_id];

                try {
                    if ($field["required"] && empty($value)) {
                        throw new Exception("This field is required");
                    }

                    if (empty($field["validate"])) continue;

                    call_user_func($field["validate"], $value, $field["required"]);
                } catch (Exception $exception) {
                    HTML_QuickForm::setElementError($field_id, $exception->getMessage());
                }
            }
        }

        if ($submitted["payment_option"] === "select") {
            if (empty($submitted["existing_recurring_contribution"])) {
                HTML_QuickForm::setElementError(
                    "existing_recurring_contribution",
                    ts("%1 is a required field", [ 1 => ts("Recurring Contribution") ])
                );
            }
        }

        $join_date = isset($submitted["join_date"]) ? strtotime($submitted["join_date"]) : null;
        $now = time();

        if (isset($join_date) && $join_date > $now) {
            HTML_QuickForm::setElementError("join_date", ts("Join date cannot be in the future"));
        }

        return parent::validate();
    }

    function setDefaults ($defaultValues = null, $filter = null) {
        $defaults = [];

        // Payment (payment_option)
        $defaults["payment_option"] = "create";

        // Payment adapter (payment_adapter)
        $defaults["payment_adapter"] = "sepa_mandate";

        // Payment frequency (frequency)
        $defaults["frequency"] = "12"; // monthly

        // Member since (join_date)
        $defaults["join_date"] = date("Y-m-d H:i:s");

        // Membership start date (start_date)
        $defaults["start_date"] = CRM_Contract_Utils::getDefaultContractChangeDate();

        // Payment-adapter-specific defaults
        foreach ($this->payment_adapters as $pa_name => $pa_class) {
            $form_fields = $pa_class::formFields([ "form" => "sign" ]);

            foreach ($form_fields as $field_name => $field) {
                if (!$field["enabled"] || empty($field["default"])) continue;

                $defaults["pa-$pa_name-$field_name"] = $field["default"];
            }
        }

        parent::setDefaults($defaults);
    }

    function postProcess () {
        $contract_params = [];
        $contact_id = $this->contact["id"];
        $submitted = $this->exportValues();

        if ($submitted["payment_option"] === "create") {
            $payment_adapter= $submitted["payment_adapter"];
            $pa_class = $this->payment_adapters[$payment_adapter];

            $payment_params = $pa_class::mapSubmittedFormValues("Contract.create", $submitted);

            $contract_params = array_merge($contract_params, $payment_params);
            $contract_params["payment_method.adapter"] = $payment_adapter;
            $contract_params["payment_method.contact_id"] = $contact_id;
            $contract_params["cycle_day"] = $submitted["cycle_day"];
        }

        if ($submitted["payment_option"] === "select") {
            $contract_params["membership_payment.membership_recurring_contribution"] = $submitted["existing_recurring_contribution"];
        }

        $contract_params["campaign_id"] = $submitted["campaign_id"];
        $contract_params["contact_id"] = $contact_id;

        $contract_params["start_date"] = CRM_Utils_Date::processDate(
            $submitted["start_date"],
            null,
            null,
            "Y-m-d H:i:s"
        );

        $contract_params["join_date"] = CRM_Utils_Date::processDate(
            $submitted["join_date"],
            null,
            null,
            "Y-m-d H:i:s"
        );

        if ($submitted["end_date"]) {
            $contract_params["end_date"] = CRM_Utils_Date::processDate(
                $submitted["end_date"],
                null,
                null,
                "Y-m-d H:i:s"
            );
        }

        $contract_params["membership_type_id"] = $submitted["membership_type_id"];
        $contract_params["membership_general.membership_reference"] = $submitted["membership_reference"];
        $contract_params["membership_general.membership_contract"] = $submitted["membership_contract"];
        $contract_params["membership_general.membership_dialoger"] = $submitted["membership_dialoger"];
        $contract_params["membership_general.membership_channel"] = $submitted["membership_channel"];

        $contract_params["medium_id"] = $submitted["activity_medium"];
        $contract_params["note"] = $submitted["activity_details"];

        civicrm_api3("Contract", "create", $contract_params);

        $this->controller->_destination = CRM_Utils_System::url(
            "civicrm/contact/view",
            "reset=1&cid=$contact_id"
        );
    }

}

?>
