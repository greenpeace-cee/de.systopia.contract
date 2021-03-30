<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


/**
 * A wrapper around Membership.create with appropriate fields passed.
 * You cannot schedule Contract.create for the future.
 */
function _civicrm_api3_Contract_create_spec(&$params) {
  include_once 'api/v3/Membership.php';
  _civicrm_api3_membership_create_spec($params);
}

/**
 * A wrapper around Membership.create with appropriate fields passed.
 * You cannot schedule Contract.create for the future.
 */
function civicrm_api3_Contract_create($params){
  // Any parameters with a period in will be converted to the custom_N format
  // Other fields will be passed directly to the membership.create API
  foreach ($params as $key => $value){
    if(strpos($key, '.')){
      unset($params[$key]);
      $params[CRM_Contract_Utils::getCustomFieldId($key)] = $value;
    }
  }

  // create
  $membership = civicrm_api3('Membership', 'create', $params);

  // link SEPA Mandate
  $recurring_contribution_field_key = CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution');
  if (!empty($params[$recurring_contribution_field_key])) {
    // link recurring contribution to contract
    CRM_Contract_BAO_ContractPaymentLink::setContractPaymentLink(
      $membership['id'],
      $params[$recurring_contribution_field_key]
    );
  }

  if (!empty($params['note'])) {
    $params['details'] = $params['note'];
  }

  // create 'sign' activity
  $params['activity_type_id'] = 'sign';
  $change = CRM_Contract_Change::getChangeForData($params);
  $change->setParameter('source_contact_id', CRM_Contract_Configuration::getUserID());
  $change->setParameter('source_record_id',  $membership['id']);
  $change->setParameter('target_contact_id', $change->getContract()['contact_id']);
  $change->setStatus('Completed');
  $change->populateData();
  $change->verifyData();
  $change->shouldBeAccepted();
  $change->save();

  // also derive contract fields
  $change->updateContract(['membership_payment.membership_recurring_contribution' => $params[$recurring_contribution_field_key]]);

  // maybe we need to do some cleanup:
  CRM_Contract_Utils::deleteSystemActivities($membership['id']);

  return $membership;
}

