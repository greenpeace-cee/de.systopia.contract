<?php

use Civi\Api4;

function civicrm_api3_Contract_next_contribution_date($params) {
  try {
    _civicrm_api3_Contract_next_contribution_date_validate_params($params);

    $today = CRM_Utils_Array::value('_today', $params);
    $payment_adapter = CRM_Contract_Utils::getPaymentAdapterClass($params['payment_adapter']);
    $next_contribution_date = $payment_adapter::nextContributionDate($params, $today);

    return civicrm_api3_create_success([$next_contribution_date]);
  } catch (Exception $e) {
    return civicrm_api3_create_error($e->getMessage());
  }
}

function _civicrm_api3_Contract_next_contribution_date_validate_params(&$params) {
  $now = strtotime(CRM_Utils_Array::value('_today', $params, 'now'));

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

  // Start date

  $params['start_date'] = CRM_Utils_Array::value('start_date', $params, date('Y-m-d', $now));
}

function _civicrm_api3_Contract_next_contribution_date_spec(&$params) {
  $params['creditor_id'] = [
    'api.required' => FALSE,
    'description'  => 'ID of the PSP creditor',
    'name'         => 'creditor_id',
    'title'        => 'Creditor ID (PSP)',
    'type'         => CRM_Utils_Type::T_INT,
  ];
  $params['cycle_day'] = [
    'api.required' => FALSE,
    'description'  => 'Expected day of the month',
    'name'         => 'cycle_day',
    'title'        => 'Cycle day',
    'type'         => CRM_Utils_Type::T_INT,
  ];
  $params['payment_adapter'] = [
    'api.required' => FALSE,
    'description'  => 'Payment adapter for the contract',
    'name'         => 'payment_adapter',
    'title'        => 'Payment adapter',
    'type'         => CRM_Utils_Type::T_STRING,
  ];
  $params['start_date'] = [
    'api.required' => FALSE,
    'description'  => 'Start date of the recurring contribution',
    'name'         => 'start_date',
    'title'        => 'Start date',
    'type'         => CRM_Utils_Type::T_DATE,
  ];
}

?>
