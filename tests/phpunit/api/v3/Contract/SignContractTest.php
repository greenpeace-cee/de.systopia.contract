<?php

use Civi\Api4;

/**
 * @group headless
 */
class api_v3_Contract_SignContractTest extends api_v3_Contract_ContractTestBase {

  public function testAdyen() {
    $today = new DateTimeImmutable();
    $tomorrow = new DateTimeImmutable('tomorrow');
    $one_year = new DateInterval('P1Y');
    $next_year = $today->add($one_year);

    // Adyen: Create contract

    $cycle_day = 13;
    $encounter_medium = self::getOptionValue('encounter_medium', 'in_person');
    $expiry_date = DateTimeImmutable::createFromFormat('Y-m-d', $next_year->format('Y-12-31'));
    $financial_type = self::getFinancialTypeID('Member Dues');
    $membership_type = self::getMembershipTypeID('General');
    $payment_instrument = self::getOptionValue('payment_instrument', 'Credit Card');
    $start_date = CRM_Contract_DateHelper::findNextOfDays([1], $tomorrow->format('Y-m-d'));

    $first_contrib_date = CRM_Contract_DateHelper::findNextOfDays(
      [$cycle_day],
      $start_date->format('Y-m-d')
    );

    $params = [
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
    ];

    $result = civicrm_api3('Contract', 'create', $params);

    // Adyen: Check membership

    $membership = self::getMembershipByID($result['id']);
    $membership_start_date = new DateTimeImmutable($membership['start_date']);

    $this->assertEachEquals([
      [$this->contact['id']        , $membership['contact_id']              ],
      [$today->format('Y-m-d')     , $membership['join_date']               ],
      ['Current'                   , $membership['status_id:name']          ],
      [$start_date->format('Y-m-d'), $membership_start_date->format('Y-m-d')],
    ]);

    // Adyen: Check recurring contribution

    $rc = self::getActiveRecurringContribution($membership['id']);
    $rc_start_date = new DateTimeImmutable($rc['start_date']);

    $this->assertEachEquals([
      [10.0                                , $rc['amount']                     ],
      [$this->contact['id']                , $rc['contact_id']                 ],
      ['In Progress'                       , $rc['contribution_status_id:name']],
      [$cycle_day                          , $rc['cycle_day']                  ],
      [$financial_type                     , $rc['financial_type_id']          ],
      [1                                   , $rc['frequency_interval']         ],
      ['month'                             , $rc['frequency_unit']             ],
      ['Credit Card'                       , $rc['payment_instrument_id:name'] ],
      ['OSF-TOKEN-PRODUCTION-56789-ADYEN'  , $rc['processor_id']               ],
      [$first_contrib_date->format('Y-m-d'), $rc_start_date->format('Y-m-d')   ],
    ]);

    // Adyen: Check payment token

    $payment_token = Api4\PaymentToken::get(FALSE)
      ->addWhere('id', '=', $rc['payment_token_id'])
      ->addSelect('*')
      ->setLimit(1)
      ->execute()
      ->first();

    $token_expiry_date = new DateTimeImmutable($payment_token['expiry_date']);

    $this->assertEachEquals([
      [$this->contact['id']         , $payment_token['contact_id']           ],
      [$this->contact['first_name'] , $payment_token['billing_first_name']   ],
      [$this->contact['last_name']  , $payment_token['billing_last_name']    ],
      [$this->contact['email']      , $payment_token['email']                ],
      [$expiry_date->format('Y-m-d'), $token_expiry_date->format('Y-m-d')    ],
      ['127.0.0.1'                  , $payment_token['ip_address']           ],
      ['AT40 1000 0000 0000 1111'   , $payment_token['masked_account_number']],
      [$this->adyenProcessor['id']  , $payment_token['payment_processor_id'] ],
      ['2856793471528814'           , $payment_token['token']                ],
    ]);
  }

