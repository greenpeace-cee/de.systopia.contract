<?php

use Civi\Api4;

/**
 * @group headless
 */
class api_v3_Contract_UpdateContractTest extends api_v3_Contract_ContractTestBase {

  public function testAdyen() {
    $membership_id = $this->createContract('adyen');
    $rc_old = self::getActiveRecurringContribution($membership_id);

    $this->assertEachEquals([
      [10.0                 , $rc_old['amount']            ],
      [$this->campaign['id'], $rc_old['campaign_id']       ],
      [1                    , $rc_old['cycle_day']         ],
      [1                    , $rc_old['frequency_interval']],
      ['month'              , $rc_old['frequency_unit']    ],
    ]);

    $start_date_old = new DateTimeImmutable($rc_old['start_date']);

    $contribution = $this->createContribution([
      'amount'                    => 10.0,
      'date'                      => $start_date_old->format('Y-m-d'),
      'recurring_contribution_id' => $rc_old['id'],
    ]);

    civicrm_api3('Contract', 'modify', [
      'action'                                  => 'update',
      'campaign_id'                             => NULL,
      'id'                                      => $membership_id,
      'membership_payment.membership_annual'    => 180.0,
      'membership_payment.membership_frequency' => 6,
      'payment_method.adapter'                  => 'adyen',
      'payment_method.cycle_day'                => 13,
      'payment_method.defer_payment_start'      => TRUE,
    ]);

    civicrm_api3('Contract', 'process_scheduled_modifications');

    $update_activity = self::getLatestUpdateActivity($membership_id);

    $this->assertEquals(NULL, $update_activity['campaign_id']);

    $rc_new = self::getActiveRecurringContribution($membership_id);

    $this->assertNotEquals($rc_old['id'], $rc_new['id']);

    $start_date_new = new DateTimeImmutable($rc_new['start_date']);
    $one_month = new DateInterval('P1M');

    $exp_start_date = CRM_Contract_DateHelper::findNextOfDays(
      [13],
      $start_date_old->add($one_month)->format('Y-m-d')
    );

    $this->assertEachEquals([
      [30.0                            , $rc_new['amount']               ],
      [$rc_old['campaign_id']          , $rc_new['campaign_id']          ],
      [13                              , $rc_new['cycle_day']            ],
      [2                               , $rc_new['frequency_interval']   ],
      ['month'                         , $rc_new['frequency_unit']       ],
      [$exp_start_date->format('Y-m-d'), $start_date_new->format('Y-m-d')],
    ]);
  }

  public function testEFT() {
    $membership_id = $this->createContract('eft');
    $rc_old = self::getActiveRecurringContribution($membership_id);

    $this->assertEachEquals([
      [10.0                 , $rc_old['amount']            ],
      [$this->campaign['id'], $rc_old['campaign_id']       ],
      [1                    , $rc_old['cycle_day']         ],
      [1                    , $rc_old['frequency_interval']],
      ['month'              , $rc_old['frequency_unit']    ],
    ]);

    $start_date_old = new DateTimeImmutable($rc_old['start_date']);

    $contribution = $this->createContribution([
      'amount'                    => 10.0,
      'date'                      => $start_date_old->format('Y-m-d'),
      'recurring_contribution_id' => $rc_old['id'],
    ]);

    civicrm_api3('Contract', 'modify', [
      'action'                                  => 'update',
      'campaign_id'                             => NULL,
      'id'                                      => $membership_id,
      'membership_payment.membership_annual'    => 200.0,
      'membership_payment.membership_frequency' => 4,
      'payment_method.adapter'                  => 'eft',
      'payment_method.cycle_day'                => 17,
      'payment_method.defer_payment_start'      => TRUE,
    ]);

    civicrm_api3('Contract', 'process_scheduled_modifications');

    $update_activity = self::getLatestUpdateActivity($membership_id);

    $this->assertEquals(NULL, $update_activity['campaign_id']);

    $rc_new = self::getActiveRecurringContribution($membership_id);

    $this->assertNotEquals($rc_old['id'], $rc_new['id']);

    $start_date_new = new DateTimeImmutable($rc_new['start_date']);
    $one_month = new DateInterval('P1M');

    $exp_start_date = CRM_Contract_DateHelper::findNextOfDays(
      [17],
      $start_date_old->add($one_month)->format('Y-m-d')
    );

    $this->assertEachEquals([
      [50.0                            , $rc_new['amount']               ],
      [17                              , $rc_new['cycle_day']            ],
      [3                               , $rc_new['frequency_interval']   ],
      ['month'                         , $rc_new['frequency_unit']       ],
      [$exp_start_date->format('Y-m-d'), $start_date_new->format('Y-m-d')],
    ]);
  }

