<?php

use Civi\Api4;

/**
 * @group headless
 */
class api_v3_Contract_CancelContractTest extends api_v3_Contract_ContractTestBase {

  public function testAdyen() {
    $today = new DateTimeImmutable();
    $tomorrow = new DateTimeImmutable('tomorrow');
    $next_year = $today->add(new DateInterval('P1Y'));

    // Create an Adyen contract
    $cycle_day = 13;
    $encounter_medium = self::getOptionValue('encounter_medium', 'in_person');
    $expiry_date = DateTimeImmutable::createFromFormat('Y-m-d', $next_year->format('Y-12-31'));
    $financial_type = self::getFinancialTypeID('Member Dues');
    $membership_type = self::getMembershipTypeID('General');
    $payment_instrument = self::getOptionValue('payment_instrument', 'Credit Card');
    $start_date = CRM_Contract_DateHelper::findNextOfDays([1], $tomorrow->format('Y-m-d'));

    $contract_create_result = civicrm_api3('Contract', 'create', [
      'contact_id'                              => $this->contact['id'],
      'join_date'                               => $today->format('Y-m-d'),
      'medium_id'                               => $encounter_medium,
      'membership_type_id'                      => $membership_type,
      'payment_method.account_number'           => 'AT40 1000 0000 0000 1111',
      'payment_method.adapter'                  => 'adyen',
      'payment_method.amount'                   => 10.0,
      'payment_method.billing_first_name'       => $this->contact['first_name'],
      'payment_method.billing_last_name'        => $this->contact['last_name'],
      'payment_method.contact_id'               => $this->contact['id'],
      'payment_method.cycle_day'                => $cycle_day,
      'payment_method.email'                    => $this->contact['email'],
      'payment_method.expiry_date'              => $expiry_date->format('Y-m-d'),
      'payment_method.financial_type_id'        => $financial_type,
      'payment_method.frequency_interval'       => 1,
      'payment_method.frequency_unit'           => 'month',
      'payment_method.ip_address'               => '127.0.0.1',
      'payment_method.payment_instrument_id'    => $payment_instrument,
      'payment_method.payment_processor_id'     => $this->adyenProcessor['id'],
      'payment_method.shopper_reference'        => 'OSF-TOKEN-PRODUCTION-56789-ADYEN',
      'payment_method.stored_payment_method_id' => '2856793471528814',
      'start_date'                              => $start_date->format('Y-m-d'),
    ]);

    // Check contract status
    $membership = self::getMembershipByID($contract_create_result['id']);
    $this->assertEquals('Current', $membership['status_id:name']);

    $recurring_contrib = self::getActiveRecurringContribution($membership['id']);
    $this->assertEquals('In Progress', $recurring_contrib['contribution_status_id:name']);

    // Cancel contract
    $cancel_reason = self::getOptionValue('contract_cancel_reason', 'adyen_refused');

    $contract_cancel_result = civicrm_api3('Contract', 'modify', [
      'modify_action'                                    => 'cancel',
      'id'                                               => $membership['id'],
      'date'                                             => 'now',
      'medium_id'                                        => $encounter_medium,
      'membership_cancellation.membership_cancel_reason' => $cancel_reason,
    ]);

    civicrm_api3('Contract', 'process_scheduled_modifications');

    // Check contract status
    $updated_membership = Api4\Membership::get(FALSE)
      ->addWhere('id', '=', $membership['id'])
      ->addSelect('membership_cancellation.membership_cancel_reason', 'status_id:name')
      ->execute()
      ->first();

    $this->assertEquals('Cancelled', $updated_membership['status_id:name'], 'Membership should be cancelled');
    $this->assertEquals($cancel_reason, $updated_membership['membership_cancellation.membership_cancel_reason'], 'Cancellation reason should be "Adyen: Refused"');

    $updated_rc_status = Api4\ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $recurring_contrib['id'])
      ->addSelect('contribution_status_id:name')
      ->execute()
      ->first()['contribution_status_id:name'];

