<?php

use Civi\Api4;
use Civi\Core\Event\GenericHookEvent;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use PHPUnit\Framework\TestCase;

/**
 * @group headless
 */
class CRM_Contract_PaymentAdapter_AdyenTest extends CRM_Contract_PaymentAdapterTestBase {

  const BANK_ACCOUNT_NUMBER = 'AT40 1000 0000 0000 1111';
  const SHOPPER_REFERENCE = 'OSF-TOKEN-PRODUCTION-12345-SCHEME';
  const STORED_PAYMENT_METHOD_ID = '5916982445614528';

  private $paymentProcessor;
  private $paymentToken;

  public function setUp() {
    parent::setUp();

    $this->createPaymentProcessor();
    $this->createPaymentToken();
  }

  public function tearDown() {
    parent::tearDown();
  }

  public function testCreate() {

    // --- Create a new Adyen payment --- //

    $tomorrow = new DateTimeImmutable('tomorrow');
    $startDate = CRM_Contract_DateHelper::findNextOfDays([15], $tomorrow->format('Y-m-d'));
    $oneYear = new DateInterval('P1Y');

    $expiryDate = DateTimeImmutable::createFromMutable($startDate)
      ->add($oneYear)
      ->setDate((int) $startDate->format('Y'), 12, 31);

    $cycleDay = $startDate->format('d');
    $creditCardOptVal = self::getOptionValue('payment_instrument', 'Credit Card');
    $inProgressOptVal = self::getOptionValue('contribution_recur_status', 'In Progress');

    $memberDuesTypeID = Api4\FinancialType::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'Member Dues')
      ->execute()
      ->first()['id'];

    $recurContribID = CRM_Contract_PaymentAdapter_Adyen::create([
      'account_number'           => self::BANK_ACCOUNT_NUMBER,
      'amount'                   => 10.0,
      'billing_first_name'       => $this->contact['first_name'],
      'billing_last_name'        => $this->contact['last_name'],
      'contact_id'               => $this->contact['id'],
      'contribution_status_id'   => $inProgressOptVal,
      'currency'                 => 'EUR',
      'cycle_day'                => $cycleDay,
      'email'                    => $this->contact['email'],
      'expiry_date'              => $expiryDate->format('Y-m-d'),
      'financial_type_id'        => $memberDuesTypeID,
      'frequency_interval'       => 1,
      'frequency_unit'           => 'month',
      'ip_address'               => '127.0.0.1',
      'payment_instrument_id'    => $creditCardOptVal,
      'payment_processor_id'     => $this->paymentProcessor['id'],
      'shopper_reference'        => self::SHOPPER_REFERENCE,
      'start_date'               => $startDate->format('Y-m-d'),
      'stored_payment_method_id' => self::STORED_PAYMENT_METHOD_ID,
    ]);

    // --- Assert recurring contribution has been created --- //