  public function testPSP() {
    $membership_id = $this->createContract('psp_sepa');
    $rc_old = self::getActiveRecurringContribution($membership_id);

    $this->assertEachEquals([
      [10.0                 , $rc_old['amount']            ],
      [$this->campaign['id'], $rc_old['campaign_id']       ],
      [5                    , $rc_old['cycle_day']         ],
      [1                    , $rc_old['frequency_interval']],
      ['month'              , $rc_old['frequency_unit']    ],
    ]);

    $start_date_old = new DateTimeImmutable($rc_old['start_date']);

    $contribution = $this->createContribution([
      'amount'                    => 10.0,
      'date'                      => $start_date_old->format('Y-m-d'),
      'recurring_contribution_id' => $rc_old['id'],
    ]);

    civicrm_api3('Contract', 'modify', [
      'action'                                  => 'update',
      'campaign_id'                             => NULL,
      'id'                                      => $membership_id,
      'membership_payment.membership_annual'    => 180.0,
      'membership_payment.membership_frequency' => 3,
      'payment_method.adapter'                  => 'psp_sepa',
      'payment_method.cycle_day'                => 15,
      'payment_method.defer_payment_start'      => TRUE,
    ]);

    civicrm_api3('Contract', 'process_scheduled_modifications');

    $update_activity = self::getLatestUpdateActivity($membership_id);

    $this->assertEquals(NULL, $update_activity['campaign_id']);

    $rc_new = self::getActiveRecurringContribution($membership_id);

    $this->assertNotEquals($rc_old['id'], $rc_new['id']);

    $start_date_new = new DateTimeImmutable($rc_new['start_date']);
    $one_month = new DateInterval('P1M');

    $exp_start_date = CRM_Contract_DateHelper::findNextOfDays(
      [15],
      $start_date_old->add($one_month)->format('Y-m-d')
    );

    $this->assertEachEquals([
      [60.0                            , $rc_new['amount']               ],
      [15                              , $rc_new['cycle_day']            ],
      [4                               , $rc_new['frequency_interval']   ],
      ['month'                         , $rc_new['frequency_unit']       ],
      [$exp_start_date->format('Y-m-d'), $start_date_new->format('Y-m-d')],
    ]);
  }

  public function testSEPA() {
    $membership_id = $this->createContract('sepa_mandate');
    $rc_old = self::getActiveRecurringContribution($membership_id);

    $this->assertEachEquals([
      [10.0                 , $rc_old['amount']            ],
      [$this->campaign['id'], $rc_old['campaign_id']       ],
      [7                    , $rc_old['cycle_day']         ],
      [1                    , $rc_old['frequency_interval']],
      ['month'              , $rc_old['frequency_unit']    ],
    ]);

    $start_date_old = new DateTimeImmutable($rc_old['start_date']);

    $contribution = $this->createContribution([
      'amount'                    => 10.0,
      'date'                      => $start_date_old->format('Y-m-d'),
      'recurring_contribution_id' => $rc_old['id'],
    ]);

    civicrm_api3('Contract', 'modify', [
      'action'                                  => 'update',
      'campaign_id'                             => NULL,
      'id'                                      => $membership_id,
      'membership_payment.membership_annual'    => 200.0,
      'membership_payment.membership_frequency' => 2,
      'payment_method.adapter'                  => 'sepa_mandate',
      'payment_method.cycle_day'                => 21,
      'payment_method.defer_payment_start'      => TRUE,
    ]);

    civicrm_api3('Contract', 'process_scheduled_modifications');

    $update_activity = self::getLatestUpdateActivity($membership_id);

    $this->assertEquals(NULL, $update_activity['campaign_id']);

    $rc_new = self::getActiveRecurringContribution($membership_id);

    $this->assertNotEquals($rc_old['id'], $rc_new['id']);

    $start_date_new = new DateTimeImmutable($rc_new['start_date']);
    $one_month = new DateInterval('P1M');

    $exp_start_date = CRM_Contract_DateHelper::findNextOfDays(
      [21],
      $start_date_old->add($one_month)->format('Y-m-d')
    );

    $this->assertEachEquals([
      [100.0                           , $rc_new['amount']               ],
      [21                              , $rc_new['cycle_day']            ],
      [6                               , $rc_new['frequency_interval']   ],
      ['month'                         , $rc_new['frequency_unit']       ],
      [$exp_start_date->format('Y-m-d'), $start_date_new->format('Y-m-d')],
    ]);
  }

