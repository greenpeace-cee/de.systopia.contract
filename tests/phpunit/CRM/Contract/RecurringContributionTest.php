<?php

/**
 * Class CRM_Contract_RecurringContributionTest
 *
 * @group headless
 */
class CRM_Contract_RecurringContributionTest extends CRM_Contract_ContractTestBase {

  public function testGetAll() {
    $contact_id = $this->createContactWithRandomEmail()['id'];
    // create a contract with an associated recurring contribution
    $contract = $this->createNewContract([
      'is_sepa'            => TRUE,
      'amount'             => '10.00',
      'frequency_unit'     => 'month',
      'frequency_interval' => '1',
      'contact_id'         => $contact_id,
    ]);
    // create a second contract
    $this->createNewContract([
      'is_sepa'            => TRUE,
      'amount'             => '12.00',
      'frequency_unit'     => 'month',
      'frequency_interval' => '1',
      'contact_id'         => $contact_id,
    ]);
    // create another recurring contribution that is not in use
    $rcurManual = $this->callAPISuccess('ContributionRecur', 'create', [
      'contact_id'             => $contact_id,
      'amount'                 => 15,
      'frequency_interval'     => 12,
      'contribution_status_id' => 'Pending',
      'payment_instrument_id'  => 'Credit Card',
    ]);
    $rcur = new CRM_Contract_RecurringContribution();
    // get unused recurring contributions for this contact
    $rcurUnused = $rcur->getAll($contact_id);
    $this->assertCount(1, $rcurUnused, 'Expected one unused recurring contribution');
    $rcurUnused = reset($rcurUnused);
    $this->assertEquals('15.00', $rcurUnused['fields']['amount'], 'Expected unused recurring contribution');
    // get all recurring contributions for this contact
    $rcurAll = $rcur->getAll($contact_id, FALSE);
    $this->assertCount(3, $rcurAll, 'Expected three recurring contributions');
    // get unused recurring contributions or recurring contributions belonging to $contract
    $rcurAll = $rcur->getAll($contact_id, TRUE, $contract['id']);
    $this->assertCount(2, $rcurAll, 'Expected two recurring contributions');
    // schedule (but don't execute) an update to the manually-created recurring contribution
    $update = [
      'membership_payment.membership_recurring_contribution' => $rcurManual['id'],
    ];
    $this->modifyContract(
      $contract['id'],
      'update',
      'now + 1 day',
      $update
    );
    // get unused recurring contributions for this contact
    $rcurUnused = $rcur->getAll($contact_id, TRUE, NULL, FALSE);
    $this->assertCount(0, $rcurUnused, 'Expected zero unused recurring contribution');
  }

  public function testIsAssignableToContract() {
    $contact_id = $this->createContactWithRandomEmail()['id'];
    // create two contracts
    $contract1 = $this->createNewContract([
      'is_sepa'            => TRUE,
      'amount'             => '10.00',
      'frequency_unit'     => 'month',
      'frequency_interval' => '1',
      'contact_id'         => $contact_id,
    ]);
    $contract2 = $this->createNewContract([
      'is_sepa'            => TRUE,
      'amount'             => '10.00',
      'frequency_unit'     => 'month',
      'frequency_interval' => '1',
      'contact_id'         => $contact_id,
    ]);

    $rcur = new CRM_Contract_RecurringContribution();
    $this->assertTrue(
      $rcur->isAssignableToContract(
        $contract1['membership_payment.membership_recurring_contribution'],
        $contract1['id']
      ),
      'Recurring contribution should be assignable to its membership'
    );
    $this->assertFalse(
      $rcur->isAssignableToContract(
        $contract1['membership_payment.membership_recurring_contribution'],
        $contract2['id']
      ),
      'Recurring contribution should NOT be assignable to other memberships'
    );
  }

}
