<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

const CE_ENGINE_PROCESSING_LIMIT = 500;

/**
 * Process the scheduled contract modifications
 */
function _civicrm_api3_Contract_process_scheduled_modifications_spec(&$params){
  $params['id'] = [
    'name'         => 'id',
    'title'        => 'Contract ID',
    'api.required' => 0,
    'description'  => 'If given, only pending modifications for this contract will be processed',
    ];
  $params['now'] = [
    'name'         => 'now',
    'title'        => 'NOW Time',
    'api.required' => 0,
    'description'  => 'You can provide another datetime for what the algorithm considers to be now',
    ];
  $params['limit'] = [
    'name'         => 'limit',
    'title'        => 'Limit',
    'api.default'  => CE_ENGINE_PROCESSING_LIMIT,
    'description'  => 'Max count of modifications to be processed',
    ];
}


/**
 * Process the scheduled contract modifications
 */
function civicrm_api3_Contract_process_scheduled_modifications($params) {
  // make sure no other task is running
  /** @var $lock Civi\Core\Lock\LockInterface */
  $lock = Civi\Core\Container::singleton()->get('lockManager')->acquire("worker.member.contract_engine");
  if (!$lock->isAcquired()) {
    return civicrm_api3_create_success(array('message' => "Another instance of the Contract.process_scheduled_modifications process is running. Skipped."));
  }

  // make sure that the time machine only works with a single contract, see GP-936
  if (isset($params['now']) && empty($params['id'])) {
    return civicrm_api3_create_error("You can only use the time machine for specific contract! set the 'id' parameter.");
  }

  if (empty($params['limit'])) {
    $params['limit'] = CE_ENGINE_PROCESSING_LIMIT;
  }

  // compile query
  $activityParams = [
      'activity_type_id'   => ['IN' => CRM_Contract_Change::getActivityTypeIds()],
      'status_id'          => 'scheduled',
      'activity_date_time' => ['<=' => date('Y-m-d H:i:s', strtotime(CRM_Utils_Array::value('now', $params, 'now')))], // execute everything scheduled in the past
      'option.limit'       => $params['limit'],
      'sequential'         => 1, // in the scheduled order(!)
      'option.sort'        => 'activity_date_time ASC, id ASC',
      'return'             => 'id,activity_type_id,status_id,activity_date_time,subject,source_record_id,' . CRM_Contract_Change::getCustomFieldList(),
  ];
  if (!empty($params['id'])) {
    $activityParams['source_record_id'] = (int) $params['id'];
  }

  // run query
  $result  = [];
  $counter = 0;
  $scheduled_activities = civicrm_api3('Activity', 'get', $activityParams);
  foreach ($scheduled_activities['values'] as $scheduled_activity) {
    CRM_Contract_Utils::stripNonContractActivityCustomFields($scheduled_activity);
    $counter++;
    if ($counter > $params['limit']) {
      break;
    }

    // execute the changes
    $change = CRM_Contract_Change::getChangeForData($scheduled_activity);
    $result['order'][] = $change->getID();
    try {
      // verify the data before execution
      $change->populateData();
      $change->verifyData();
      $change->verifyStatusChange();
    } catch (Exception $ex) {
      // verification failed
      $result['failed'][] = $change->getID();
      $result['error_details'][$change->getID()] = CRM_Contract_Utils::formatExceptionForApi($ex);
      $change->setStatus('Failed');
      $change->setParameter('details', CRM_Contract_Utils::formatExceptionForActivityDetails($ex));
      $change->save();
      continue;
    }

    try {
      // execute
      CRM_Contract_Configuration::disableMonitoring();
      $change->execute();

      // log as executed
      $result['completed'][] = $change->getID();

      // maybe we need to do some cleanup:
      CRM_Contract_Utils::deleteSystemActivities($change->getContractID());

      // check for new conflicts
      $change->checkForConflicts();

    } catch (Exception $ex) {
      // something went wrong...
      $result['failed'][] = $change->getID();
      $result['error_details'][$change->getID()] = CRM_Contract_Utils::formatExceptionForApi($ex);
      $change->setStatus('Failed');
      $change->setParameter('details', CRM_Contract_Utils::formatExceptionForActivityDetails($ex));
      $change->save();
    } finally {
      CRM_Contract_Configuration::enableMonitoring();
    }
  }

  $lock->release();
  return civicrm_api3_create_success($result);
}
