<?php

use Civi\Api4;

/**
 * @group headless
 */
class api_v3_Contract_StartDateTest extends api_v3_Contract_ContractTestBase {

  public function testStartDateAdyen() {

    // Adyen: Case 1

    $start_date_params = [
      '_today'          => '2023-01-15',
      'payment_adapter' => 'adyen',
    ];

    $this->assertEquals('2023-01-15', $this->getStartDate($start_date_params));

    // Adyen: Case 2

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'payment_adapter' => 'adyen',
    ];

    $this->assertEquals('2023-02-13', $this->getStartDate($start_date_params));

    // Adyen: Case 3

    $start_date_params = [
      '_today'          => '2023-01-15',
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'adyen',
    ];

    $this->assertEquals('2023-03-01', $this->getStartDate($start_date_params));

    // Adyen: Case 4

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'adyen',
    ];

    $this->assertEquals('2023-03-13', $this->getStartDate($start_date_params));

    // Adyen: Case 5

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 33,
      'payment_adapter' => 'adyen',
    ];

    $start_date_result = civicrm_api(
      'Contract',
      'start_date',
      $start_date_params + [ 'version' => 3 ]
    );

    $this->assertEquals(1, $start_date_result['is_error']);

    $this->assertEquals(
      'Cycle day 33 is not allowed for Adyen payments',
      $start_date_result['error_message']
    );

    // Adyen: Case 6

    $membership = $this->createMembership('2023-01-15');

    $recurring_contribution = $this->createRecurringContribution([
      'cycle_day'       => 17,
      'is_active'       => TRUE,
      'membership_id'   => $membership['id'],
      'payment_adapter' => 'adyen',
      'start_date'      => '2022-02-17',
    ]);

    $start_date_params = [
      '_today'        => '2023-01-15',
      'membership_id' => $membership['id'],
    ];

    $this->assertEquals('2023-01-17', $this->getStartDate($start_date_params));

    // Adyen: Case 7

    $membership = $this->createMembership('2023-01-15');

    $recurring_contribution = $this->createRecurringContribution([
      'cycle_day'          => 1,
      'frequency_interval' => 2,
      'frequency_unit'     => 'month',
      'is_active'          => TRUE,
      'membership_id'      => $membership['id'],
      'payment_adapter'    => 'adyen',
      'start_date'         => '2022-01-01',
    ]);

    $this->createContribution([
      'amount'                    => 10.0,
      'date'                      => '2023-01-05',
      'membership_id'             => $membership['id'],
      'recurring_contribution_id' => $recurring_contribution['id'],
    ]);

    $start_date_params = [
      '_today'              => '2023-01-15',
      'defer_payment_start' => TRUE,
      'membership_id'       => $membership['id'],
    ];

    $this->assertEquals('2023-03-01', $this->getStartDate($start_date_params));

  }

  public function testStartDateEFT() {

    // EFT: Case 1

    $start_date_params = [
      '_today'          => '2023-01-15',
      'payment_adapter' => 'eft',
    ];

    $this->assertEquals('2023-01-15', $this->getStartDate($start_date_params));

    // EFT: Case 2

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'payment_adapter' => 'eft',
    ];

    $this->assertEquals('2023-02-13', $this->getStartDate($start_date_params));

    // EFT: Case 3

    $start_date_params = [
      '_today'          => '2023-01-15',
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'eft',
    ];

    $this->assertEquals('2023-03-01', $this->getStartDate($start_date_params));

    // EFT: Case 4

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'eft',
    ];

    $this->assertEquals('2023-03-13', $this->getStartDate($start_date_params));

    // EFT: Case 5

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 33,
      'payment_adapter' => 'eft',
    ];

    $start_date_result = civicrm_api(
      'Contract',
      'start_date',
      $start_date_params + [ 'version' => 3 ]
    );

    $this->assertEquals(1, $start_date_result['is_error']);

    $this->assertEquals(
      'Cycle day 33 is not allowed for EFT payments',
      $start_date_result['error_message']
    );

    // EFT: Case 6

    $membership = $this->createMembership('2023-01-15');

    $recur_contrib_id = $this->createRecurringContribution([
      'cycle_day'       => 5,
      'is_active'       => TRUE,
      'membership_id'   => $membership['id'],
      'payment_adapter' => 'eft',
      'start_date'      => '2022-03-01',
    ]);

    $start_date_params = [
      '_today'        => '2023-01-15',
      'membership_id' => $membership['id'],
    ];

    $this->assertEquals('2023-02-05', $this->getStartDate($start_date_params));

    // EFT: Case 7

    $membership = $this->createMembership('2023-01-15');

    $recurring_contribution = $this->createRecurringContribution([
      'cycle_day'          => 5,
      'frequency_interval' => 1,
      'frequency_unit'     => 'year',
      'is_active'          => TRUE,
      'membership_id'      => $membership['id'],
      'payment_adapter'    => 'eft',
      'start_date'         => '2023-01-05',
    ]);

    $this->createContribution([
      'amount'                    => 10.0,
      'date'                      => '2023-01-05',
      'membership_id'             => $membership['id'],
      'recurring_contribution_id' => $recurring_contribution['id'],
    ]);

    $start_date_params = [
      '_today'              => '2023-01-15',
      'defer_payment_start' => TRUE,
      'membership_id'       => $membership['id'],
    ];

    $this->assertEquals('2024-01-05', $this->getStartDate($start_date_params));

  }

  public function testStartDateSEPA() {
    CRM_Sepa_Logic_Settings::setSetting(
      implode(',', [7, 14, 21, 28]),
      'cycledays',
      $this->sepaCreditor['id']
    );

    CRM_Sepa_Logic_Settings::setSetting(10, 'batching.RCUR.notice', $this->sepaCreditor['id']);

    // SEPA: Case 1

    $start_date_params = [
      '_today'          => '2023-01-15',
      'payment_adapter' => 'sepa_mandate',
    ];

    $this->assertEquals('2023-01-28', $this->getStartDate($start_date_params));

    // SEPA: Case 2

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 14,
      'payment_adapter' => 'sepa_mandate',
    ];

    $this->assertEquals('2023-02-14', $this->getStartDate($start_date_params));

    // SEPA: Case 3

    $start_date_params = [
      '_today'          => '2023-01-15',
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'sepa_mandate',
    ];

    $this->assertEquals('2023-03-07', $this->getStartDate($start_date_params));

    // SEPA: Case 4

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 14,
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'sepa_mandate',
    ];

    $this->assertEquals('2023-03-14', $this->getStartDate($start_date_params));

    // SEPA: Case 5

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'payment_adapter' => 'sepa_mandate',
    ];

    $start_date_result = civicrm_api(
      'Contract',
      'start_date',
      $start_date_params + [ 'version' => 3 ]
    );

    $this->assertEquals(1, $start_date_result['is_error']);

    $this->assertEquals(
      'Cycle day 13 is not allowed for this SEPA creditor',
      $start_date_result['error_message']
    );

    // SEPA: Case 6

    $membership = $this->createMembership('2023-01-15');

    $recur_contrib_id = $this->createRecurringContribution([
      'cycle_day'       => 28,
      'is_active'       => TRUE,
      'membership_id'   => $membership['id'],
      'payment_adapter' => 'sepa_mandate',
      'start_date'      => '2022-01-28',
    ]);

    $start_date_params = [
      '_today'        => '2023-01-15',
      'membership_id' => $membership['id'],
    ];

    $this->assertEquals('2023-01-28', $this->getStartDate($start_date_params));

    // SEPA: Case 7

    $membership = $this->createMembership('2023-01-15');

    $recurring_contribution = $this->createRecurringContribution([
      'cycle_day'          => 14,
      'frequency_interval' => 6,
      'frequency_unit'     => 'month',
      'is_active'          => TRUE,
      'membership_id'      => $membership['id'],
      'payment_adapter'    => 'sepa_mandate',
      'start_date'         => '2022-01-14',
    ]);

    $this->createContribution([
      'amount'                    => 10.0,
      'date'                      => '2023-01-17',
      'membership_id'             => $membership['id'],
      'recurring_contribution_id' => $recurring_contribution['id'],
    ]);

    $start_date_params = [
      '_today'              => '2023-01-15',
      'defer_payment_start' => TRUE,
      'membership_id'       => $membership['id'],
    ];

    $this->assertEquals('2023-07-14', $this->getStartDate($start_date_params));

  }

  private function createMembership(string $start_date) {
    return Api4\Membership::create(FALSE)
      ->addValue('contact_id'             , $this->contact['id'])
      ->addValue('membership_type_id.name', 'General')
      ->addValue('start_date'             , $start_date)
      ->addValue('status_id.name'         , 'Current')
      ->execute()
      ->first();
  }

  private function createRecurringContribution(array $params) {
    $contribution_status = $params['is_active'] ? 'In Progress' : 'Completed';
    $frequency_interval = CRM_Utils_Array::value('frequency_interval', $params, 1);
    $frequency_unit = CRM_Utils_Array::value('frequency_unit', $params, 'month');

    $api_call = Api4\ContributionRecur::create(FALSE)
      ->addValue('amount'                      , 10.0)
      ->addValue('contact_id'                  , $this->contact['id'])
      ->addValue('contribution_status_id:name' , $contribution_status)
      ->addValue('create_date'                 , $params['start_date'])
      ->addValue('cycle_day'                   , $params['cycle_day'])
      ->addValue('frequency_interval'          , $frequency_interval)
      ->addValue('frequency_unit'              , $frequency_unit)
      ->addValue('start_date'                  , $params['start_date']);

    switch ($params['payment_adapter']) {
      case 'adyen': {
        $recurring_contribution = $api_call
          ->addValue('payment_processor_id', $this->adyenProcessor['id'])
          ->execute()
          ->first();

        break;
      }

      case 'eft': {
        $recurring_contribution = $api_call
          ->addValue('payment_instrument_id:name', 'EFT')
          ->execute()
          ->first();

        break;
      }

      case 'sepa_mandate': {
        $recurring_contribution = $api_call
          ->addValue('payment_instrument_id:name', 'RCUR')
          ->execute()
          ->first();

        $sepa_mandate = Api4\SepaMandate::create(FALSE)
          ->addValue('creditor_id' , $this->sepaCreditor['id'])
          ->addValue('date'        , $params['start_date'])
          ->addValue('entity_id'   , $recurring_contribution['id'])
          ->addValue('entity_table', 'civicrm_contribution_recur')
          ->addValue('reference'   , bin2hex(random_bytes(8)))
          ->execute()
          ->first();

        break;
      }

      default:
        return NULL;
    }

    Api4\Membership::update(FALSE)
      ->addWhere('id', '=', $params['membership_id'])
      ->addValue(
        'membership_payment.membership_recurring_contribution',
        $recurring_contribution['id']
      )
      ->execute();

    civicrm_api3('ContractPaymentLink', 'create', [
      'contract_id'           => $params['membership_id'],
      'contribution_recur_id' => $recurring_contribution['id'],
      'is_active'             => $params['is_active'],
    ]);

    return $recurring_contribution;
  }

  private function getStartDate(array $params) {
    $result = civicrm_api3('Contract', 'start_date', $params);

    return $result['values'][0];
  }

}

?>
