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
  $now = new DateTimeImmutable(CRM_Utils_Array::value('_today', $params, 'now'));

  // Recurring contribution

  if (isset($params['recurring_contribution_id'])) {
    $rc_id = $params['recurring_contribution_id'];

    $rc_result = Api4\ContributionRecur::get()
      ->addWhere('id', '=', $rc_id)
      ->addSelect('*')
      ->setLimit(1)
      ->execute();

    if ($rc_result->count() < 1) {
      throw new API_Exception("Recurring contribution with ID $rc_id not found");
    }

    $rc = $rc_result->first();
    $adapter = CRM_Contract_Utils::getPaymentAdapterForRecurringContribution($rc_id);

    $params['cycle_day'] = CRM_Utils_Array::value('cycle_day', $params, $rc['cycle_day']);
    $params['payment_adapter'] = CRM_Utils_Array::value('payment_adapter', $params, $adapter);
    $params['start_date'] = CRM_Utils_Array::value('start_date', $params, $rc['start_date']);

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

  // Cycle day

  if (empty($params['cycle_day'])) unset($params['cycle_day']);

  // Start date

  $params['start_date'] = CRM_Utils_Array::value('start_date', $params, $now->format('Y-m-d'));
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
  $params['recurring_contribution_id'] = [
    'api.required' => FALSE,
    'description'  => 'ID of an existing recurring contribution',
    'name'         => 'recurring_contribution_id',
    'title'        => 'Recurring contribution ID',
    'type'         => CRM_Utils_Type::T_INT,
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
