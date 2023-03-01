<?php

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
      'payment_adapter' => 'adyen',
      'start_date'      => '2023-03-01',
    ];

    $this->assertEquals('2023-03-01', $this->getNextContributionDate($ncd_params));

    // Case 4

    $ncd_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'payment_adapter' => 'adyen',
      'start_date'      => '2023-03-01',
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
      'payment_adapter' => 'eft',
      'start_date'      => '2023-03-01',
    ];

    $this->assertEquals('2023-03-01', $this->getNextContributionDate($ncd_params));

    // Case 4

    $ncd_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'payment_adapter' => 'eft',
      'start_date'      => '2023-03-01',
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

  }

  public function testNextContributionDatePSP() {
    CRM_Sepa_Logic_Settings::setSetting(
      implode(',', [5, 10, 15, 20, 25]),
      'cycledays',
      $this->pspCreditor['id']
    );

    CRM_Sepa_Logic_Settings::setSetting(7, 'batching.RCUR.notice', $this->pspCreditor['id']);

    // Case 1

    $ncd_params = [
      '_today'          => '2023-01-15',
      'creditor_id'     => $this->pspCreditor['id'],
      'payment_adapter' => 'psp_sepa',
    ];

    $this->assertEquals('2023-01-25', $this->getNextContributionDate($ncd_params));

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
      'payment_adapter' => 'psp_sepa',
      'start_date'      => '2023-03-01',
    ];

    $this->assertEquals('2023-03-05', $this->getNextContributionDate($ncd_params));

    // Case 4

    $ncd_params = [
      '_today'          => '2023-01-15',
      'creditor_id'     => $this->pspCreditor['id'],
      'cycle_day'       => 10,
      'payment_adapter' => 'psp_sepa',
      'start_date'      => '2023-03-01',
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

  }

  public function testNextContributionDateSEPA() {
    CRM_Sepa_Logic_Settings::setSetting(
      implode(',', [7, 14, 21, 28]),
      'cycledays',
      $this->sepaCreditor['id']
    );

    CRM_Sepa_Logic_Settings::setSetting(5, 'batching.RCUR.notice', $this->sepaCreditor['id']);

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
      'payment_adapter' => 'sepa_mandate',
      'start_date'      => '2023-03-01',
    ];

    $this->assertEquals('2023-03-07', $this->getNextContributionDate($ncd_params));

    // Case 4

    $ncd_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 14,
      'payment_adapter' => 'sepa_mandate',
      'start_date'      => '2023-03-01',
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

  }

  private function getNextContributionDate(array $params) {
    $result = civicrm_api3('Contract', 'next_contribution_date', $params);

    return $result['values'][0];
  }

}

?>
