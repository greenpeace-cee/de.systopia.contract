<?php

use Civi\Api4;

/**
 * @group headless
 */
class api_v3_Contract_AmendCancelTest extends api_v3_Contract_ContractTestBase {

  public function testSEPA() {
    $today = new DateTimeImmutable();
    $tomorrow = new DateTimeImmutable('tomorrow');

    // Create SEPA contract
    $encounter_medium = self::getOptionValue('encounter_medium', 'in_person');
    $financial_type = self::getFinancialTypeID('Member Dues');
    $membership_type = self::getMembershipTypeID('General');
    $start_date = CRM_Contract_DateHelper::findNextOfDays([1], $tomorrow->format('Y-m-d'));

    $contract_create_result = $this->callAPISuccess('Contract', 'create', [
      'contact_id'                        => $this->contact['id'],
      'join_date'                         => $today->format('Y-m-d'),
      'medium_id'                         => $encounter_medium,
      'membership_type_id'                => $membership_type,
      'payment_method.adapter'            => 'sepa_mandate',
      'payment_method.amount'             => 10.0,
      'payment_method.bic'                => 'BKAUATWWXXX',
      'payment_method.contact_id'         => $this->contact['id'],
      'payment_method.financial_type_id'  => $financial_type,
      'payment_method.frequency_interval' => 1,
      'payment_method.frequency_unit'     => 'month',
      'payment_method.iban'               => 'AT340000000012345678',
      'payment_method.type'               => 'RCUR',
      'start_date'                        => $start_date->format('Y-m-d'),
    ]);

    // Check contract status
    $membership = self::getMembershipByID($contract_create_result['id']);
    $this->assertEquals('Current', $membership['status_id:name']);

    $recurring_contrib = self::getActiveRecurringContribution($membership['id']);
    $this->assertEquals('Pending', $recurring_contrib['contribution_status_id:name']);

    $sepa_mandate = Api4\SepaMandate::get(FALSE)
      ->addWhere('entity_id'   , '=', $recurring_contrib['id'])
      ->addWhere('entity_table', '=', 'civicrm_contribution_recur')
      ->addSelect('id', 'status')
      ->setLimit(1)
      ->execute()
      ->first();

    $this->assertEquals('FRST', $sepa_mandate['status']);

    // Cancel contract
    $cancel_reason = self::getOptionValue('contract_cancel_reason', 'rdn_insufficient_funds');

    $contract_cancel_result = $this->callAPISuccess('Contract', 'modify', [
      'modify_action'                                    => 'cancel',
      'id'                                               => $membership['id'],
      'date'                                             => 'now',
      'medium_id'                                        => $encounter_medium,
      'membership_cancellation.cancel_tags'              => ['cancel_tag_1', 'cancel_tag_2', 'cancel_tag_3'],
      'membership_cancellation.membership_cancel_reason' => $cancel_reason,
    ]);

    $cancel_activity_id = reset($contract_cancel_result['values'])['change_activity_id'];

    $this->callAPISuccess('Contract', 'process_scheduled_modifications');

    // Check contract status
    $updated_membership = $this->getMembershipByID($membership['id']);

    $this->assertEquals('Cancelled', $updated_membership['status_id:name'], 'Membership should be cancelled');
    $this->assertEquals($cancel_reason, $updated_membership['membership_cancellation.membership_cancel_reason'], 'Cancellation reason should be "RDN: Insufficient Funds"');

    $updated_rc_status = Api4\ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $recurring_contrib['id'])
      ->addSelect('contribution_status_id:name')
      ->execute()
      ->first()['contribution_status_id:name'];

    $this->assertEquals('Completed', $updated_rc_status, 'Recurring contribution should be completed');

    $updated_mandate_status = Api4\SepaMandate::get(FALSE)
      ->addWhere('id', '=', $sepa_mandate['id'])
      ->addSelect('status')
      ->execute()
      ->first()['status'];

    $this->assertEquals('COMPLETE', $updated_mandate_status);

    $this->assertEquals(['cancel_tag_1', 'cancel_tag_2', 'cancel_tag_3'], $this->getCancelTagsForCancelActivity($cancel_activity_id));