  public function testEFT() {
    $today = new DateTimeImmutable();
    $tomorrow = new DateTimeImmutable('tomorrow');

    // EFT: Create contract

    $cycle_day = 17;
    $encounter_medium = self::getOptionValue('encounter_medium', 'in_person');
    $financial_type = self::getFinancialTypeID('Member Dues');
    $membership_type = self::getMembershipTypeID('General');
    $start_date = CRM_Contract_DateHelper::findNextOfDays([1], $tomorrow->format('Y-m-d'));

    $first_contrib_date = CRM_Contract_DateHelper::findNextOfDays(
      [$cycle_day],
      $start_date->format('Y-m-d')
    );

    $params = [
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
    ];

    $result = civicrm_api3('Contract', 'create', $params);

    // EFT: Check membership

    $membership = self::getMembershipByID($result['id']);
    $membership_start_date = new DateTimeImmutable($membership['start_date']);

    $this->assertEachEquals([
      [$this->contact['id']        , $membership['contact_id']              ],
      [$today->format('Y-m-d')     , $membership['join_date']               ],
      ['Current'                   , $membership['status_id:name']          ],
      [$start_date->format('Y-m-d'), $membership_start_date->format('Y-m-d')],
    ]);

    // EFT: Check recurring contribution

    $rc = self::getActiveRecurringContribution($membership['id']);
    $rc_start_date = new DateTimeImmutable($rc['start_date']);

    $this->assertEachEquals([
      [10.0                                , $rc['amount']                     ],
      [$this->contact['id']                , $rc['contact_id']                 ],
      ['Pending'                           , $rc['contribution_status_id:name']],
      [$cycle_day                          , $rc['cycle_day']                  ],
      [$financial_type                     , $rc['financial_type_id']          ],
      [1                                   , $rc['frequency_interval']         ],
      ['month'                             , $rc['frequency_unit']             ],
      ['EFT'                               , $rc['payment_instrument_id:name'] ],
      [$first_contrib_date->format('Y-m-d'), $rc_start_date->format('Y-m-d')   ],
    ]);
  }

  public function testPSP() {
    $today = new DateTimeImmutable();
    $tomorrow = new DateTimeImmutable('tomorrow');

    // PSP: Create contract

    $encounter_medium = self::getOptionValue('encounter_medium', 'in_person');
    $financial_type = self::getFinancialTypeID('Member Dues');
    $membership_type = self::getMembershipTypeID('General');
    $payment_instrument = self::getOptionValue('payment_instrument', 'Credit Card');
    $start_date = CRM_Contract_DateHelper::findNextOfDays([1], $tomorrow->format('Y-m-d'));

    $first_contrib_date = CRM_Contract_DateHelper::findNextOfDays(
      [5],
      $start_date->format('Y-m-d')
    );

    $params = [
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
    ];

    $result = civicrm_api3('Contract', 'create', $params);

    // PSP: Check membership

    $membership = self::getMembershipByID($result['id']);
    $membership_start_date = new DateTimeImmutable($membership['start_date']);

    $this->assertEachEquals([
      [$this->contact['id']        , $membership['contact_id']              ],
      [$today->format('Y-m-d')     , $membership['join_date']               ],
      ['Current'                   , $membership['status_id:name']          ],
      [$start_date->format('Y-m-d'), $membership_start_date->format('Y-m-d')],
    ]);

    // PSP: Check recurring contribution

    $rc = self::getActiveRecurringContribution($membership['id']);
    $rc_start_date = new DateTimeImmutable($rc['start_date']);

    $this->assertEachEquals([
      [10.0                                , $rc['amount']                     ],
      [$this->contact['id']                , $rc['contact_id']                 ],
      ['Pending'                           , $rc['contribution_status_id:name']],
      [5                                   , $rc['cycle_day']                  ],
      [$financial_type                     , $rc['financial_type_id']          ],
      [1                                   , $rc['frequency_interval']         ],
      ['month'                             , $rc['frequency_unit']             ],
      ['Credit Card'                       , $rc['payment_instrument_id:name'] ],
      [$first_contrib_date->format('Y-m-d'), $rc_start_date->format('Y-m-d')   ],
    ]);

    // PSP: Check SEPA mandate

    $sepa_mandate = Api4\SepaMandate::get(FALSE)
      ->addWhere('entity_id'   , '=', $rc['id'])
      ->addWhere('entity_table', '=', 'civicrm_contribution_recur')
      ->addSelect('*')
      ->setLimit(1)
      ->execute()
      ->first();

    $this->assertEachEquals([
      ['Greenpeace'                    , $sepa_mandate['bic']],
      [$this->contact['id']            , $sepa_mandate['contact_id']],
      [$this->pspCreditor['id']        , $sepa_mandate['creditor_id']],
      ['OSF-TOKEN-PRODUCTION-12345-PSP', $sepa_mandate['iban']],
      ['FRST'                          , $sepa_mandate['status']],
      ['RCUR'                          , $sepa_mandate['type']],
    ]);
  }

