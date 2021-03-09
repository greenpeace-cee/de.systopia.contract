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

    private static $payment_instruments = [
        "sepa_mandate" => "CRM_Contract_PaymentInstrument_SepaMandate",
    ];

    private $change_class;
    private $contact;
    private $mediums;
    private $membership_channels;
    private $membership_types;
    private $title;

    function preProcess () {

        // Change class
        $this->change_class = CRM_Contract_Change::getClassByAction("sign");

        // Title
        $this->title = $this->change_class::getChangeTitle();

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
        $this->mediums = civicrm_api3("Activity", "getoptions", [
            "field"   => "activity_medium_id",
            "options" => [
                "limit" => 0,
                "sort"  => "weight",
            ],
        ])["values"];

        // Membership channels
        $this->membership_channels = civicrm_api3("OptionValue", "get", [
            "is_active"       => 1,
            "option_group_id" => "contact_channel",
            "options"         => [
                "limit" => 0,
                "sort"  => "weight",
            ],
        ])["values"];

        // Membership types
        $this->membership_types = civicrm_api3("MembershipType", "get", [
            "options" => [
                "limit" => 0,
                "sort"  => "weight",
            ],
        ])["values"];

        // JS variables
        $resources = CRM_Core_Resources::singleton();

        $resources->addVars("de.systopia.contract", [
            "cid"                     => $this->contact["id"],
            "debitor_name"            => $this->contact["display_name"],
            "frequencies"             => CRM_Contract_RecurringContribution::getPaymentFrequencies(),
            "grace_end"               => NULL,
            "recurring_contributions" => CRM_Contract_RecurringContribution::getAllForContact($this->contact["id"]),
        ]);

        foreach (self::$payment_instruments as $pi_name => $pi_class) {
            $resources->addVars("de.systopia.contract/$pi_name", $pi_class::formVars());
        }

    }

    function buildQuickForm () {
        // Title
        CRM_Utils_System::setTitle($this->title);

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

        // Payment instrument (payment_instrument)
        $payment_instrument_options = [];

        foreach (self::$payment_instruments as $pi_name => $pi_class) {
            $payment_instrument_options[$pi_name] = $pi_class::displayName();
        }

        $this->add(
            "select",
            "payment_instrument",
            ts("Payment instrument"),
            $payment_instrument_options
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

        // Recurring contribution (existing_recurring_contribution)
        $formUtils = new CRM_Contract_FormUtils($this, "Membership");

        $formUtils->addPaymentContractSelect2(
            "existing_recurring_contribution",
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
        $membership_type_options = [ "" => "- none -" ];

        foreach($this->membership_types as $membership_type){
            $membership_type_options[$membership_type["id"]] = $membership_type["name"];
        }

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
        $membership_channel_options = [ "" => "- none -" ];

        foreach($this->membership_channels as $mc){
            $membership_channel_options[$mc["value"]] = $mc["label"];
        }

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

            if (empty($submitted["frequency"])) {
                HTML_QuickForm::setElementError ("frequency", "Please specify a payment frequency");
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
        }

        if ($submitted["payment_option"] === "select") {
            if (empty($submitted["recurring_contribution"])) {
                HTML_QuickForm::setElementError(
                    "recurring_contribution",
                    ts("%1 is a required field", [ 1 => ts("Recurring Contribution") ])
                );
            }
        }

        $join_date =
            isset($submitted["join_date"])
            ? CRM_Utils_Date::processDate($submitted["join_date"])
            : null;

        $now = CRM_Utils_Date::processDate(date("Ymd"));

        if (isset($join_date) && $join_date > $now) {
            HTML_QuickForm::setElementError("join_date", ts("Join date cannot be in the future"));
        }

        return parent::validate();
    }

    function setDefaults ($defaultValues = null, $filter = null) {
        $defaults = [];

        // Payment (payment_option)
        $defaults["payment_option"] = "create";

        // Payment frequency (frequency)
        $defaults["frequency"] = "12"; // monthly

        // Member since (join_date)
        $defaults["join_date"] = date("Y-m-d H:i:s");

        // Membership start date (start_date)
        $defaults["start_date"] = CRM_Contract_Utils::getDefaultContractChangeDate();

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

    function postProcess () {
        $contract_params = [];
        $contact_id = $this->contact["id"];
        $submitted = $this->exportValues();

        if ($submitted["payment_option"] === "create") {
            $payment_instrument = $submitted["payment_instrument"];
            $pi_class = self::$payment_instruments[$payment_instrument];

            $submitted["contact_id"] = $contact_id;

            $payment_params = $pi_class::mapSubmittedValues($submitted);
            $new_payment = $pi_class::create($payment_params);

            $entity_id = $new_payment->getParameters()["entity_id"];
            $contract_params["membership_payment.membership_recurring_contribution"] = $entity_id;
        }

        if ($submitted["payment_option" === "select"]) {
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
