<?php
use CRM_Contract_ExtensionUtil as E;

/**
 * Contract.SynchronizeContributionRecur API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_contract_synchronize_contribution_recur_spec(&$spec) {
  $spec['id'] = [
    'name'         => 'id',
    'title'        => 'Contract ID',
    'api.aliases'  => ['contract_id'],
    'api.required' => 1,
    'description'  => 'Contract (Membership) ID of the contract to be synchronized',
  ];
}

/**
 * Refresh contract details based on recurring contribution data
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_contract_synchronize_contribution_recur($params) {
  // get membership and ensure ContributionRecur is set
  $rcurId = civicrm_api3('Membership', 'getvalue', [
    'id'     => $params['id'],
    'return' => CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution'),
  ]);
  $sync = new CRM_Contract_Change_SynchronizeContributionRecur([
    'source_record_id' => $params['id'],
  ]);
  $sync->execute();
  return civicrm_api3_create_success(civicrm_api3('Membership', 'getsingle', [
    'id'     => $params['id'],
  ]));
}
