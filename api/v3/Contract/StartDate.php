<?php

use Civi\Api4;

function civicrm_api3_Contract_start_date($params) {
  try {
    _civicrm_api3_Contract_start_date_validate_params($params);

    $today = CRM_Utils_Array::value('_today', $params);
    $payment_adapter = CRM_Contract_Utils::getPaymentAdapterClass($params['payment_adapter']);
    $start_date = $payment_adapter::startDate($params, $today);

    return civicrm_api3_create_success([$start_date->format('Y-m-d')]);
  } catch (Exception $e) {
    return civicrm_api3_create_error($e->getMessage());
  }
}

function _civicrm_api3_Contract_start_date_validate_params(&$params) {

  // Existing contract

  if (isset($params['membership_id'])) {
    $membership_id = $params['membership_id'];

    $membership_result = Api4\Membership::get(FALSE)
      ->addWhere('id', '=', $membership_id)
      ->addSelect('*')
      ->setLimit(1)
      ->execute();

    if ($membership_result->count() < 1) {
      throw new API_Exception("Membership with ID $membership_id not found");
    }

    $membership = $membership_result->first();

    $payment_link = civicrm_api3('ContractPaymentLink', 'get', [
      'contract_id' => $membership_id,
      'is_active'   => TRUE,
      'sequential'  => TRUE,
    ]);

    if ($payment_link['count'] < 1) {
      throw new API_Exception("No recurring contribution found for this contract");
    }

    $rc_id = $payment_link['values'][0]['contribution_recur_id'];
    $adapter = CRM_Contract_Utils::getPaymentAdapterForRecurringContribution($rc_id);
    $params['payment_adapter'] = $adapter;

    if ($adapter === 'psp_sepa' && empty($params['creditor_id'])) {
      $sepa_mandate = Api4\SepaMandate::get()
        ->addWhere('entity_id'   , '=', $rc_id)
        ->addWhere('entity_table', '=', 'civicrm_contribution_recur')
        ->addSelect('creditor_id')
        ->setLimit(1)
        ->execute()
        ->first();

      $params['creditor_id'] = $sepa_mandate['creditor_id'];
    }
  }

  // Defer payment start

  if ($params['defer_payment_start'] && empty($params['membership_id'])) {
    throw new API_Exception("Missing parameter 'membership_id'");
  }

  // Payment adapter

  $adapter = CRM_Utils_Array::value('payment_adapter', $params);
  $params['payment_adapter'] = CRM_Contract_Utils::resolvePaymentAdapterAlias($adapter);

  if (empty($params['payment_adapter'])) {
    throw new API_Exception("Missing parameter 'payment_adapter'");
  }

  // PSP creditor ID

  if ($params['payment_adapter'] === 'psp_sepa') {
    if (empty($params['creditor_id'])) {
      throw new Exception("Missing parameter 'creditor_id'");
    }

    $creditor_id = $params['creditor_id'];

    $creditor_count = Api4\SepaCreditor::get()
      ->selectRowCount()
      ->addWhere('id', '=', $creditor_id)
      ->execute()
      ->rowCount;

    if ($creditor_count < 1) {
      throw new Exception("PSP creditor with ID $creditor_id not found");
    }
  }

}

function _civicrm_api3_Contract_start_date_spec(&$params) {
  $params['creditor_id'] = [
    'api.default'  => NULL,
    'api.required' => FALSE,
    'description'  => 'ID of the PSP creditor',
    'name'         => 'creditor_id',
    'title'        => 'Creditor ID (PSP)',
    'type'         => CRM_Utils_Type::T_INT,
  ];
  $params['cycle_day'] = [
    'api.default'  => NULL,
    'api.required' => FALSE,
    'description'  => 'Expected day of the month',
    'name'         => 'cycle_day',
    'title'        => 'Cycle day',
    'type'         => CRM_Utils_Type::T_INT,
  ];
  $params['defer_payment_start'] = [
    'api.default'  => FALSE,
    'api.required' => FALSE,
    'description'  => 'Defer the start date based on existing contributions',
    'name'         => 'defer_payment_start',
    'title'        => 'Defer payment start',
    'type'         => CRM_Utils_Type::T_BOOLEAN,
  ];
  $params['membership_id'] = [
    'api.default'  => NULL,
    'api.required' => FALSE,
    'description'  => 'ID of an existing membership (contract)',
    'name'         => 'membership_id',
    'title'        => 'Membership ID',
    'type'         => CRM_Utils_Type::T_INT,
  ];
  $params['min_date'] = [
    'api.default'  => NULL,
    'api.required' => FALSE,
    'description'  => 'Used as an offset to calculate the result date from',
    'name'         => 'min_date',
    'title'        => 'Minimum date',
    'type'         => CRM_Utils_Type::T_DATE,
  ];
  $params['payment_adapter'] = [
    'api.default'  => NULL,
    'api.required' => FALSE,
    'description'  => 'Payment adapter for the contract',
    'name'         => 'payment_adapter',
    'title'        => 'Payment adapter',
    'type'         => CRM_Utils_Type::T_STRING,
  ];
}

?>
