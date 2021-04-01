<?php

class CRM_Contract_Form_Cancel extends CRM_Core_Form {
  function preProcess () {
    $contract_id = CRM_Utils_Request::retrieve("id", "Integer");

    if (isset($contract_id)) {
      $this->set("id", $contract_id);
    } else {
      CRM_Core_Error::fatal("Missing contract ID");
    }
  }

  function buildQuickForm () {

    // Cancellation reason (cancel_reason)
    $options_result = civicrm_api3("OptionValue", "get", [
      "option_group_id" => "contract_cancel_reason",
      "filter"          => 0,
      "is_active"       => 1,
      "options"         => [
        "limit" => 0,
        "sort" => "weight",
      ],
    ]);

    $cancel_reason_options = [ "" => "- none -" ];

    foreach ($options_result["values"] as $reason) {
      $cancel_reason_options[$reason["value"]] = $reason["label"];
    }

    $this->add(
      "select",
      "cancel_reason",
      ts("Cancellation reason"),
      $cancel_reason_options,
      true,
      [ "class" => "crm-select2 huge" ]
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
    $options_result = civicrm_api3("Activity", "getoptions", [
      "field" => "activity_medium_id",
      "options" => [
        "limit" => 0,
        "sort" => "weight",
      ],
    ]);

    $medium_id_options = [ "" => ts("- none -") ] + $options_result["values"];

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
      $this->addWysiwyg("notes", ts("Notes"), []);
    } else {
      $this->add("wysiwyg", "notes", ts("Notes"));
    }

    // Cancel / Submit buttons
    $this->addButtons([
      [
        "type" => "cancel",
        "name" => ts("Discard changes"),
        "submitOnce" => true,
      ],
      [
        "type" => "submit",
        "name" => ts("Cancel"),
        "isDefault" => true,
        "submitOnce" => true,
      ],
    ]);

    $this->setDefaults();
  }

  function setDefaults($defaultValues = null, $filter = null){
    $now = time();

    $defaults = [
      "activity_date" => date("Y-m-d", $now),
      "activity_date_time" => date("H:i:00", $now),
    ];

    $this->assign("default_to_minimum_change_date", false);
    $minimum_change_date = Civi::settings()->get("contract_minimum_change_date");
    $default_activity_date_time = $defaults['activity_date'] . " " . $defaults['activity_date_time'];

    if (
      isset($minimum_change_date)
      && strtotime($default_activity_date_time) < strtotime($minimum_change_date)
    ) {
      $this->assign("default_to_minimum_change_date", true);

      $defaults = [
        "activity_date" => date("Y-m-d", strtotime($minimum_change_date)),
        "activity_date_time" => date("H:i:00", strtotime($minimum_change_date)),
      ];
    }

    parent::setDefaults($defaults);
  }

  function validate () {
    $submitted = $this->exportValues();

    // Check that the scheduled date is after the minimum change date for contracts
    if (isset($submitted["activity_date"]) && isset($submitted["activity_date_time"])) {
      $activity_date = CRM_Utils_Date::processDate(
        $submitted["activity_date"],
        $submitted["activity_date_time"]
      );
    }

    $minimum_change_date = Civi::settings()->get("contract_minimum_change_date");

    if (
      isset($activity_date)
      && isset($minimum_change_date)
      && strtotime($activity_date) < strtotime($minimum_change_date)
    ) {
      $is_valid = false;
      $formatted_mcd = date("j M Y h:i a", strtotime($minimum_change_date));

      HTML_QuickForm::setElementError(
        "activity_date",
        "Activity date must be after the minimum change date $formatted_mcd"
      );
    }

    return parent::validate();
  }

  function postProcess () {
    $contract_id = $this->get("id");
    $submitted = $this->exportValues();

    $modify_params = [
      "action"                                           => "cancel",
      "id"                                               => $contract_id,
      "medium_id"                                        => $submitted["medium_id"],
      "membership_cancellation.membership_cancel_reason" => $submitted["cancel_reason"],
      "note"                                             => $submitted["note"],
    ];

    if(isset($submitted["activity_date"])){
      $modify_params["date"] = CRM_Utils_Date::processDate(
        $submitted["activity_date"],
        $submitted["activity_date_time"],
        false,
        "Y-m-d H:i:s"
      );
    }

    civicrm_api3("Contract", "modify", $modify_params);

    civicrm_api3("Contract", "process_scheduled_modifications", [
      "id" => $contract_id,
    ]);
  }
}

?>
