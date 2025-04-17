<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


/**
 * Schedule a Contract modification
 */
function _civicrm_api3_Contract_modify_spec(&$params){
  $params['modify_action'] = array(
    'name'         => 'modify_action',
    'title'        => 'Action',
    'api.required' => 1,
    'api.aliases'  => ['action'],
    'description'  => 'Action to be executed (same as "action")',
    );
  $params['id'] = array(
    'name'         => 'id',
    'title'        => 'Contract ID',
    'api.aliases'  => ['contract_id'],
    'api.required' => 1,
    'description'  => 'Contract (Membership) ID of the contract to be modified',
    );
  $params['medium_id'] = array(
      'name'         => 'medium_id',
      'title'        => 'Medium ID',
      'api.required' => 1,
      'description'  => 'How was the modification received',
  );
  $params['date'] = array(
      'name'         => 'date',
      'title'        => 'Date',
      'api.default'  => 'now',
      'description'  => 'Scheduled execution date (not in the past, and in format Y-m-d H:i:s)',
  );
}


/**
 * Schedule a new Contract modification
 */
function civicrm_api3_Contract_modify($params) {
  $minimumChangeDate = Civi::settings()->get("contract_minimum_change_date");

  // Pick a valid default date if none is provided
  if (empty($params['date'])) {
    $params['date'] =
      isset($minimumChangeDate)
      ? date("Y-m-d H:i:s", max(strtotime($minimumChangeDate), time()))
      : "now";
  }

  // use activity_type_id instead of modify_action
  $params['action'] = $params['modify_action'];

  // also: revert REST-like '.' -> '_' conversion
  foreach (array_keys($params) as $key) {
    $new_key = preg_replace('#^membership_payment_#', 'membership_payment.', $key);
    $new_key = preg_replace('#^membership_cancellation_#', 'membership_cancellation.', $new_key);
    $params[$new_key] = $params[$key];
  }

  // check the requested execution time
  $requested_execution_time = strtotime($params['date']);

  if (!empty($minimumChangeDate) && $requested_execution_time < strtotime($minimumChangeDate)) {
    throw new Exception("Parameter 'date' must be after the minimum change date for contracts.");
  }

  if ($requested_execution_time < strtotime('today')) {
    throw new Exception("Parameter 'date' must either be in the future, or absent if you want to execute the modification immediately.");
  }

  // modify data to match internal structure
  $params['activity_type_id']   = $params['action'];
  $params['activity_date_time'] = date('Y-m-d H:i:s', $requested_execution_time);
  $params['source_record_id']   = $params['id'];
  unset($params['id']);
  if (!empty($params['note'])) {
    $params['details'] = $params['note'];
  }


  // generate change (activity)
  $change = CRM_Contract_Change::getChangeForData($params);
  $change->setParameter('source_contact_id', $params['source_contact_id'] ?? CRM_Contract_Configuration::getUserID());
  $change->setParameter('target_contact_id', $change->getContract()['contact_id']);
  $change->setStatus('Scheduled');
  $change->populateData();
  $change->verifyData();
  $change->shouldBeAccepted();
  $change->save();

  // make sure any newly created conflicts are marked
  CRM_Contract_Handler_ModificationConflicts::checkForConflicts($change->getContractID());

  // return contract (legacy behaviour)
  $contract = $change->getContract();
  $contract['change_activity_id'] = $change->getID();
  return civicrm_api3_create_success([$contract['id'] => $contract]);
}
