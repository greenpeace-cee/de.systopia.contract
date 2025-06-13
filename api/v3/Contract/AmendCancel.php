<?php

use Civi\Api4;

function _civicrm_api3_Contract_amend_cancel_spec(&$params) {
  $params['activity_id'] = [
    'name'         => 'activity_id',
    'title'        => 'Cancel Activity ID',
    'api.required' => TRUE,
    'description'  => 'Cancel activity ID that should be amended',
    'type'         => CRM_Utils_Type::T_INT,
  ];
  $params['cancel_reason'] = [
    'name'         => 'cancel_reason',
    'title'        => 'Cancel Reason',
    'api.required' => FALSE,
    'description'  => 'New cancel reason',
    'type'         => CRM_Utils_Type::T_STRING,
  ];
  $params['cancel_tags'] = array(
    'name'         => 'cancel_tags',
    'title'        => 'Cancel Tags',
    'api.required' => FALSE,
    'description'  => 'Array of new cancel tag names, replacing the existing ones',
    'type'         => CRM_Utils_Type::T_STRING,
  );
  $params['medium_id'] = array(
    'name'         => 'medium_id',
    'title'        => 'Medium ID',
    'api.required' => FALSE,
    'description'  => 'New medium ID',
    'type'         => CRM_Utils_Type::T_INT,
  );
  $params['details'] = array(
    'name'         => 'details',
    'title'        => 'Details',
    'api.required' => FALSE,
    'description'  => 'New cancel details',
    'type'         => CRM_Utils_Type::T_STRING,
  );
}

function civicrm_api3_Contract_amend_cancel($params) {
  $activity = Api4\Activity::get(FALSE)
    ->addSelect(
      'activity_type_id',
      'medium_id',
      'details',
      'source_record_id',
      'contract_cancellation.contact_history_cancel_reason',
      'GROUP_CONCAT(entity_tag.tag_id) AS cancel_tags'
    )
    ->addJoin(
      'EntityTag AS entity_tag',
      'LEFT',
      ['entity_tag.entity_id', '=', 'id'],
      ['entity_tag.entity_table', '=', '"civicrm_activity"']
    )
    ->addWhere('id', '=', $params['activity_id'])
    ->addWhere('activity_type_id:name', '=', 'Contract_Cancelled')
    ->addGroupBy('id')
    ->addGroupBy('contract_cancellation.contact_history_cancel_reason')
    ->execute()
    ->first();
  if (empty($activity['id'])) {
    return civicrm_api3_create_error('Unable to find cancel activity with provided activity_id');
  }

  $membership = Api4\Membership::get(FALSE)
    ->addSelect(
      'status_id:name',
      'ABS(activity.id) AS most_recent_activity_id',
      'ABS(membership_payment.membership_recurring_contribution) AS recurring_contribution_id'
    )
    ->addWhere('id', '=', $activity['source_record_id'])
    ->addJoin(
      'Activity AS activity',
      'LEFT',
      ['activity.source_record_id', '=', 'id'],
      [
        'activity.activity_type_id:name',
        'IN',
        array_keys(CRM_Contract_Change::$type2class)
      ]
    )
    ->addOrderBy('activity.activity_date_time', 'DESC')
    ->addOrderBy('activity.id', 'DESC')
    ->setLimit(1)
    ->execute()
    ->first();


  // Create an instance of CRM_Contract_Change_Cancel to render its default activity subject
  $change = CRM_Contract_Change::getChangeForData($activity);

  // Prepare cancel activity update
  $update = Api4\Activity::update(FALSE)
    ->addWhere('id', '=', $params['activity_id']);

  // special handling for optional params: only set if param is provided
  // this allows existing values to remain unchanged while making it possible
  // to clear value by explicitly providing NULL

  if (array_key_exists('cancel_reason', $params)) {
    if (empty($params['cancel_reason'])) {
      return civicrm_api3_create_error('cancel reason cannot be empty, skip parameter to keep it unchanged');
    }
    $change->setParameter('contract_cancellation.contact_history_cancel_reason', $params['cancel_reason']);
    $update->addValue('contract_cancellation.contact_history_cancel_reason', $params['cancel_reason']);
  }
  if (array_key_exists('details', $params)) {
    $update->addValue('details', $params['details']);
  }
  if (array_key_exists('medium_id', $params)) {
    $update->addValue('medium_id', $params['medium_id']);
  }

  if (array_key_exists('cancel_tags', $params)) {
    if (empty($params['cancel_tags'])) {
      // convert any empty-ish values to empty array
      $params['cancel_tags'] = [];
    }
    if (!is_array($params['cancel_tags'])) {
      return civicrm_api3_create_error('Expected array of tag names or empty value for cancel tags');
    }
    // First, remove all existing cancellation tags
    Api4\EntityTag::delete(FALSE)
      ->addWhere('entity_table', '=', 'civicrm_activity')
      ->addWhere('entity_id', '=', $params['activity_id'])
      ->addWhere('tag_id', 'IN', $activity['cancel_tags'])
      ->execute();

    // Then link all provided cancellation tags
    foreach ($params['cancel_tags'] as $tag_name) {
      Api4\EntityTag::create(FALSE)
        ->addValue('entity_table', 'civicrm_activity')
        ->addValue('entity_id', $activity['id'])
        ->addValue('tag_id:name', $tag_name)
        ->execute();
    }
  }

  $update->addValue('subject', $change->renderDefaultSubject(NULL));
  $update->execute();

  if (array_key_exists('cancel_reason', $params)
    && $activity['id'] === $membership['most_recent_activity_id']
    && $membership['status_id:name'] === 'Cancelled') {
    // cancel activity that is being modified is most recent contract activity and membership is still cancelled
    // => update Membership/ContributionRecur cancel reason
    Api4\Membership::update(FALSE)
      ->addValue('membership_cancellation.membership_cancel_reason', $params['cancel_reason'])
      ->addValue('status_id:name', 'Cancelled')
      ->addWhere('id', '=', $membership['id'])
      ->execute();

    Api4\ContributionRecur::update(FALSE)
      ->addValue('cancel_reason', $params['cancel_reason'])
      ->addWhere('id', '=', $membership['recurring_contribution_id'])
      ->execute();
  }

  return civicrm_api3_create_success();
}
