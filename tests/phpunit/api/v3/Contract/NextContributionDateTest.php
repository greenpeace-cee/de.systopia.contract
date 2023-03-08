<?php

use Civi\Api4;

/**
 * @group headless
 */
class api_v3_Contract_NextContributionDateTest extends api_v3_Contract_DateTestBase {

  public function testNextContributionDateAdyen() {

    // Case 1

    $ncd_params = [
      '_today'          => '2023-01-15',
      'payment_adapter' => 'adyen',
    ];

    $this->assertEquals('2023-01-15', $this->getNextContributionDate($ncd_params));

    // Case 2

    $ncd_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'payment_adapter' => 'adyen',
    ];

    $this->assertEquals('2023-02-13', $this->getNextContributionDate($ncd_params));

    // Case 3

    $ncd_params = [
      '_today'          => '2023-01-15',
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'adyen',
    ];

    $this->assertEquals('2023-03-01', $this->getNextContributionDate($ncd_params));

    // Case 4

    $ncd_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'adyen',
    ];

    $this->assertEquals('2023-03-13', $this->getNextContributionDate($ncd_params));

    // Case 5

    $ncd_params = [
      '_today'          => '2023-01-15',
      'creditor_id'     => $this->pspCreditor['id'],
      'cycle_day'       => 33,
      'payment_adapter' => 'adyen',
    ];

    $ncd_result = civicrm_api(
      'Contract',
      'next_contribution_date',
      $ncd_params + [ 'version' => 3 ]
    );

    $this->assertEquals(1, $ncd_result['is_error']);

    $this->assertEquals(
      'Cycle day 33 is not allowed for Adyen payments',
      $ncd_result['error_message']
    );

    // Case 6

    $recur_contrib_id = $this->createRecurringContribution([
      '_today'          => '2023-01-15',
      'cycle_day'       => 17,
      'payment_adapter' => 'adyen',
      'start_date'      => '2023-02-01',
    ]);

    $ncd_params = [
      '_today'                    => '2023-01-15',
      'recurring_contribution_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2023-02-17', $this->getNextContributionDate($ncd_params));

    // Case 7

    $recur_contrib_id = $this->createRecurringContribution([
      '_today'             => '2023-01-15',
      'cycle_day'          => 1,
      'frequency_interval' => 2,
      'frequency_unit'     => 'month',
      'payment_adapter'    => 'adyen',
      'start_date'         => '2023-02-01',
    ]);

    $this->createContribution([
      'date'                      => '2022-12-31',
      'recurring_contribution_id' => $recur_contrib_id,
    ]);

    $ncd_params = [
      '_today'                    => '2023-01-15',
      'recurring_contribution_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2023-03-01', $this->getNextContributionDate($ncd_params));

  }

  public function testNextContributionDateEFT() {

    // Case 1

    $ncd_params = [
      '_today'          => '2023-01-15',
      'payment_adapter' => 'eft',
    ];

    $this->assertEquals('2023-01-15', $this->getNextContributionDate($ncd_params));

    // Case 2

    $ncd_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'payment_adapter' => 'eft',
    ];

    $this->assertEquals('2023-02-13', $this->getNextContributionDate($ncd_params));

    // Case 3

    $ncd_params = [
      '_today'          => '2023-01-15',
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'eft',
    ];

    $this->assertEquals('2023-03-01', $this->getNextContributionDate($ncd_params));

    // Case 4

    $ncd_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'eft',
    ];

    $this->assertEquals('2023-03-13', $this->getNextContributionDate($ncd_params));

    // Case 5

    $ncd_params = [
      '_today'          => '2023-01-15',
      'creditor_id'     => $this->pspCreditor['id'],
      'cycle_day'       => 33,
      'payment_adapter' => 'eft',
    ];

    $ncd_result = civicrm_api(
      'Contract',
      'next_contribution_date',
      $ncd_params + [ 'version' => 3 ]
    );

    $this->assertEquals(1, $ncd_result['is_error']);

    $this->assertEquals(
      'Cycle day 33 is not allowed for EFT payments',
      $ncd_result['error_message']
    );

    // Case 6

    $recur_contrib_id = $this->createRecurringContribution([
      '_today'          => '2023-01-15',
      'cycle_day'       => 05,
      'payment_adapter' => 'eft',
      'start_date'      => '2023-03-01',
    ]);

    $ncd_params = [
      '_today'                    => '2023-01-15',
      'recurring_contribution_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2023-03-05', $this->getNextContributionDate($ncd_params));

    // Case 7

    $recur_contrib_id = $this->createRecurringContribution([
      '_today'             => '2023-01-15',
      'cycle_day'          => 20,
      'frequency_interval' => 1,
      'frequency_unit'     => 'year',
      'payment_adapter'    => 'eft',
      'start_date'         => '2023-01-20',
    ]);

    $this->createContribution([
      'date'                      => '2023-01-15',
      'recurring_contribution_id' => $recur_contrib_id,
    ]);

    $ncd_params = [
      '_today'                    => '2023-01-15',
      'recurring_contribution_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2024-01-20', $this->getNextContributionDate($ncd_params));

  }

  public function testNextContributionDatePSP() {
    CRM_Sepa_Logic_Settings::setSetting(
      implode(',', [5, 10, 15, 20, 25]),
      'cycledays',
      $this->pspCreditor['id']
    );

    // Case 1

    $ncd_params = [
      '_today'          => '2023-01-15',
      'creditor_id'     => $this->pspCreditor['id'],
      'payment_adapter' => 'psp_sepa',
    ];

    $this->assertEquals('2023-01-15', $this->getNextContributionDate($ncd_params));

    // Case 2

    $ncd_params = [
      '_today'          => '2023-01-15',
      'creditor_id'     => $this->pspCreditor['id'],
      'cycle_day'       => 10,
      'payment_adapter' => 'psp_sepa',
    ];

    $this->assertEquals('2023-02-10', $this->getNextContributionDate($ncd_params));

    // Case 3

    $ncd_params = [
      '_today'          => '2023-01-15',
      'creditor_id'     => $this->pspCreditor['id'],
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'psp_sepa',
    ];

    $this->assertEquals('2023-03-05', $this->getNextContributionDate($ncd_params));

    // Case 4

    $ncd_params = [
      '_today'          => '2023-01-15',
      'creditor_id'     => $this->pspCreditor['id'],
      'cycle_day'       => 10,
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'psp_sepa',
    ];

    $this->assertEquals('2023-03-10', $this->getNextContributionDate($ncd_params));

    // Case 5

    $ncd_params = [
      '_today'          => '2023-01-15',
      'creditor_id'     => $this->pspCreditor['id'],
      'cycle_day'       => 13,
      'payment_adapter' => 'psp_sepa',
    ];

    $ncd_result = civicrm_api(
      'Contract',
      'next_contribution_date',
      $ncd_params + [ 'version' => 3 ]
    );

    $this->assertEquals(1, $ncd_result['is_error']);

    $this->assertEquals(
      'Cycle day 13 is not allowed for this PSP creditor',
      $ncd_result['error_message']
    );

    // Case 6

    $recur_contrib_id = $this->createRecurringContribution([
      '_today'          => '2023-01-15',
      'cycle_day'       => 20,
      'payment_adapter' => 'psp_sepa',
      'start_date'      => '2023-02-01',
    ]);

    $ncd_params = [
      '_today'                    => '2023-01-15',
      'recurring_contribution_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2023-02-20', $this->getNextContributionDate($ncd_params));

    // Case 7

    $recur_contrib_id = $this->createRecurringContribution([
      '_today'             => '2023-01-15',
      'cycle_day'          => 5,
      'frequency_interval' => 3,
      'frequency_unit'     => 'month',
      'payment_adapter'    => 'psp_sepa',
      'start_date'         => '2023-01-16',
    ]);

    $this->createContribution([
      'date'                      => '2023-01-15',
      'recurring_contribution_id' => $recur_contrib_id,
    ]);

    $ncd_params = [
      '_today'                    => '2023-01-15',
      'recurring_contribution_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2023-05-05', $this->getNextContributionDate($ncd_params));

  }

  public function testNextContributionDateSEPA() {
    CRM_Sepa_Logic_Settings::setSetting(
      implode(',', [7, 14, 21, 28]),
      'cycledays',
      $this->sepaCreditor['id']
    );

    // Case 1

    $ncd_params = [
      '_today'          => '2023-01-15',
      'payment_adapter' => 'sepa_mandate',
    ];

    $this->assertEquals('2023-01-21', $this->getNextContributionDate($ncd_params));

    // Case 2

    $ncd_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 14,
      'payment_adapter' => 'sepa_mandate',
    ];

    $this->assertEquals('2023-02-14', $this->getNextContributionDate($ncd_params));

    // Case 3

    $ncd_params = [
      '_today'          => '2023-01-15',
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'sepa_mandate',
    ];

    $this->assertEquals('2023-03-07', $this->getNextContributionDate($ncd_params));

    // Case 4

    $ncd_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 14,
      'min_date'        => '2023-03-01',
      'payment_adapter' => 'sepa_mandate',
    ];

    $this->assertEquals('2023-03-14', $this->getNextContributionDate($ncd_params));

    // Case 5

    $ncd_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'payment_adapter' => 'sepa_mandate',
    ];

    $ncd_result = civicrm_api(
      'Contract',
      'next_contribution_date',
      $ncd_params + [ 'version' => 3 ]
    );

    $this->assertEquals(1, $ncd_result['is_error']);

    $this->assertEquals(
      'Cycle day 13 is not allowed for this SEPA creditor',
      $ncd_result['error_message']
    );

    // Case 6

    $recur_contrib_id = $this->createRecurringContribution([
      '_today'          => '2023-01-15',
      'cycle_day'       => 28,
      'payment_adapter' => 'sepa_mandate',
      'start_date'      => '2023-02-01',
    ]);

    $ncd_params = [
      '_today'                    => '2023-01-15',
      'recurring_contribution_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2023-02-28', $this->getNextContributionDate($ncd_params));

    // Case 7

    $recur_contrib_id = $this->createRecurringContribution([
      '_today'             => '2023-01-15',
      'cycle_day'          => 21,
      'frequency_interval' => 6,
      'frequency_unit'     => 'month',
      'payment_adapter'    => 'sepa_mandate',
      'start_date'         => '2023-01-16',
    ]);

    $this->createContribution([
      'date'                      => '2023-01-14',
      'recurring_contribution_id' => $recur_contrib_id,
    ]);

    $ncd_params = [
      '_today'                    => '2023-01-15',
      'recurring_contribution_id' => $recur_contrib_id,
    ];

    $this->assertEquals('2023-07-21', $this->getNextContributionDate($ncd_params));

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
      ->addValue('create_date'        , $params['_today'])
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
          ->addValue('date'        , $params['_today'])
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
          ->addValue('date'        , $params['_today'])
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

  private function getNextContributionDate(array $params) {
    $result = civicrm_api3('Contract', 'next_contribution_date', $params);

    return $result['values'][0];
  }

}

?>