    $this->assertEquals('Completed', $updated_rc_status, 'Recurring contribution should be completed');
  }

  public function testEFT() {
    $today = new DateTimeImmutable();
    $tomorrow = new DateTimeImmutable('tomorrow');

    // Create EFT contract
    $cycle_day = 17;
    $encounter_medium = self::getOptionValue('encounter_medium', 'in_person');
    $financial_type = self::getFinancialTypeID('Member Dues');
    $membership_type = self::getMembershipTypeID('General');
    $start_date = CRM_Contract_DateHelper::findNextOfDays([1], $tomorrow->format('Y-m-d'));

    $contract_create_result = civicrm_api3('Contract', 'create', [
      'contact_id'                        => $this->contact['id'],
      'join_date'                         => $today->format('Y-m-d'),
      'medium_id'                         => $encounter_medium,
      'membership_type_id'                => $membership_type,
      'payment_method.adapter'            => 'eft',
      'payment_method.amount'             => 10.0,
      'payment_method.contact_id'         => $this->contact['id'],
      'payment_method.cycle_day'          => $cycle_day,
      'payment_method.financial_type_id'  => $financial_type,
      'payment_method.frequency_interval' => 1,
      'payment_method.frequency_unit'     => 'month',
      'start_date'                        => $start_date->format('Y-m-d'),
    ]);

    // Check contract status
    $membership = self::getMembershipByID($contract_create_result['id']);
    $this->assertEquals('Current', $membership['status_id:name']);

    $recurring_contrib = self::getActiveRecurringContribution($membership['id']);
    $this->assertEquals('Pending', $recurring_contrib['contribution_status_id:name']);

    // Cancel contract
    $cancel_reason = self::getOptionValue('contract_cancel_reason', 'cancellation_via_bank');

    $contract_cancel_result = civicrm_api3('Contract', 'modify', [
      'modify_action'                                    => 'cancel',
      'id'                                               => $membership['id'],
      'date'                                             => 'now',
      'medium_id'                                        => $encounter_medium,
      'membership_cancellation.membership_cancel_reason' => $cancel_reason,
    ]);

    civicrm_api3('Contract', 'process_scheduled_modifications');

    // Check contract status
    $updated_membership = Api4\Membership::get(FALSE)
      ->addWhere('id', '=', $membership['id'])
      ->addSelect('membership_cancellation.membership_cancel_reason', 'status_id:name')
      ->execute()
      ->first();

    $this->assertEquals('Cancelled', $updated_membership['status_id:name'], 'Membership should be cancelled');
    $this->assertEquals($cancel_reason, $updated_membership['membership_cancellation.membership_cancel_reason'], 'Cancellation reason should be "Cancellation via bank"');

    $updated_rc_status = Api4\ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $recurring_contrib['id'])
      ->addSelect('contribution_status_id:name')
      ->execute()
      ->first()['contribution_status_id:name'];

    $this->assertEquals('Completed', $updated_rc_status, 'Recurring contribution should be completed');
  }

  public function testPSP() {
    $today = new DateTimeImmutable();
    $tomorrow = new DateTimeImmutable('tomorrow');

    // Create PSP contract
    $encounter_medium = self::getOptionValue('encounter_medium', 'in_person');
    $financial_type = self::getFinancialTypeID('Member Dues');
    $membership_type = self::getMembershipTypeID('General');
    $payment_instrument = self::getOptionValue('payment_instrument', 'Credit Card');
    $start_date = CRM_Contract_DateHelper::findNextOfDays([1], $tomorrow->format('Y-m-d'));

    $contract_create_result = civicrm_api3('Contract', 'create', [
      'contact_id'                           => $this->contact['id'],
      'join_date'                            => $today->format('Y-m-d'),
      'medium_id'                            => $encounter_medium,
      'membership_type_id'                   => $membership_type,
      'payment_method.account_name'          => 'Greenpeace',
      'payment_method.account_reference'     => 'OSF-TOKEN-PRODUCTION-12345-PSP',
      'payment_method.adapter'               => 'psp_sepa',
      'payment_method.amount'                => 10.0,
      'payment_method.contact_id'            => $this->contact['id'],
      'payment_method.creditor_id'           => $this->pspCreditor['id'],
      'payment_method.financial_type_id'     => $financial_type,
      'payment_method.frequency_interval'    => 1,
      'payment_method.frequency_unit'        => 'month',
      'payment_method.payment_instrument_id' => $payment_instrument,
      'payment_method.type'                  => 'RCUR',
      'start_date'                           => $start_date->format('Y-m-d'),
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
    $cancel_reason = self::getOptionValue('contract_cancel_reason', 'rdncc_card_expired');

    $contract_cancel_result = civicrm_api3('Contract', 'modify', [
      'modify_action'                                    => 'cancel',
      'id'                                               => $membership['id'],
      'date'                                             => 'now',
      'medium_id'                                        => $encounter_medium,
      'membership_cancellation.membership_cancel_reason' => $cancel_reason,
    ]);

    civicrm_api3('Contract', 'process_scheduled_modifications');

    // Check contract status
    $updated_membership = Api4\Membership::get(FALSE)
      ->addWhere('id', '=', $membership['id'])
      ->addSelect('membership_cancellation.membership_cancel_reason', 'status_id:name')
      ->execute()
      ->first();

    $this->assertEquals('Cancelled', $updated_membership['status_id:name'], 'Membership should be cancelled');
    $this->assertEquals($cancel_reason, $updated_membership['membership_cancellation.membership_cancel_reason'], 'Cancellation reason should be "RDNCC: Card expired"');

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
  }

  public function testSEPA() {
    $today = new DateTimeImmutable();
    $tomorrow = new DateTimeImmutable('tomorrow');

    // Create SEPA contract
    $encounter_medium = self::getOptionValue('encounter_medium', 'in_person');
    $financial_type = self::getFinancialTypeID('Member Dues');
    $membership_type = self::getMembershipTypeID('General');
    $payment_instrument = self::getOptionValue('payment_instrument', 'Credit Card');
    $start_date = CRM_Contract_DateHelper::findNextOfDays([1], $tomorrow->format('Y-m-d'));

    $contract_create_result = civicrm_api3('Contract', 'create', [
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

    $contract_cancel_result = civicrm_api3('Contract', 'modify', [
      'modify_action'                                    => 'cancel',
      'id'                                               => $membership['id'],
      'date'                                             => 'now',
      'medium_id'                                        => $encounter_medium,
      'membership_cancellation.membership_cancel_reason' => $cancel_reason,
    ]);

    civicrm_api3('Contract', 'process_scheduled_modifications');

    // Check contract status
    $updated_membership = Api4\Membership::get(FALSE)
      ->addWhere('id', '=', $membership['id'])
      ->addSelect('membership_cancellation.membership_cancel_reason', 'status_id:name')
      ->execute()
      ->first();

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
  }

}
