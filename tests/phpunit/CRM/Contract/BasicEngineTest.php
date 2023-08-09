<?php

use Civi\Api4\Activity;
use CRM_Contract_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

include_once 'ContractTestBase.php';

/**
 * Basic Contract Engine Tests
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Contract_BasicEngineTest extends CRM_Contract_ContractTestBase {

  public function setUp() {
    parent::setUp();
  }

  /**
   * Test a simple create
   */
  public function testSimpleCreate() {
    foreach ([0,1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract([
          'is_sepa'            => $is_sepa,
          'amount'             => '10.00',
          'frequency_unit'     => 'month',
          'frequency_interval' => '1',
      ]);

      // annual amount
      $this->assertEquals('120.00', $contract['membership_payment.membership_annual']);
      $this->assertEquals('2', $contract['status_id']);
      $this->assertNotEmpty($contract['membership_payment.membership_recurring_contribution']);
      $this->assertNotEmpty($contract['membership_payment.cycle_day']);
    }
  }

  /**
   * Test a simple cancellation
   */
  public function testSimpleCancel() {
    foreach ([1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);

      // schedule and update for tomorrow
      $this->modifyContract($contract['id'], 'cancel', 'tomorrow', [
          'membership_cancellation.membership_cancel_reason' => 'Unknown'
      ]);

      // run engine see if anything changed
      $this->runContractEngine($contract['id']);

      // things should not have changed
      $contract_changed1 = $this->getContract($contract['id']);
      $this->assertEquals($contract, $contract_changed1, "This shouldn't have changed");

      // run engine again for tomorrow
      $this->runContractEngine($contract['id'], '+2 days');
      $contract_changed2 = $this->getContract($contract['id']);
      $this->assertNotEquals($contract, $contract_changed2, "This should have changed");

      // make sure status is cancelled
      $this->assertEquals($this->getMembershipStatusID('Cancelled'), $contract_changed2['status_id'], "The contract wasn't cancelled");

      // make sure 'cancel_reason' of the associated recurring contribution is updated
      $recurringContributionId = $contract_changed2["membership_payment.membership_recurring_contribution"];
      $recurringContribution = new CRM_Contribute_DAO_ContributionRecur();
      $recurringContribution->get("id", $recurringContributionId);

      $cancelReasonOptionValue = civicrm_api3("OptionValue", "getsingle", [
        "option_group_id" => "contract_cancel_reason",
        "name" => "Unknown",
      ])["value"];

      $this->assertEquals(
        $cancelReasonOptionValue,
        $recurringContribution->cancel_reason,
        "'cancel_reason' of the recurring contribution should be ${cancelReasonOptionValue} (='Unknown')"
      );

      $this->assertNotEmpty(
        $recurringContribution->cancel_date,
        'cancel_date should be set after cancellation'
      );
      $this->assertNotEmpty(
        $recurringContribution->end_date,
        'end_date should be set after cancellation'
      );

      // make sure membership_cancellation fields are set
      $this->assertEquals(
        $contract_changed2["membership_cancellation.membership_cancel_reason"],
        1
      );

      $this->assertEquals(
        $contract_changed2["membership_cancellation.membership_cancel_date"],
        date("Y-m-d 00:00:00")
      );
    }
  }

  /**
   * Test a simple upgrade
   */
  public function testSimpleUpgrade() {
    foreach ([1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);

      // schedule and update for tomorrow
      $this->modifyContract($contract['id'], 'update', 'tomorrow', [
          'membership_payment.membership_annual'             => '240.00',
          'membership_cancellation.membership_cancel_reason' => $this->getRandomOptionValue('contract_cancel_reason')]);

      // run engine see if anything changed
      $this->runContractEngine($contract['id']);

      // things should not have changed
      $contract_changed1 = $this->getContract($contract['id']);
      $this->assertEquals($contract, $contract_changed1, "This shouldn't have changed");

      // run engine again for tomorrow
      $this->runContractEngine($contract['id'], '+2 days');
      $contract_changed2 = $this->getContract($contract['id']);
      $this->assertNotEquals($contract, $contract_changed2, "This should have changed");

      // make sure status is current
      $this->assertEquals($this->getMembershipStatusID('Current'), $contract_changed2['status_id'], "The contract isn't active");
      $this->assertEquals(240.00, $contract_changed2['membership_payment.membership_annual'], "The contract has the wrong amount");
    }
  }

  /**
   * Test a simple pause/resume
   */
  public function testSimplePauseResume() {
    foreach ([1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);

      // schedule and update for tomorrow
      $this->modifyContract($contract['id'], 'pause', 'tomorrow');
      $changes = $this->callAPISuccess('Activity', 'get', ['source_record_id' => $contract['id']]);


      // run engine see if anything changed
      $this->runContractEngine($contract['id']);

      // things should not have changed
      $contract_changed1 = $this->getContract($contract['id']);
      $this->assertEquals($contract, $contract_changed1, "This shouldn't have changed");

      // run engine again for tomorrow
      $this->runContractEngine($contract['id'], '+1 day');
      $contract_changed2 = $this->getContract($contract['id']);
      $this->assertEquals($this->getMembershipStatusID('Paused'), $contract_changed2['status_id'], "The contract isn't paused");
      $mandate = $this->getMandateForContract($contract['id']);
      $this->assertEquals('ONHOLD', $mandate['status'], 'Mandate should be on hold');

      // run engine again for the day after tomorrow
      $this->runContractEngine($contract['id'], '+2 days');
      $contract_changed2 = $this->getContract($contract['id']);
      $this->assertEquals($this->getMembershipStatusID('Current'), $contract_changed2['status_id'], "The contract isn't paused");
      $mandate = $this->getMandateForContract($contract['id']);
      $this->assertEquals('FRST', $mandate['status'], 'Mandate should be active');
      $this->assertEquals(
        $contract_changed1['membership_payment.membership_recurring_contribution'],
        $contract_changed2['membership_payment.membership_recurring_contribution'],
        'Recurring contribution should have remained the same'
      );
    }
  }

  /**
   * Test a simple revive
   */
  public function testSimpleRevive() {
    foreach ([1] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);

      // schedule and update for tomorrow
      $this->modifyContract($contract['id'], 'cancel', 'tomorrow', [
          'membership_cancellation.membership_cancel_reason' => 'Unknown'
      ]);

      // run engine again for tomorrow
      $this->runContractEngine($contract['id'], '+1 days');
      $contract_cancelled = $this->getContract($contract['id']);
      $this->assertNotEquals($contract, $contract_cancelled, "This should have changed");

      // make sure status is cancelled
      $this->assertEquals($this->getMembershipStatusID('Cancelled'), $contract_cancelled['status_id'], "The contract wasn't cancelled");

      // now: revive contract
      $this->modifyContract($contract['id'], 'revive', '+2 days', [
          'membership_payment.membership_annual'             => '240.00',
          'membership_cancellation.membership_cancel_reason' => $this->getRandomOptionValue('contract_cancel_reason')]);

      $this->runContractEngine($contract['id'], '+2 days');
      $contract_revived = $this->getContract($contract['id']);
      $this->assertNotEquals($contract_cancelled, $contract_revived, "This should have changed");

      // make sure status is cancelled
      $this->assertEquals($this->getMembershipStatusID('Current'), $contract_revived['status_id'], "The contract wasn't revived");
      $this->assertEquals(240.00, $contract_revived['membership_payment.membership_annual'], "The contract has the wrong amount");

      // make sure membership_cancellation was cleared GP-12430
      $this->assertEmpty($contract_revived['membership_cancellation.membership_cancel_reason']);
      $this->assertEmpty($contract_revived['membership_cancellation.membership_cancel_date'] ?? NULL);
    }
  }

  /**
   * Test that an update with invalid parameters causes failure
   */
  public function testUpdateFailure() {
    // create a new contract
    $contract = $this->createNewContract([ "is_sepa" => true ]);

    // schedule and update with an invalid from_ba
    $this->modifyContract($contract['id'], 'update', 'tomorrow', [
      'membership_payment.from_ba'           => 12345,
      'membership_payment.membership_annual' => '123.00',
    ]);
    // run engine for tomorrow
    $result = $this->callEngineFailure(
      $contract['id'],
      '+1 days',
      "Expected one BankingAccount"
    );
    $activityId = $result['failed'][0];
    // contract update activity should be status failed and details should contain error
    $this->callAPISuccess('Activity', 'getsingle', [
      'id'        => $activityId,
      'status_id' => 'Failed',
      'details'   => ['LIKE' => '%Expected one BankingAccount%'],
    ]);
    $contract_changed = $this->getContract($contract['id']);
    $this->assertEquals(
      $contract,
      $contract_changed,
      'Contract shouldn\'t have changed after failure'
    );
  }

  /**
   * Test that cancellations of already-cancelled memberships aren't allowed
   */
  public function testAllowedStatusChangeCancellation() {
    // create a new contract
    $contract = $this->createNewContract();

    // schedule cancel
    $this->modifyContract($contract['id'], 'cancel', '+1 days', [
      'membership_cancellation.membership_cancel_reason' => 'Unknown'
    ]);
    // also schedule an update a week from now
    $this->modifyContract($contract['id'], 'update', '+7 days', [
      'membership_payment.membership_annual' => '123.00',
    ]);
    // resolve "Needs Review"
    CRM_Core_DAO::executeQuery("UPDATE civicrm_activity SET status_id = 1 WHERE source_record_id = {$contract['id']} AND status_id <> 2;");
    // run the cancellation (but not the update)
    $this->runContractEngine($contract['id'], '+2 days');

    $contract_changed = $this->getContract($contract['id']);
    $this->assertEquals(
      'Cancelled',
      CRM_Contract_Utils::getMembershipStatusName($contract_changed['status_id']),
      'Membership should be cancelled'
    );

    // cancel contract again (while it's already cancelled)
    $this->modifyContract($contract['id'], 'cancel', '+1 days', [
      'membership_cancellation.membership_cancel_reason' => 'Unknown'
    ]);
    // resolve "Needs Review"
    CRM_Core_DAO::executeQuery("UPDATE civicrm_activity SET status_id = 1 WHERE source_record_id = {$contract['id']} AND status_id <> 2;");
    // run cancellation
    $this->callEngineFailure(
      $contract['id'],
      '+2 days',
      "Cannot cancel a membership when its status is 'Cancelled'"
    );
  }

  /**
   * Test that reviving active memberships is not possible
   */
  public function testAllowedStatusChangeRevive() {
    // create a new contract
    $contract = $this->createNewContract();

    // schedule revive
    $this->modifyContract($contract['id'], 'revive', '+1 days', [
      'membership_payment.membership_annual' => '123.00',
    ]);
    // run revive
    $this->callEngineFailure(
      $contract['id'],
      '+2 days',
      "Cannot revive a membership when its status is 'Current'"
    );
  }

  /**
   * Test campaign propagation behaviour
   */
  public function testCampaign() {
    $campaign_id = $this->callAPISuccess('Campaign', 'create', [
      'title' => 'sign',
    ])['id'];
    $upgrade_campaign_id = $this->callAPISuccess('Campaign', 'create', [
      'title' => 'upgrade',
    ])['id'];
    $revive_campaign_id = $this->callAPISuccess('Campaign', 'create', [
      'title' => 'revive',
    ])['id'];
    // create contract with campaign_id
    $contract = $this->createNewContract([
      'is_sepa'            => 1,
      'amount'             => '10.00',
      'frequency_unit'     => 'month',
      'frequency_interval' => '1',
      'campaign_id'        => $campaign_id,
    ]);

    $this->assertEquals(
      $campaign_id,
      $contract['campaign_id'],
      'campaign_id should be set for contract'
    );
    $this->assertContributionRecurCampaignMatches($contract['membership_payment.membership_recurring_contribution'], $campaign_id);
    $this->assertLatestContractActivityCampaignMatches($contract['id'], $campaign_id);

    // upgrade contract using a different campaign
    $this->modifyContract($contract['id'], 'update', 'now', [
      'membership_payment.membership_annual' => '240.00',
      'campaign_id'                          => $upgrade_campaign_id,
    ]);
    $this->runContractEngine($contract['id']);

    $contract = $this->getContract($contract['id']);
    $this->assertEquals(240.00, $contract['membership_payment.membership_annual'], 'contract amount should have changed');
    $this->assertEquals(
      $campaign_id,
      $contract['campaign_id'],
      'campaign_id for contract should be unchanged'
    );
    $this->assertContributionRecurCampaignMatches($contract['membership_payment.membership_recurring_contribution'], $upgrade_campaign_id);
    $this->assertLatestContractActivityCampaignMatches($contract['id'], $upgrade_campaign_id);

    // cancel contract
    $this->modifyContract($contract['id'], 'cancel', 'now', [
      'membership_cancellation.membership_cancel_reason' => 'Unknown'
    ]);
    $this->runContractEngine($contract['id']);

    // revive contract using another campaign
    $this->modifyContract($contract['id'], 'revive', 'now', [
      'membership_payment.membership_annual'             => '240.00',
      'campaign_id'                                      => $revive_campaign_id,
      'payment_method.reference' => "SEPA-" . $contract['id'] . "-" . date("Ymd") . bin2hex(random_bytes(4)),
    ]);
    $this->runContractEngine($contract['id']);

    $contract = $this->getContract($contract['id']);
    $this->assertContributionRecurCampaignMatches($contract['membership_payment.membership_recurring_contribution'], $revive_campaign_id);
    $this->assertLatestContractActivityCampaignMatches($contract['id'], $revive_campaign_id);
  }

  private function assertContributionRecurCampaignMatches($contributionRecurId, $campaignId) {
    $rcur_campaign_id = $this->callAPISuccess('ContributionRecur', 'getvalue', [
      'id'     => $contributionRecurId,
      'return' => 'campaign_id',
    ]);
    $this->assertEquals(
      $campaignId,
      $rcur_campaign_id,
      'campaign_id of recurring contribution should match'
    );
  }

  private function assertLatestContractActivityCampaignMatches($contractId, $campaignId) {
    $activity_campaign_id = $this->callAPISuccess('Activity', 'getvalue', [
      'source_record_id' => $contractId,
      'return'           => 'campaign_id',
      'options'          => ['limit' => 1, 'sort' => 'activity_date_time DESC'],
    ]);
    $this->assertEquals(
      $campaignId,
      $activity_campaign_id,
      'campaign_id of contract activity should match'
    );
  }

  /**
   * Test a pause/resume where resume contains a change
   */
  public function testPauseResumeWithUpdate() {
    // create a new contract
    $contract = $this->createNewContract(['is_sepa' => TRUE]);

    // schedule and update for tomorrow
    $this->modifyContract($contract['id'], 'pause', 'tomorrow', [
      'membership_payment.membership_annual' => '240.00',
      'membership_payment.cycle_day'         => 10,
    ]);
    $resume_activity_id = $this->callAPISuccess('Activity', 'getvalue', [
      'return'           => 'id',
      'activity_type_id' => 'Contract_Resumed',
      'source_record_id' => $contract['id'],
    ]);
    // change an update fields in the resume activity
    $cycle_day_key = CRM_Contract_Utils::getCustomFieldId('contract_updates.ch_cycle_day');
    $this->callAPISuccess('Activity', 'create', [
      'id'           => $resume_activity_id,
      $cycle_day_key => '10',
    ]);

    // run engine for tomorrow
    $this->runContractEngine($contract['id'], '+1 day');
    $contract_changed1 = $this->getContract($contract['id']);
    $this->assertEquals($this->getMembershipStatusID('Paused'), $contract_changed1['status_id'], "The contract isn't paused");
    $mandate = $this->getMandateForContract($contract['id']);
    $this->assertEquals('ONHOLD', $mandate['status'], 'Mandate should be on hold');

    // run engine again for the day after tomorrow
    $this->runContractEngine($contract['id'], '+2 days');
    $contract_changed2 = $this->getContract($contract['id']);
    $this->assertEquals($this->getMembershipStatusID('Current'), $contract_changed2['status_id'], "The contract isn't paused");
    $mandate = $this->getMandateForContract($contract['id']);
    $this->assertEquals('FRST', $mandate['status'], 'Mandate should be active');
    $this->assertEquals('10', $contract_changed2['membership_payment.cycle_day'], 'cycle_day should have changed');
    $this->assertNotEquals(
      $contract_changed1['membership_payment.membership_recurring_contribution'],
      $contract_changed2['membership_payment.membership_recurring_contribution'],
      'Recurring contribution should have changed'
    );
  }

  /**
   * It should not be possible to schedule contract modifications before an
   * already specified minimum change date
   */
  public function testScheduleUpdateBeforeMinimumChangeDate () {
    // Create a new contract
    $contract = $this->createNewContract();

    // Set the minimum change date for contracts to 2 days from now
    $minimumChangeDate = date("Y-m-d H:i:s", strtotime("+2 days"));
    Civi::settings()->set("contract_minimum_change_date", $minimumChangeDate);

    // Expect the following API call to throw an exception
    $this->expectException(Exception::class);

    // Schedule a contract update for tomorrow
    $updateResult = civicrm_api3("Contract", "modify", [
      "id"                           => $contract["id"],
      "modify_action"                => "update",
      "date"                         => date('Y-m-d H:i:s', strtotime("+1 day")),
      "medium_id"                    => 1,
      "membership_payment.cycle_day" => 15,
    ]);
  }

  /**
   * When contract modifications are already scheduled before a minimum
   * change date they should be marked for review and not be executed during
   * processing
   */
  public function testSetMinimumChangeDateAfterScheduledUpdate () {
    // Make sure contract_minimum_change_date is not set
    Civi::settings()->set("contract_minimum_change_date", null);

    // Create a new contract
    $contract = $this->createNewContract([ "is_sepa" => true ]);
    CRM_Contract_CustomData::labelCustomFields($contract);
    $originalCycleDay = $contract["membership_payment.cycle_day"];

    // Schedule a contract update for tomorrow
    $updateResult = $this->modifyContract($contract["id"], "update", "+1 day", [
      "membership_payment.cycle_day" => 15,
    ]);

    // Set the minimum change date for contracts to 3 days from now
    $minimumChangeDate = date("Y-m-d H:i:s", strtotime("+3 days"));
    Civi::settings()->set("contract_minimum_change_date", $minimumChangeDate);

    // Process scheduled modifications 2 days from now
    $this->runContractEngine($contract["id"], "+2 days");

    // Get Update activity
    $updateActivity = civicrm_api3("Activity", "getsingle", [
      "activity_type_id" => CRM_Contract_Change::getActivityIdForClass("CRM_Contract_Change_Upgrade"),
      "source_record_id" => $contract["id"],
    ]);

    $updateActivityStatus = civicrm_api3("OptionValue", "getsingle", [
      "option_group_id" => "activity_status",
      "return"          => [ "name" ],
      "value"           => $updateActivity["status_id"],
    ]);

    // Assert that the modification has been labelled with "Needs Review"
    // because it was scheduled before the minimum change date
    $this->assertEquals(
      "Needs Review",
      $updateActivityStatus["name"],
      "The contract modification should be labelled with \"Needs Review\""
    );

    // Assert that the contract remains unchanged
    $contract = civicrm_api3("Contract", "getsingle", [ "id" => $contract["id"] ]);
    CRM_Contract_CustomData::labelCustomFields($contract);
    $newCycleDay = $contract["membership_payment.cycle_day"];

    $this->assertEquals(
      $originalCycleDay,
      $newCycleDay,
      "The contract's cycle day should remain unchanged"
    );
  }

  /**
   * When contract modifications are scheduled before a minimum change date but
   * are processed after that minimum date, the scheduled dates should be
   * ignored and the modififactions should be applied anyway
   */
  public function testProcessingAfterMinimumChangeDate () {
    // Make sure contract_minimum_change_date is not set
    Civi::settings()->set("contract_minimum_change_date", null);

    // Create a new contract
    $contract = $this->createNewContract([ "is_sepa" => true ]);
    CRM_Contract_CustomData::labelCustomFields($contract);
    $originalCycleDay = $contract["membership_payment.cycle_day"];

    // Schedule a contract update for tomorrow
    $updateResult = $this->modifyContract($contract["id"], "update", "+1 day", [
      "membership_payment.cycle_day" => 15,
    ]);

    // Set the minimum change date for contracts to 2 days from now
    $minimumChangeDate = date("Y-m-d H:i:s", strtotime("+2 days"));
    Civi::settings()->set("contract_minimum_change_date", $minimumChangeDate);

    // Process scheduled modifications 3 days from now
    $this->runContractEngine($contract["id"], "+3 days");

    // Get Update activity
    $updateActivity = civicrm_api3("Activity", "getsingle", [
      "activity_type_id" => CRM_Contract_Change::getActivityIdForClass("CRM_Contract_Change_Upgrade"),
      "source_record_id" => $contract["id"],
    ]);

    $updateActivityStatus = civicrm_api3("OptionValue", "getsingle", [
      "option_group_id" => "activity_status",
      "return"          => [ "name" ],
      "value"           => $updateActivity["status_id"],
    ]);

    // Assert that the modification has been labelled with "Completed"
    // because it was processed after the minimum change date
    $this->assertEquals(
      "Completed",
      $updateActivityStatus["name"],
      "The contract modification should be labelled with \"Needs Review\""
    );

    // Assert that the contract has changed
    $contract = civicrm_api3("Contract", "getsingle", [ "id" => $contract["id"] ]);
    CRM_Contract_CustomData::labelCustomFields($contract);
    $newCycleDay = $contract["membership_payment.cycle_day"];

    $this->assertNotEquals(
      $originalCycleDay,
      $newCycleDay,
      "The contract's cycle day should have changed"
    );

  }

  /**
   * Test that defer_payment_start is handled correctly
   */
  public function testDeferPaymentStart() {
    $now = new DateTimeImmutable();
    $nowPlusOneYear = $now->add(new Dateinterval('P1Y'))->setTime(0, 0);

    // create a contract starting now
    $contract = $this->createNewContract([
      'is_sepa'            => 1,
      'amount'             => '10.00',
      'frequency_unit'     => 'year',
      'frequency_interval' => '1',
      'start_date'         => $now->format('Y-m-d'),
    ]);

    $nextScheduleDate = new DateTime(civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'next_sched_contribution_date',
      'id'     => $contract['membership_payment.membership_recurring_contribution'],
    ]));

    // the next scheduled contribution date should be as soon as possible (within the next year)
    $this->assertLessThan(
      $nowPlusOneYear->getTimestamp(),
      $nextScheduleDate->getTimestamp(),
      'next_sched_contribution_date should be as soon as possible'
    );

    $contribution = civicrm_api3('Contribution', 'create', [
      'contact_id'            => $contract['contact_id'],
      'contribution_recur_id' => $contract['membership_payment.membership_recurring_contribution'],
      'financial_type_id'     => 'Member Dues',
      'receive_date'          => $nextScheduleDate->format('Y-m-d'),
      'total_amount'          => 10.0,
    ]);

    civicrm_api3('MembershipPayment', 'create', [
      'contribution_id' => $contribution['id'],
      'membership_id'   => $contract['id'],
    ]);

    // update to a monthly membership with defer_payment_start's default value of 1
    $this->modifyContract($contract['id'], 'update', 'now', [
      'membership_payment.membership_frequency' => '12',
    ]);

    $this->runContractEngine($contract['id']);
    $contract = $this->getContract($contract['id']);

    $nextScheduleDateAfterChange = new DateTime(civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'next_sched_contribution_date',
      'id' => $contract['membership_payment.membership_recurring_contribution'],
    ]));

    $this->assertGreaterThanOrEqual(
      $nowPlusOneYear,
      $nextScheduleDateAfterChange,
      'Existing contributions should be respected'
    );

    // update again, but use defer_payment_start = 0
    $newSepaReference = "SEPA-" . $contract['id'] . "-" . date("Ymd") . bin2hex(random_bytes(4));

    $this->modifyContract($contract['id'], 'update', 'now', [
      'membership_payment.membership_frequency' => '12',
      'membership_payment.defer_payment_start'  => 0,
      'payment_method.reference'                => $newSepaReference,
    ]);

    $this->runContractEngine($contract['id']);
    $contract = $this->getContract($contract['id']);

    $nextScheduleDateAfterChange = new DateTime(civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'next_sched_contribution_date',
      'id'     => $contract['membership_payment.membership_recurring_contribution'],
    ]));

    $this->assertLessThan(
      $nowPlusOneYear->getTimestamp(),
      $nextScheduleDate->getTimestamp(),
      'next_sched_contribution_date should be as soon as possible'
    );
  }

  public function testAnnualDiff() {
    $contract = $this->createNewContract([
      'is_sepa'            => 1,
      'amount'             => '10.50',
      'frequency_unit'     => 'month',
      'frequency_interval' => '1',
    ]);

    $this->modifyContract($contract['id'], 'update', 'now', [
      'membership_payment.membership_annual' => '2400.60',
    ]);
    $this->runContractEngine($contract['id']);
    $contract = $this->getContract($contract['id']);

    $activity = Activity::get(FALSE)
      ->addWhere('activity_type_id:name', '=', 'Contract_Updated')
      ->addWhere('source_record_id', '=', $contract['id'])
      ->addSelect('custom.*')
      ->addOrderBy('activity_date_time', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first();

    $this->assertEquals($activity['contract_updates.ch_annual'], 2400.6);
    $this->assertEquals($activity['contract_updates.ch_annual_diff'], 2274.6);
  }

}
