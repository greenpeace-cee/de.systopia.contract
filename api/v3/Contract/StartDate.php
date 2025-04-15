<?php

use Civi\Api4;

function civicrm_api3_Contract_start_date($params) {
  try {
    _civicrm_api3_Contract_start_date_validate_params($params);

    $today = CRM_Utils_Array::value('_today', $params, 'now');
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

    $rc_id = CRM_Contract_RecurringContribution::getCurrentForContract($membership_id)['id'] ?? NULL;

    if (empty($rc_id)) {
      throw new API_Exception("No recurring contribution found for this contract");
    }
    $adapter = CRM_Contract_Utils::getPaymentAdapterForRecurringContribution($rc_id);
    $params['payment_adapter'] = $adapter;
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
}

function _civicrm_api3_Contract_start_date_spec(&$params) {
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