    $recurContribQuery = Api4\ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $recurContribID)
      ->addSelect(
        'amount',
        'contact_id',
        'contribution_status_id:name',
        'currency',
        'cycle_day',
        'frequency_interval',
        'frequency_unit',
        'next_sched_contribution_date',
        'payment_instrument_id',
        'payment_token_id',
        'processor_id',
        'start_date',
        'trxn_id'
      )
      ->execute();

    $this->assertEquals(1, $recurContribQuery->rowCount);
    $recurringContribution = $recurContribQuery->first();

    $this->assertEquals([
      'amount'                       => 10.0,
      'contact_id'                   => $this->contact['id'],
      'contribution_status_id:name'  => 'In Progress',
      'currency'                     => 'EUR',
      'cycle_day'                    => 15,
      'frequency_interval'           => 1,
      'frequency_unit'               => 'month',
      'id'                           => $recurringContribution['id'],
      'next_sched_contribution_date' => $startDate->format('Y-m-d H:i:s'),
      'payment_instrument_id'        => $creditCardOptVal,
      'payment_token_id'             => $recurringContribution['payment_token_id'],
      'processor_id'                 => self::SHOPPER_REFERENCE,
      'start_date'                   => $startDate->format('Y-m-d H:i:s'),
      'trxn_id'                      => NULL,
    ], $recurringContribution);

    // --- Assert payment token has been created --- //

    $paymentTokenQuery = Api4\PaymentToken::get(FALSE)
      ->addSelect(
        'billing_first_name',
        'billing_last_name',
        'contact_id',
        'email',
        'expiry_date',
        'ip_address',
        'masked_account_number',
        'payment_processor_id',
        'token'
      )
      ->addWhere('id', '=', $recurringContribution['payment_token_id'])
      ->execute();

    $this->assertEquals(1, $paymentTokenQuery->rowCount);
    $paymentToken = $paymentTokenQuery->first();

    $this->assertEquals([
      'billing_first_name'    => $this->contact['first_name'],
      'billing_last_name'     => $this->contact['last_name'],
      'contact_id'            => $this->contact['id'],
      'email'                 => $this->contact['email'],
      'expiry_date'           => $expiryDate->format('Y-m-d H:i:s'),
      'id'                    => $paymentToken['id'],
      'ip_address'            => '127.0.0.1',
      'masked_account_number' => self::BANK_ACCOUNT_NUMBER,
      'payment_processor_id'  => $this->paymentProcessor['id'],
      'token'                 => self::STORED_PAYMENT_METHOD_ID,
    ], $paymentToken);

  }

  public function testCreateFromUpdate() {

    // --- Create an EFT payment --- //

    $memberDuesTypeID = CRM_Contract_Utils::getFinancialTypeID('Member Dues');

    $originalRCID = CRM_Contract_PaymentAdapter_EFT::create([
      'amount'             => 10.0,
      'contact_id'         => $this->contact['id'],
      'financial_type_id'  => $memberDuesTypeID,
      'frequency_interval' => 1,
      'frequency_unit'     => 'month',
    ]);

    // --- Assert recurring contribution has been created --- //

    $originalRCResult = Api4\ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $originalRCID)
      ->addSelect(
        'amount',
        'contact_id',
        'financial_type_id',
        'frequency_interval',
        'frequency_unit:name'
      )
      ->execute();

    $this->assertEquals(1, $originalRCResult->rowCount);

    $originalRecurContrib = $originalRCResult->first();

    $this->assertEquals([
      'amount'              => 10.0,
      'contact_id'          => $this->contact['id'],
      'financial_type_id'   => $memberDuesTypeID,
      'frequency_interval'  => 1,
      'frequency_unit:name' => 'month',
      'id'                  => $originalRecurContrib['id'],
    ], $originalRecurContrib);

    // --- Change payment type to Adyen and update amount --- //

    $newRCID = CRM_Contract_PaymentAdapter_Adyen::createFromUpdate($originalRCID, 'eft', [
      'amount'           => 15.0,
      'payment_token_id' => $this->paymentToken['id'],
    ]);

    // --- Assert a new recurring contribution has been created --- //

    $this->assertNotEquals($originalRCID, $newRCID);

    $newRCResult = Api4\ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $newRCID)
      ->addSelect(
        'amount',
        'contact_id',
        'financial_type_id',
        'frequency_interval',
        'frequency_unit:name',
        'payment_instrument_id',
        'payment_processor_id',
        'payment_token_id'
      )
      ->execute();

    $this->assertEquals(1, $newRCResult->rowCount);

    $newRecurContrib = $newRCResult->first();

    $this->assertEquals([
      'amount'                => 15.0,
      'contact_id'            => $this->contact['id'],
      'financial_type_id'     => $memberDuesTypeID,
      'frequency_interval'    => 1,
      'frequency_unit:name'   => 'month',
      'id'                    => $newRecurContrib['id'],
      'payment_instrument_id' => $this->paymentToken['payment_instrument_id'],
      'payment_processor_id'  => $newRecurContrib['payment_processor_id'],
      'payment_token_id'      => $this->paymentToken['id'],
    ], $newRecurContrib);

    // --- Assert the new payment is linked to an Adyen payment processor --- //

    $paymentProcessor = Api4\PaymentProcessor::get(FALSE)
      ->addWhere('id', '=', $newRecurContrib['payment_processor_id'])
      ->addSelect('payment_processor_type_id:name')
      ->execute()
      ->first();

    $this->assertEquals('Adyen', $paymentProcessor['payment_processor_type_id:name']);

  }

  public function testNextSchedContributionDate() {
    $tomorrow = new DateTimeImmutable('tomorrow');
    $startDate = CRM_Contract_DateHelper::findNextOfDays([1], $tomorrow->format('Y-m-d'));
    $oneMonth = new DateInterval('P1M');
    $oneMonthFromNow = DateTimeImmutable::createFromMutable($startDate)->add($oneMonth);
    $twoMonthsFromNow = $oneMonthFromNow->add($oneMonth);

    $recurContribID = CRM_Contract_PaymentAdapter_Adyen::create([
      'amount'               => 10.0,
      'contact_id'           => $this->contact['id'],
      'cycle_day'            => 1,
      'frequency_interval'   => 1,
      'frequency_unit'       => 'month',
      'payment_processor_id' => $this->paymentProcessor['id'],
      'payment_token_id'     => $this->paymentToken['id'],
      'start_date'           => $startDate->format('Y-m-d'),
    ]);

    $recurringContribution = CRM_Contract_RecurringContribution::getById($recurContribID);

    $contribution = Api4\Contribution::create(FALSE)
      ->addValue('contact_id'            , $this->contact['id'])
      ->addValue('contribution_recur_id' , $recurContribID)
      ->addValue('financial_type_id.name', 'Member Dues')
      ->addValue('receive_date'          , $startDate->format('Y-m-d'))
      ->addValue('total_amount'          , 10.0)
      ->execute()
      ->first();

    $hookEvent = GenericHookEvent::create([
      'contribution_recur_id' => $recurContribID,
      'cycle_day'             => $recurringContribution['cycle_day'],
      'frequency_interval'    => $recurringContribution['frequency_interval'],
      'frequency_unit'        => $recurringContribution['frequency_unit'],
      'newDate'               => $twoMonthsFromNow->format('Y-m-d'),
      'originalDate'          => $recurringContribution['next_sched_contribution_date'],
    ]);

    Civi::dispatcher()->dispatch('civi.recur.nextschedcontributiondatealter', $hookEvent);

    $recurringContribution = Api4\ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $recurContribID)
      ->addSelect('next_sched_contribution_date')
      ->execute()
      ->first();

    $nextSchedContribDate = new DateTimeImmutable(
      $recurringContribution['next_sched_contribution_date']
    );

    $this->assertEquals(
      $oneMonthFromNow->format('Y-m-d'),
      $nextSchedContribDate->format('Y-m-d')
    );
  }

  public function testPauseAndResume() {

    // --- Create a payment --- //
    $recurId = CRM_Contract_PaymentAdapter_Adyen::create([
      'amount'                   => 10.0,
      'contact_id'               => $this->contact['id'],
      'payment_processor_id'     => $this->paymentProcessor['id'],
      'payment_token_id'         => $this->paymentToken['id'],
    ]);

    $recurContribQuery = Api4\ContributionRecur::get(FALSE)
      ->addSelect('contribution_status_id:name')
      ->addWhere('id', '=', $recurId)
      ->execute();

    $recurringContribution = $recurContribQuery->first();
    $this->assertEquals('In Progress', $recurringContribution['contribution_status_id:name']);

    // --- Pause the payment --- //

    CRM_Contract_PaymentAdapter_Adyen::pause($recurringContribution['id']);

    // --- Assert the payment has been paused --- //

    $recurringContribution = Api4\ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $recurringContribution['id'])
      ->addSelect('contribution_status_id:name')
      ->execute()
      ->first();

    $this->assertEquals('Paused', $recurringContribution['contribution_status_id:name']);

    // --- Resume the payment --- //

    CRM_Contract_PaymentAdapter_Adyen::resume($recurringContribution['id']);

    // --- Assert the payment has been resumed --- //

    $recurringContribution = Api4\ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $recurringContribution['id'])
      ->addSelect('contribution_status_id:name')
      ->execute()
      ->first();

    $this->assertEquals('In Progress', $recurringContribution['contribution_status_id:name']);

  }

  public function testRevive() {

    // --- Create a payment --- //

    $tomorrow = new DateTimeImmutable('tomorrow');
    $startDate = CRM_Contract_DateHelper::findNextOfDays([15], $tomorrow->format('Y-m-d'));

    $recurContribID = CRM_Contract_PaymentAdapter_Adyen::create([
      'amount'               => 20.0,
      'contact_id'           => $this->contact['id'],
      'cycle_day'            => 15,
      'frequency_interval'   => 2,
      'frequency_unit'       => 'month',
      'payment_processor_id' => $this->paymentProcessor['id'],
      'payment_token_id'     => $this->paymentToken['id'],
      'start_date'           => $startDate->format('Y-m-d'),
    ]);

    // --- Add a contribution for that payment --- //

    $firstContribDate = new DateTimeImmutable('2021-12-15');

    Api4\Contribution::create(FALSE)
      ->addValue('contact_id',             $this->contact['id'])
      ->addValue('contribution_recur_id',  $recurContribID)
      ->addValue('financial_type_id.name', 'Member Dues')
      ->addValue('receive_date',           $firstContribDate->format('Y-m-d'))
      ->addValue('total_amount',           20.0)
      ->execute();

    // --- Terminate the payment --- //

    CRM_Contract_PaymentAdapter_Adyen::terminate($recurContribID);

    // --- Assert the payment has been terminated --- //

    $cancelledRC = Api4\ContributionRecur::get(FALSE)
      ->addSelect(
        'amount',
        'cancel_date',
        'cancel_reason',
        'contribution_status_id:name',
        'cycle_day',
        'frequency_interval',
        'frequency_unit:name',
        'payment_token_id',
        'start_date'
      )
      ->addWhere('id', '=', $recurContribID)
      ->execute()
      ->first();

    $this->assertEquals([
      'amount'                      => 20.0,
      'cancel_date'                 => $cancelledRC['cancel_date'],
      'cancel_reason'               => 'CHNG',
      'contribution_status_id:name' => 'Completed',
      'cycle_day'                   => 15,
      'frequency_interval'          => 2,
      'frequency_unit:name'         => 'month',
      'id'                          => $recurContribID,
      'payment_token_id'            => $cancelledRC['payment_token_id'],
      'start_date'                  => $startDate->format('Y-m-d H:i:s'),
    ], $cancelledRC);

    $this->assertNotNull($cancelledRC['cancel_date']);

    // --- Revive the payment --- //

    $threeMonths = new DateInterval('P3M');

    $reviveDate = CRM_Contract_DateHelper::findNextOfDays(
      [28],
      $startDate->format('Y-m-d')
    )->add($threeMonths);

    $newRecurContribID = CRM_Contract_PaymentAdapter_Adyen::revive($recurContribID, [
      'amount'     => 15.0,
      'cycle_day'  => 28,
      'start_date' => $reviveDate->format('Y-m-d'),
    ]);

    // Should have created a new recurring contribution
    $this->assertNotEquals($recurContribID, $newRecurContribID);

    $revivedRC = Api4\ContributionRecur::get(FALSE)
      ->addSelect(
        'amount',
        'cancel_date',
        'cancel_reason',
        'contribution_status_id:name',
        'cycle_day',
        'next_sched_contribution_date',
        'payment_token_id'
      )
      ->addWhere('id', '=', $newRecurContribID)
      ->execute()
      ->first();

    // --- Assert the payment has been revived --- //

    $this->assertEquals([
      'amount'                       => 15.0,
      'cancel_date'                  => NULL,
      'cancel_reason'                => NULL,
      'contribution_status_id:name'  => 'In Progress',
      'cycle_day'                    => 28,
      'id'                           => $newRecurContribID,
      'next_sched_contribution_date' => $reviveDate->format('Y-m-d H:i:s'),
      'payment_token_id'             => $cancelledRC['payment_token_id'],
    ], $revivedRC);

  }

  public function testTerminate() {

    // --- Create a payment --- //

    $recurContribID = CRM_Contract_PaymentAdapter_Adyen::create([
      'amount'               => 10.0,
      'contact_id'           => $this->contact['id'],
      'payment_processor_id' => $this->paymentProcessor['id'],
      'payment_token_id'     => $this->paymentToken['id'],
    ]);

    $recurContribQuery = Api4\ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $recurContribID)
      ->addSelect('contribution_status_id:name')
      ->execute();

    $this->assertEquals(1, $recurContribQuery->rowCount);

    $recurringContribution = $recurContribQuery->first();
    $this->assertEquals('In Progress', $recurringContribution['contribution_status_id:name']);

    // --- Terminate the payment --- //

    CRM_Contract_PaymentAdapter_Adyen::terminate($recurringContribution['id']);

    // --- Assert recurring contribution has been cancelled --- //

    $recurringContribution = Api4\ContributionRecur::get(FALSE)
      ->addSelect(
        'cancel_date',
        'cancel_reason',
        'contribution_status_id:name',
        'end_date',
        // 'next_sched_contribution_date'
      )
      ->addWhere('id', '=', $recurringContribution['id'])
      ->execute()
      ->first();

    $this->assertEquals([
      'cancel_date'                  => $recurringContribution['cancel_date'],
      'cancel_reason'                => 'CHNG',
      'contribution_status_id:name'  => 'Completed',
      'end_date'                     => $recurringContribution['end_date'],
      'id'                           => $recurringContribution['id'],
      // 'next_sched_contribution_date' => NULL,
    ], $recurringContribution);

    // Recurring contribution should have ended within the last minute
    $this->assertLessThan(60, time() - strtotime($recurringContribution['cancel_date']));
    $this->assertLessThan(60, time() - strtotime($recurringContribution['end_date']));

  }

  public function testUpdate() {

    // --- Create a payment --- //

    $tomorrow = new DateTimeImmutable('tomorrow');
    $startDate = CRM_Contract_DateHelper::findNextOfDays([13], $tomorrow->format('Y-m-d'));

    $donationTypeID = self::getFinancialTypeID('Donation');
    $memberDuesTypeID = self::getFinancialTypeID('Member Dues');

    $inProgressOptVal = (int) $this->getOptionValue('contribution_recur_status', 'In Progress');

    $recurContribID = CRM_Contract_PaymentAdapter_Adyen::create([
      'amount'                => 10.0,
      'campaign_id'           => $this->campaign['id'],
      'contact_id'            => $this->contact['id'],
      'currency'              => 'EUR',
      'cycle_day'             => 13,
      'financial_type_id'     => $memberDuesTypeID,
      'frequency_interval'    => 1,
      'frequency_unit'        => 'month',
      'payment_processor_id'  => $this->paymentProcessor['id'],
      'payment_token_id'      => $this->paymentToken['id'],
      'start_date'            => $startDate->format('Y-m-d'),
    ]);

    $recurringContribution = Api4\ContributionRecur::get(FALSE)
      ->addSelect(
        'amount',
        'campaign_id',
        'contribution_status_id:name',
        'currency',
        'cycle_day',
        'financial_type_id',
        'frequency_interval',
        'frequency_unit',
        'next_sched_contribution_date',
        'payment_instrument_id',
        'payment_token_id',
        'processor_id',
        'start_date',
        'trxn_id'
      )
      ->addWhere('id', '=', $recurContribID)
      ->execute()
      ->first();

    $this->assertEquals([
      'amount'                       => 10.0,
      'campaign_id'                  => $this->campaign['id'],
      'contribution_status_id:name'  => 'In Progress',
      'currency'                     => 'EUR',
      'cycle_day'                    => 13,
      'financial_type_id'            => $memberDuesTypeID,
      'frequency_interval'           => 1,
      'frequency_unit'               => 'month',
      'id'                           => $recurringContribution['id'],
      'next_sched_contribution_date' => $startDate->format('Y-m-d H:i:s'),
      'payment_instrument_id'        => $this->paymentToken['payment_instrument_id'],
      'payment_token_id'             => $recurringContribution['payment_token_id'],
      'processor_id'                 => self::SHOPPER_REFERENCE,
      'start_date'                   => $startDate->format('Y-m-d H:i:s'),
      'trxn_id'                      => NULL,
    ], $recurringContribution);

    // --- Update the payment --- //

    $oneMonth = new DateInterval('P1M');

    $newStartDate = CRM_Contract_DateHelper::findNextOfDays(
      [17],
      $startDate->format('Y-m-d')
    )->add($oneMonth);

    $newRecurContribID = CRM_Contract_PaymentAdapter_Adyen::update($recurringContribution['id'], [
      'amount'                 => 20.0,
      'campaign_id'            => NULL,
      'contribution_status_id' => $inProgressOptVal,
      'currency'               => 'USD',
      'cycle_day'              => 17,
      'financial_type_id'      => $donationTypeID,
      'frequency_interval'     => 2,
      'frequency_unit'         => 'week',
      'start_date'             => $newStartDate->format('Y-m-d'),
    ]);

    // Should have created a new recurring contribution
    $this->assertNotEquals($newRecurContribID, $recurringContribution['id']);

    // --- Assert the payment has been updated --- //

    $newRecurringContribution = Api4\ContributionRecur::get(FALSE)
      ->addSelect(
        'amount',
        'campaign_id',
        'contribution_status_id:name',
        'currency',
        'cycle_day',
        'financial_type_id',
        'frequency_interval',
        'frequency_unit',
        'next_sched_contribution_date',
        'payment_instrument_id',
        'start_date',
        'trxn_id'
      )
      ->addWhere('id', '=', $newRecurContribID)
      ->execute()
      ->first();

    $this->assertEquals([
      'amount'                       => 20.0,
      'campaign_id'                  => $this->campaign['id'],
      'contribution_status_id:name'  => 'In Progress',
      'currency'                     => 'USD',
      'cycle_day'                    => 17,
      'financial_type_id'            => $donationTypeID,
      'frequency_interval'           => 2,
      'frequency_unit'               => 'week',
      'id'                           => $newRecurContribID,
      'next_sched_contribution_date' => $newStartDate->format('Y-m-d H:i:s'),
      'payment_instrument_id'        => $this->paymentToken['payment_instrument_id'],
      'start_date'                   => $newStartDate->format('Y-m-d H:i:s'),
      'trxn_id'                      => NULL,
    ], $newRecurringContribution);

    // --- Assert the old payment has been terminated --- //

    $oldRCStatus = Api4\ContributionRecur::get(FALSE)
      ->addSelect('contribution_status_id:name')
      ->addWhere('id', '=', $recurringContribution['id'])
      ->execute()
      ->first()['contribution_status_id:name'];

    $this->assertEquals('Completed', $oldRCStatus);

  }

  private function createPaymentProcessor() {
    $createPaymentProcResult = Api4\PaymentProcessor::create(FALSE)
      ->addValue('financial_account_id.name',      'Payment Processor Account')
      ->addValue('name',                           'adyen')
      ->addValue('payment_processor_type_id:name', 'Adyen')
      ->addValue('title',                          'Adyen')
      ->execute();

    $this->paymentProcessor = $createPaymentProcResult->first();
  }

  private function createPaymentToken() {
    $expiryDate = new DateTime('+1 year');

    $createPaymentTokenResult = Api4\PaymentToken::create(FALSE)
      ->addValue('billing_first_name'   , $this->contact['first_name'])
      ->addValue('billing_last_name'    , $this->contact['last_name'])
      ->addValue('contact_id'           , $this->contact['id'])
      ->addValue('email'                , $this->contact['email'])
      ->addValue('expiry_date'          , $expiryDate->format('Y-m-d 00:00:00'))
      ->addValue('ip_address'           , '127.0.0.1')
      ->addValue('masked_account_number', self::BANK_ACCOUNT_NUMBER)
      ->addValue('payment_processor_id' , $this->paymentProcessor['id'])
      ->addValue('token'                , self::STORED_PAYMENT_METHOD_ID)
      ->execute();

    $this->paymentToken = $createPaymentTokenResult->first();

    $recurringContribution = Api4\ContributionRecur::create(FALSE)
      ->addValue('amount'                    ,  0.0)
      ->addValue('contact_id'                ,  $this->contact['id'])
      ->addValue('financial_type_id.name'    , 'Member Dues')
      ->addValue('payment_instrument_id:name', 'Credit Card')
      ->addValue('payment_token_id'          , $this->paymentToken['id'])
      ->addValue('processor_id'              , self::SHOPPER_REFERENCE)
      ->addValue('trxn_id'                   , NULL)
      ->execute()
      ->first();

    $this->paymentToken['payment_instrument_id'] = $recurringContribution['payment_instrument_id'];
  }

}

?>
