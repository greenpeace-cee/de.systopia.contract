<?php

use Civi\Api4;

class CRM_Contract_Form_Pause extends CRM_Core_Form {
  function preProcess () {
    $contract_id = CRM_Utils_Request::retrieve("id", "Integer");

    if (isset($contract_id)) {
      $this->set("id", $contract_id);
    } else {
      CRM_Core_Error::fatal("Missing contract ID");
    }

    $membership = Api4\Membership::get(FALSE)
      ->addSelect("DATE(rc.next_sched_contribution_date) AS next_sched_contribution_date")
      ->addJoin(
        "ContributionRecur AS rc",
        "INNER",
        ["membership_payment.membership_recurring_contribution", "=", "rc.id"]
      )
      ->addWhere("id", "=", $contract_id)
      ->execute()
      ->first();

    $this->assign("next_sched_contribution_date", $membership["next_sched_contribution_date"]);

    $resources = CRM_Core_Resources::singleton();

    $resources->addVars("de.systopia.contract", [
      "ext_base_url"                 => rtrim($resources->getUrl("de.systopia.contract"), "/"),
      "next_sched_contribution_date" => $membership["next_sched_contribution_date"],
    ]);
  }

  function buildQuickForm () {

    // Resume date (resume_date)
    $this->add(
      "datepicker",       // $type
      "resume_date",      // $name
      ts("Resume date"),  // $label
      [],                 // $attributes
      true,               // $required
      [ "time" => false ] // $extra
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

    // Notes (note)
    if (version_compare(CRM_Utils_System::version(), "4.7", "<")) {
      $this->addWysiwyg("note", ts("Notes"), []);
    } else {
      $this->add("wysiwyg", "note", ts("Notes"));
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
        "name" => ts("Pause"),
        "isDefault" => true,
        "submitOnce" => true,
      ],
    ]);

    $this->setDefaults();
  }

  function setDefaults($defaultValues = null, $filter = null){
    $tomorrow = strtotime('+1 day');

    $defaults = [
      "activity_date" => date("Y-m-d", $tomorrow),
      "activity_date_time" => date("H:i:s", $tomorrow),
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
      "action"    => "pause",
      "id"        => $contract_id,
      "medium_id" => $submitted["medium_id"],
      "note"      => $submitted["note"],
    ];

    $modify_params["resume_date"] = CRM_Utils_Date::processDate(
      $submitted["resume_date"], // $date
      null,                      // $time
      false,                     // $returnNullString
      "Y-m-d"                    // $format
    );

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
