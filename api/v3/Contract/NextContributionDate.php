<?php

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

  // Start date

  $params['start_date'] = CRM_Utils_Array::value('start_date', $params, date('Y-m-d', $now));

  // Cycle day

  $default_cycle_day = date('d', strtotime($params['start_date']));
  $params['cycle_day'] = (int) CRM_Utils_Array::value('cycle_day', $params, $default_cycle_day);
}

function _civicrm_api3_Contract_next_contribution_date_spec(&$params) {
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
