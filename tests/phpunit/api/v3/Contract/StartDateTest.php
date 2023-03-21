<?php

use Civi\Api4;

/**
 * @group headless
 */
class api_v3_Contract_StartDateTest extends api_v3_Contract_DateTestBase {

  public function testStartDateAdyen() {

    // Case 1

    $start_date_params = [
      '_today'          => '2023-01-15',
      'payment_adapter' => 'adyen',
    ];

    $this->assertEquals('2023-01-15', $this->getStartDate($start_date_params));

    // Case 2

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'payment_adapter' => 'adyen',
    ];

    $this->assertEquals('2023-02-13', $this->getStartDate($start_date_params));

    // Case 3

    $start_date_params = [
      '_today'          => '2023-01-15',
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'adyen',
    ];

    $this->assertEquals('2023-03-01', $this->getStartDate($start_date_params));

    // Case 4

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'adyen',
    ];

    $this->assertEquals('2023-03-13', $this->getStartDate($start_date_params));

    // Case 5

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

    // Case 6

    $recur_contrib_id = $this->createRecurringContribution([
      'cycle_day'       => 17,
      'payment_adapter' => 'adyen',
      'start_date'      => '2022-02-17',
    ]);

    $start_date_params = [
      '_today'                => '2023-01-15',
      'prev_recur_contrib_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2023-01-17', $this->getStartDate($start_date_params));

    // Case 7

    $recur_contrib_id = $this->createRecurringContribution([
      'cycle_day'          => 1,
      'frequency_interval' => 2,
      'frequency_unit'     => 'month',
      'payment_adapter'    => 'adyen',
      'start_date'         => '2022-02-01',
    ]);

    $this->createContribution([
      'date'                      => '2022-12-31',
      'recurring_contribution_id' => $recur_contrib_id,
    ]);

    $start_date_params = [
      '_today'                => '2023-01-15',
      'cycle_day'             => 17,
      'defer_payment_start'   => TRUE,
      'prev_recur_contrib_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2023-03-17', $this->getStartDate($start_date_params));

  }

  public function testStartDateEFT() {

    // Case 1

    $start_date_params = [
      '_today'          => '2023-01-15',
      'payment_adapter' => 'eft',
    ];

    $this->assertEquals('2023-01-15', $this->getStartDate($start_date_params));

    // Case 2

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'payment_adapter' => 'eft',
    ];

    $this->assertEquals('2023-02-13', $this->getStartDate($start_date_params));

    // Case 3

    $start_date_params = [
      '_today'          => '2023-01-15',
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'eft',
    ];

    $this->assertEquals('2023-03-01', $this->getStartDate($start_date_params));

    // Case 4

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'eft',
    ];

    $this->assertEquals('2023-03-13', $this->getStartDate($start_date_params));

    // Case 5

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

    // Case 6

    $recur_contrib_id = $this->createRecurringContribution([
      'cycle_day'       => 5,
      'payment_adapter' => 'eft',
      'start_date'      => '2022-03-01',
    ]);

    $start_date_params = [
      '_today'                => '2023-01-15',
      'prev_recur_contrib_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2023-02-05', $this->getStartDate($start_date_params));

    // Case 7

    $recur_contrib_id = $this->createRecurringContribution([
      'cycle_day'          => 5,
      'frequency_interval' => 1,
      'frequency_unit'     => 'year',
      'payment_adapter'    => 'eft',
      'start_date'         => '2023-01-01',
    ]);

    $this->createContribution([
      'date'                      => '2023-01-05',
      'recurring_contribution_id' => $recur_contrib_id,
    ]);

    $start_date_params = [
      '_today'                => '2023-01-15',
      'defer_payment_start'   => TRUE,
      'prev_recur_contrib_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2024-01-05', $this->getStartDate($start_date_params));

  }

  public function testStartDatePSP() {
    CRM_Sepa_Logic_Settings::setSetting(
      implode(',', [5, 10, 15, 20, 25]),
      'cycledays',
      $this->pspCreditor['id']
    );

    CRM_Sepa_Logic_Settings::setSetting(7, 'batching.RCUR.notice', $this->pspCreditor['id']);

    // Case 1

    $start_date_params = [
      '_today'          => '2023-01-15',
      'creditor_id'     => $this->pspCreditor['id'],
      'payment_adapter' => 'psp_sepa',
    ];

    $this->assertEquals('2023-01-25', $this->getStartDate($start_date_params));

    // Case 2

    $start_date_params = [
      '_today'          => '2023-01-15',
      'creditor_id'     => $this->pspCreditor['id'],
      'cycle_day'       => 10,
      'payment_adapter' => 'psp_sepa',
    ];

    $this->assertEquals('2023-02-10', $this->getStartDate($start_date_params));

    // Case 3

    $start_date_params = [
      '_today'          => '2023-01-15',
      'creditor_id'     => $this->pspCreditor['id'],
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'psp_sepa',
    ];

    $this->assertEquals('2023-03-05', $this->getStartDate($start_date_params));

    // Case 4

    $start_date_params = [
      '_today'          => '2023-01-15',
      'creditor_id'     => $this->pspCreditor['id'],
      'cycle_day'       => 10,
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'psp_sepa',
    ];

    $this->assertEquals('2023-03-10', $this->getStartDate($start_date_params));

    // Case 5

    $start_date_params = [
      '_today'          => '2023-01-15',
      'creditor_id'     => $this->pspCreditor['id'],
      'cycle_day'       => 13,
      'payment_adapter' => 'psp_sepa',
    ];

    $start_date_result = civicrm_api(
      'Contract',
      'start_date',
      $start_date_params + [ 'version' => 3 ]
    );

    $this->assertEquals(1, $start_date_result['is_error']);

    $this->assertEquals(
      'Cycle day 13 is not allowed for this PSP creditor',
      $start_date_result['error_message']
    );

    // Case 6

    $recur_contrib_id = $this->createRecurringContribution([
      'cycle_day'       => 20,
      'payment_adapter' => 'psp_sepa',
      'start_date'      => '2022-02-01',
    ]);

    $start_date_params = [
      '_today'                => '2023-01-15',
      'prev_recur_contrib_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2023-02-20', $this->getStartDate($start_date_params));

    // Case 7

    $recur_contrib_id = $this->createRecurringContribution([
      'cycle_day'          => 5,
      'frequency_interval' => 3,
      'frequency_unit'     => 'month',
      'payment_adapter'    => 'psp_sepa',
      'start_date'         => '2022-01-05',
    ]);

    $this->createContribution([
      'date'                      => '2023-01-05',
      'recurring_contribution_id' => $recur_contrib_id,
    ]);

    $start_date_params = [
      '_today'                => '2023-01-15',
      'defer_payment_start'   => TRUE,
      'prev_recur_contrib_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2023-04-05', $this->getStartDate($start_date_params));

  }

  public function testStartDateSEPA() {
    CRM_Sepa_Logic_Settings::setSetting(
      implode(',', [7, 14, 21, 28]),
      'cycledays',
      $this->sepaCreditor['id']
    );

    CRM_Sepa_Logic_Settings::setSetting(10, 'batching.RCUR.notice', $this->sepaCreditor['id']);

    // Case 1

    $start_date_params = [
      '_today'          => '2023-01-15',
      'payment_adapter' => 'sepa_mandate',
    ];

    $this->assertEquals('2023-01-28', $this->getStartDate($start_date_params));

    // Case 2

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 14,
      'payment_adapter' => 'sepa_mandate',
    ];

    $this->assertEquals('2023-02-14', $this->getStartDate($start_date_params));

    // Case 3

    $start_date_params = [
      '_today'          => '2023-01-15',
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'sepa_mandate',
    ];

    $this->assertEquals('2023-03-07', $this->getStartDate($start_date_params));

    // Case 4

    $start_date_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 14,
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'sepa_mandate',
    ];

    $this->assertEquals('2023-03-14', $this->getStartDate($start_date_params));

    // Case 5

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

    // Case 6

    $recur_contrib_id = $this->createRecurringContribution([
      'cycle_day'       => 28,
      'payment_adapter' => 'sepa_mandate',
      'start_date'      => '2022-01-28',
    ]);

    $start_date_params = [
      '_today'                => '2023-01-15',
      'prev_recur_contrib_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2023-01-28', $this->getStartDate($start_date_params));

    // Case 7

    $recur_contrib_id = $this->createRecurringContribution([
      'cycle_day'          => 14,
      'frequency_interval' => 6,
      'frequency_unit'     => 'month',
      'payment_adapter'    => 'sepa_mandate',
      'start_date'         => '2022-01-14',
    ]);

    $this->createContribution([
      'date'                      => '2023-01-14',
      'recurring_contribution_id' => $recur_contrib_id,
    ]);

    $start_date_params = [
      '_today'                => '2023-01-15',
      'defer_payment_start'   => TRUE,
      'prev_recur_contrib_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2023-07-14', $this->getStartDate($start_date_params));

  }

  private function createContribution(array $params) {
    Api4\Contribution::create(FALSE)
      ->addValue('contact_id'            , $this->contact['id'])
      ->addValue('contribution_recur_id' , $params['recurring_contribution_id'])
      ->addValue('financial_type_id.name', 'Member Dues')
      ->addValue('receive_date'          , $params['date'])
      ->addValue('total_amount'          , 30.0)
      ->execute();
  }

  private function createRecurringContribution(array $params) {
    $frequency_interval = CRM_Utils_Array::value('frequency_interval', $params, 1);
    $frequency_unit = CRM_Utils_Array::value('frequency_unit', $params, 'month');

    $api_call = Api4\ContributionRecur::create(FALSE)
      ->addValue('amount'             , 10.0)
      ->addValue('contact_id'         , $this->contact['id'])
      ->addValue('create_date'        , $params['start_date'])
      ->addValue('cycle_day'          , $params['cycle_day'])
      ->addValue('frequency_interval' , $frequency_interval)
      ->addValue('frequency_unit'     , $frequency_unit)
      ->addValue('start_date'         , $params['start_date']);

    switch ($params['payment_adapter']) {
      case 'adyen': {
        $recurring_contribution = $api_call
          ->addValue('payment_processor_id', $this->adyenProcessor['id'])
          ->execute()
          ->first();

        return $recurring_contribution['id'];
      }

      case 'eft': {
        $recurring_contribution = $api_call
          ->addValue('payment_instrument_id:name', 'EFT')
          ->execute()
          ->first();

        return $recurring_contribution['id'];
      }

      case 'psp_sepa': {
        $recurring_contribution = $api_call
          ->addValue('payment_instrument_id:name', 'RCUR')
          ->execute()
          ->first();

        $sepa_mandate = Api4\SepaMandate::create(FALSE)
          ->addValue('creditor_id' , $this->pspCreditor['id'])
          ->addValue('date'        , $params['start_date'])
          ->addValue('entity_id'   , $recurring_contribution['id'])
          ->addValue('entity_table', 'civicrm_contribution_recur')
          ->addValue('reference'   , bin2hex(random_bytes(8)))
          ->execute()
          ->first();

        return $recurring_contribution['id'];
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

        return $recurring_contribution['id'];
      }

      default:
        return NULL;
    }
  }

  private function getStartDate(array $params) {
    $result = civicrm_api3('Contract', 'start_date', $params);

    return $result['values'][0];
  }

}

?>