    $new_cancel_reason = self::getOptionValue('contract_cancel_reason', 'cancellation_via_bank');
    $new_medium = self::getOptionValue('encounter_medium', 'phone');
    $new_details = 'Amended';
    $new_cancel_tags = ['cancel_tag_3'];
    $this->callAPISuccess('Contract', 'amend_cancel', [
      'activity_id' => $cancel_activity_id,
      'cancel_reason' => $new_cancel_reason,
      'medium_id' => $new_medium,
      'details' => $new_details,
      'cancel_tags' => $new_cancel_tags,
    ]);
    $this->assertEquals(
      $new_cancel_tags,
      $this->getCancelTagsForCancelActivity($cancel_activity_id),
      'cancel tags should have been updated'
    );
    $cancel_activity = $this->getCancelActivity($cancel_activity_id);
    $this->assertEquals(
      $new_cancel_reason,
      $cancel_activity['contract_cancellation.contact_history_cancel_reason'],
      'cancel reason in activity should have been updated'
    );
    $this->assertEquals(
      $new_details,
      $cancel_activity['details'],
      'details should have been updated'
    );
    $this->assertEquals(
      $new_medium,
      $cancel_activity['medium_id'],
      'medium should have been updated'
    );

    $updated_membership = $this->getMembershipByID($membership['id']);
    $this->assertEquals(
      $new_cancel_reason,
      $updated_membership['membership_cancellation.membership_cancel_reason'],
      'cancel reason in membership should have been updated'
    );

    $updated_recur = $this->getActiveRecurringContribution($membership['id']);
    $this->assertEquals(
      $new_cancel_reason,
      $updated_recur['cancel_reason'],
      'cancel reason in recurring contribution should have been updated'
    );

    // revive contract
    $this->callAPISuccess('Contract', 'modify', [
      'action'                                  => 'revive',
      'id'                                      => $membership['id'],
      'membership_payment.defer_payment_start'  => TRUE,
      'membership_payment.membership_annual'    => 200.0,
      'membership_payment.membership_frequency' => 2,
      'payment_method.adapter'                  => 'sepa_mandate',
      'payment_method.cycle_day'                => 21,
    ]);

    $this->callAPISuccess('Contract', 'process_scheduled_modifications');

    // amend previous cancel again
    $new_cancel_reason = self::getOptionValue('contract_cancel_reason', 'adyen_refused');
    $new_medium = self::getOptionValue('encounter_medium', 'email');
    $new_details = NULL;
    $new_cancel_tags = ['cancel_tag_1'];
    $this->callAPISuccess('Contract', 'amend_cancel', [
      'activity_id' => $cancel_activity_id,
      'cancel_reason' => $new_cancel_reason,
      'medium_id' => $new_medium,
      'details' => $new_details,
      'cancel_tags' => $new_cancel_tags,
    ]);
    $this->assertEquals(
      $new_cancel_tags,
      $this->getCancelTagsForCancelActivity($cancel_activity_id),
      'cancel tags should have been updated'
    );
    $cancel_activity = $this->getCancelActivity($cancel_activity_id);
    $this->assertEquals(
      $new_cancel_reason,
      $cancel_activity['contract_cancellation.contact_history_cancel_reason'],
      'cancel reason in activity should have been updated'
    );
    $this->assertEquals(
      $new_details,
      $cancel_activity['details'],
      'details should have been updated'
    );
    $this->assertEquals(
      $new_medium,
      $cancel_activity['medium_id'],
      'medium should have been updated'
    );

    $updated_membership = $this->getMembershipByID($membership['id']);
    $this->assertEmpty(
      $updated_membership['membership_cancellation.membership_cancel_reason'],
      'cancel reason in membership should be empty'
    );

    $updated_recur = $this->getActiveRecurringContribution($membership['id']);
    $this->assertEmpty(
      $updated_recur['cancel_reason'],
      'cancel reason in recurring contribution should be empty'
    );

    // check that cancel_reason is required if param provided
    $this->callAPIFailure('Contract', 'amend_cancel', [
      'activity_id' => $cancel_activity_id,
      'cancel_reason' => NULL,
    ]);

  }

  private function getCancelTagsForCancelActivity($activityId) {
    $cancel_activities = (array) Api4\Activity::get(FALSE)
      ->addJoin('EntityTag AS et', 'INNER', ['id', '=', 'et.entity_id'])
      ->addWhere('id', '=', $activityId)
      ->addWhere('et.entity_table', '=', 'civicrm_activity')
      ->addSelect('et.tag_id:name')
      ->addOrderBy('et.tag_id:name', 'ASC')
      ->execute();

    return array_map(fn ($a) => $a['et.tag_id:name'], $cancel_activities);
  }

  private function getCancelActivity($activityId) {
    return (array) Api4\Activity::get(FALSE)
      ->addWhere('id', '=', $activityId)
      ->addSelect('*', 'custom.*')
      ->execute()
      ->first();
  }

}