  private function createContract(string $adapter, array $override_params = []) {
    $today = new DateTimeImmutable();
    $tomorrow = new DateTimeImmutable('tomorrow');

    $encounter_medium = self::getOptionValue('encounter_medium', 'in_person');
    $financial_type = self::getFinancialTypeID('Member Dues');
    $membership_type = self::getMembershipTypeID('General');
    $start_date = CRM_Contract_DateHelper::findNextOfDays([1], $tomorrow->format('Y-m-d'));

    $params = [
      'campaign_id'                       => $this->campaign['id'],
      'contact_id'                        => $this->contact['id'],
      'medium_id'                         => $encounter_medium,
      'membership_type_id'                => $membership_type,
      'payment_method.adapter'            => $adapter,
      'payment_method.amount'             => 10.0,
      'payment_method.campaign_id'        => $this->campaign['id'],
      'payment_method.contact_id'         => $this->contact['id'],
      'payment_method.financial_type_id'  => $financial_type,
      'payment_method.frequency_interval' => 1,
      'payment_method.frequency_unit'     => 'month',
      'start_date'                        => $start_date->format('Y-m-d'),
    ];

    switch ($adapter) {
      case "adyen": {
        $payment_instrument = self::getOptionValue('payment_instrument', 'Credit Card');

        $params += [
          'payment_method.payment_instrument_id'    => $payment_instrument,
          'payment_method.payment_processor_id'     => $this->adyenProcessor['id'],
          'payment_method.shopper_reference'        => 'OSF-TOKEN-PRODUCTION-56789-ADYEN',
          'payment_method.stored_payment_method_id' => '2856793471528814',
        ];

        break;
      }

      case "eft": {
        break;
      }

      case "psp_sepa": {
        $payment_instrument = self::getOptionValue('payment_instrument', 'Credit Card');

        $params += [
          'payment_method.account_name'          => 'Greenpeace',
          'payment_method.account_reference'     => 'OSF-TOKEN-PRODUCTION-12345-PSP',
          'payment_method.creditor_id'           => $this->pspCreditor['id'],
          'payment_method.payment_instrument_id' => $payment_instrument,
          'payment_method.type'                  => 'RCUR',
        ];

        break;
      }

      case "sepa_mandate": {
        $params += [
          'payment_method.bic'  => 'BKAUATWWXXX',
          'payment_method.iban' => 'AT340000000012345678',
          'payment_method.type' => 'RCUR',
        ];

        break;
      }
    }

    $params = array_merge($params, $override_params);
    $result = civicrm_api3('Contract', 'create', $params);

    return (int) $result['id'];
  }

  private static function getLatestUpdateActivity($membership_id) {
    return Api4\Activity::get(FALSE)
      ->addWhere('activity_type_id:name', '=', 'Contract_Updated')
      ->addWhere('source_record_id', '=', $membership_id)
      ->addSelect('*')
      ->addOrderBy('activity_date_time', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first();
  }

}

?>