  public function testSEPA() {
    $today = new DateTimeImmutable();
    $tomorrow = new DateTimeImmutable('tomorrow');

    // SEPA: Create contract

    $encounter_medium = self::getOptionValue('encounter_medium', 'in_person');
    $financial_type = self::getFinancialTypeID('Member Dues');
    $membership_type = self::getMembershipTypeID('General');
    $payment_instrument = self::getOptionValue('payment_instrument', 'Credit Card');
    $start_date = CRM_Contract_DateHelper::findNextOfDays([1], $tomorrow->format('Y-m-d'));

    $first_contrib_date = CRM_Contract_DateHelper::findNextOfDays(
      [7],
      $start_date->format('Y-m-d')
    );

    $params = [
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
    ];

    $result = civicrm_api3('Contract', 'create', $params);

    // SEPA: Check membership

    $membership = self::getMembershipByID($result['id']);
    $membership_start_date = new DateTimeImmutable($membership['start_date']);

    $this->assertEachEquals([
      [$this->contact['id']        , $membership['contact_id']              ],
      [$today->format('Y-m-d')     , $membership['join_date']               ],
      ['Current'                   , $membership['status_id:name']          ],
      [$start_date->format('Y-m-d'), $membership_start_date->format('Y-m-d')],
    ]);

    // SEPA: Check recurring contribution

    $rc = self::getActiveRecurringContribution($membership['id']);
    $rc_start_date = new DateTimeImmutable($rc['start_date']);

    $this->assertEachEquals([
      [10.0                                , $rc['amount']                     ],
      [$this->contact['id']                , $rc['contact_id']                 ],
      ['Pending'                           , $rc['contribution_status_id:name']],
      [7                                   , $rc['cycle_day']                  ],
      [$financial_type                     , $rc['financial_type_id']          ],
      [1                                   , $rc['frequency_interval']         ],
      ['month'                             , $rc['frequency_unit']             ],
      ['RCUR'                              , $rc['payment_instrument_id:name'] ],
      [$first_contrib_date->format('Y-m-d'), $rc_start_date->format('Y-m-d')   ],
    ]);

    // SEPA: Check SEPA mandate

    $sepa_mandate = Api4\SepaMandate::get(FALSE)
      ->addWhere('entity_id'   , '=', $rc['id'])
      ->addWhere('entity_table', '=', 'civicrm_contribution_recur')
      ->addSelect('*')
      ->setLimit(1)
      ->execute()
      ->first();

    $this->assertEachEquals([
      ['BKAUATWWXXX'            , $sepa_mandate['bic']],
      [$this->contact['id']     , $sepa_mandate['contact_id']],
      [$this->sepaCreditor['id'], $sepa_mandate['creditor_id']],
      ['AT340000000012345678'   , $sepa_mandate['iban']],
      ['FRST'                   , $sepa_mandate['status']],
      ['RCUR'                   , $sepa_mandate['type']],
    ]);
  }

}

?>
