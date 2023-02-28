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
      'cycle_day'       => 13,
      'payment_adapter' => 'adyen',
      'start_date'      => '2023-03-01',
    ];

    $this->assertEquals('2023-03-13', $this->getNextContributionDate($ncd_params));

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
      'cycle_day'       => 13,
      'payment_adapter' => 'eft',
      'start_date'      => '2023-03-01',
    ];

    $this->assertEquals('2023-03-13', $this->getNextContributionDate($ncd_params));

  }

  public function testNextContributionDatePSP() {

    // Case 1

    $ncd_params = [
      '_today'          => '2023-01-15',
      'payment_adapter' => 'psp_sepa',
    ];

    $this->assertEquals('2023-01-15', $this->getNextContributionDate($ncd_params));

    // Case 2

    $ncd_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'payment_adapter' => 'psp_sepa',
    ];

    $this->assertEquals('2023-02-13', $this->getNextContributionDate($ncd_params));

    // Case 3

    $ncd_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'payment_adapter' => 'psp_sepa',
      'start_date'      => '2023-03-01',
    ];

    $this->assertEquals('2023-03-13', $this->getNextContributionDate($ncd_params));

  }

  public function testNextContributionDateSEPA() {

    // Case 1

    $ncd_params = [
      '_today'          => '2023-01-15',
      'payment_adapter' => 'sepa_mandate',
    ];

    $this->assertEquals('2023-01-15', $this->getNextContributionDate($ncd_params));

    // Case 2

    $ncd_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'payment_adapter' => 'sepa_mandate',
    ];

    $this->assertEquals('2023-02-13', $this->getNextContributionDate($ncd_params));

    // Case 3

    $ncd_params = [
      '_today'          => '2023-01-15',
      'cycle_day'       => 13,
      'payment_adapter' => 'sepa_mandate',
      'start_date'      => '2023-03-01',
    ];

    $this->assertEquals('2023-03-13', $this->getNextContributionDate($ncd_params));

  }

  private function getNextContributionDate(array $params) {
    $result = civicrm_api3('Contract', 'next_contribution_date', $params);

    $this->assertEquals(0, $result['is_error']);

    return $result['values'][0];
  }

}

?>
