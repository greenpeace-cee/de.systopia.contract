<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Handler_ModificationConflicts{

  private $scheduledModifications = [];
  private $contractId = NULL;

  function __construct(){
    $this->needsReviewStatusId = civicrm_api3('OptionValue', 'getvalue', [ 'return' => "value", 'option_group_id' => "activity_status", 'name' => 'Needs Review']);;
  }

  function checkForConflicts($contractId){
    if (empty($contractId)) {
      throw new Exception('Missing contract ID, cannot check for conflicts');
    }

    $this->contractId = $contractId;
    // Black and white listing happens as follows: an array of scheduled
    // modifications (in order of execution) is retreived by
    // getScheduledModifications.

    // Various functions can inspect the scheduled modifications, removing
    // any modifications (including combinations of modifications) that they
    // consider to be safe. Any modifications left once all the functions have
    // been run will be marked for review.

    $this->getScheduledModifications();

    $this->whitelistOneActivity();

    $this->whitelistPauseResume();

    if(count($this->scheduledModifications)) {
      foreach($this->scheduledModifications as $scheduledModification){
        if($scheduledModification['status_id'] != $this->needsReviewStatusId){
          $this->markForReview($scheduledModification['id']);
        }
      }
    }
  }

  function getScheduledModifications(){
    $scheduledModifications = civicrm_api3('activity', 'get', [
      'option.limit' => 10000, // If we have more than 10,000 scheduled updates for this contract, probably time to review organisational proceedures
      'source_record_id' => $this->contractId,
      'activity_type_id' => ['IN' => array_keys(CRM_Contract_Change::$type2class)],
      'status_id' => ['IN' => ['scheduled', 'needs review']]
    ])['values'];
    foreach($scheduledModifications as $k => &$scheduledModification){
      $scheduledModification['activity_date_unixtime'] = strtotime($scheduledModification['activity_date_time']);
    }
    usort($scheduledModifications, function($a, $b){
      return $a['activity_date_unixtime'] - $b['activity_date_unixtime'];
    });
    $this->scheduledModifications = $scheduledModifications;

  }

  /**
   * Mark a given activity as "Needs Review"
   */
  function markForReview($id) {
    $update_activity = [
      'id'           => $id,
      'status_id'    => 'Needs Review',
      'skip_handler' => true,
    ];

    // assign to reviewers
    $reviewers = civicrm_api3('Setting', 'GetValue', [
      'name' => 'contract_modification_reviewers',
      'group' => 'Contract preferences'
    ]);
    if ($reviewers) {
      $assignees = explode(',', $reviewers);
      if (!empty($assignees)) {
        $update_activity['assignee_id'] = $assignees;
      }
    }

    civicrm_api3('Activity', 'create', $update_activity);
  }

  function whitelistOneActivity(){
    if(count($this->scheduledModifications) == 1){
      // TODO we should perform extra validation here since we know what
      // the start state is, we can check that this would be a valid
      // modification and only whitelist it if so.
      $this->scheduledModifications = [];
    }
  }


  function whitelistPauseResume(){

    // This whitelist only works when there are exactly two activities
    if(count($this->scheduledModifications) != 2){
      return;
    }

    // If the first modification is a pause and that the second activity
    // is a resume, remove the scheduled modifications
    $pauseActivity  = current($this->scheduledModifications);
    $resumeActivity = next($this->scheduledModifications);

    if (
           $pauseActivity['activity_type_id']  == CRM_Contract_Change::getActivityIdForClass('CRM_Contract_Change_Pause')
        && $resumeActivity['activity_type_id'] == CRM_Contract_Change::getActivityIdForClass('CRM_Contract_Change_Resume')
    ){
      $this->scheduledModifications = [];
    }
  }
}
