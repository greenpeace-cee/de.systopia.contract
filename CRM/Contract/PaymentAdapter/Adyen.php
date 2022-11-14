<?php

use Civi\Api4;

class CRM_Contract_PaymentAdapter_Adyen implements CRM_Contract_PaymentAdapter {

  const ADAPTER_ID = 'adyen';
  const DISPLAY_NAME = 'Adyen';

  public static function adapterInfo() {
    return [
      'display_name' => self::DISPLAY_NAME,
      'id'           => self::ADAPTER_ID,
    ];
  }

  public static function create($params) {
    $paymentTokenParamMapping = [
      //                         | original name              | required | default |
      'billing_first_name'    => [ 'billing_first_name'       , FALSE    , NULL    ],
      'billing_last_name'     => [ 'billing_last_name'        , FALSE    , NULL    ],
      'contact_id'            => [ 'contact_id'               , TRUE     , NULL    ],
      'email'                 => [ 'email'                    , FALSE    , NULL    ],
      'expiry_date'           => [ 'expiry_date'              , FALSE    , NULL    ],
      'ip_address'            => [ 'ip_address'               , FALSE    , NULL    ],
      'masked_account_number' => [ 'account_number'           , FALSE    , NULL    ],
      'payment_processor_id'  => [ 'payment_processor_id'     , TRUE     , NULL    ],
      'token'                 => [ 'stored_payment_method_id' , TRUE     , NULL    ],
    ];

    $defaultPaymentProcessorID = NULL;

    if (!isset($params['payment_token_id'])) {
      $paymentToken = civicrm_api4('PaymentToken', 'create', [
        'values' => self::mapParameters($paymentTokenParamMapping, $params),
      ])->first();

      $params['payment_token_id'] = $paymentToken['id'];
      $defaultPaymentProcessorID = $paymentToken['payment_processor_id'];
    }

    $pendingOptVal = (int) CRM_Contract_Utils::getOptionValue('contribution_recur_status', 'Pending');
    $defaultCurrency = Civi::settings()->get('defaultCurrency');
    $memberDuesTypeID = CRM_Contract_Utils::getFinancialTypeID('Member Dues');
    $cycleDay = (int) CRM_Utils_Array::value('cycle_day', $params);
    $startDate = new DateTimeImmutable(CRM_Utils_Array::value('start_date', $params, 'now'));
    $nextSchedContribDate = CRM_Contract_Utils::nextCycleDate($cycleDay, $startDate->format('Y-m-d'));

    $recurContribParamMapping = [
      //                                | original name            | required | default                     |
      'amount'                       => [ 'amount'                 , TRUE     , NULL                        ],
      'contribution_status_id'       => [ 'contribution_status_id' , FALSE    , $pendingOptVal              ],
      'contact_id'                   => [ 'contact_id'             , TRUE     , NULL                        ],
      'currency'                     => [ 'currency'               , FALSE    , $defaultCurrency            ],
      'cycle_day'                    => [ 'cycle_day'              , FALSE    , 1                           ],
      'financial_type_id'            => [ 'financial_type_id'      , FALSE    , $memberDuesTypeID           ],
      'frequency_interval'           => [ 'frequency_interval'     , FALSE    , 1                           ],
      'frequency_unit:name'          => [ 'frequency_unit'         , FALSE    , 'month'                     ],
      'next_sched_contribution_date' => [ NULL                     , FALSE    , $nextSchedContribDate       ],
      'payment_instrument_id'        => [ 'payment_instrument_id'  , FALSE    , NULL                        ],
      'payment_processor_id'         => [ 'payment_processor_id'   , FALSE    , $defaultPaymentProcessorID  ],
      'payment_token_id'             => [ 'payment_token_id'       , TRUE     , NULL                        ],
      'processor_id'                 => [ 'shopper_reference'      , FALSE    , NULL                        ],
      'start_date'                   => [ 'start_date'             , FALSE    , $startDate->format('Y-m-d') ],
    ];

    $recurContribID = civicrm_api4('ContributionRecur', 'create', [
      'values' => self::mapParameters($recurContribParamMapping, $params),
    ])->first()['id'];

    CRM_Core_Session::setStatus(
      "New Adyen payment created. (Recurring contribution ID: $recurContribID)",
      'Success',
      'info'
    );

    return $recurContribID;
  }

  public static function createFromUpdate(
    $recurring_contribution_id,
    $current_adapter,
    $update,
    $activity_type_id = NULL
  ) {
    // ...

    return 0;
  }

  public static function cycleDays($params = []) {
    // ...

    return [];
  }

  public static function formFields($recurring_contribution_id = NULL) {
    $paymentProcessorOptions = [];

    $paymentProcessorQuery = Api4\PaymentProcessor::get()
      ->addSelect('title')
      ->addWhere('payment_processor_type_id:name', '=', 'Adyen')
      ->execute();

    foreach ($paymentProcessorQuery as $paymentProcessor) {
      $paymentProcessorOptions[$paymentProcessor['id']] = $paymentProcessor['title'];
    }

    $paymentInstrumentOptions = [];

    $paymentInstrumentQuery = Api4\OptionValue::get()
      ->addSelect('value', 'label')
      ->addWhere('option_group_id:name', '=', 'payment_instrument')
      ->execute();

    foreach ($paymentInstrumentQuery as $pi) {
      $paymentInstrumentOptions[$pi['value']] = $pi['label'];
    }

    return [
      'payment_processor' => [
        'default'      => NULL,
        'display_name' => 'Payment processor',
        'enabled'      => TRUE,
        'name'         => 'payment_processor',
        'options'      => $paymentProcessorOptions,
        'required'     => TRUE,
        'type'         => 'select',
      ],
      'payment_instrument' => [
        'default'      => NULL,
        'display_name' => 'Payment instrument',
        'enabled'      => TRUE,
        'name'         => 'payment_instrument',
        'options'      => $paymentInstrumentOptions,
        'required'     => TRUE,
        'type'         => 'select',
      ],
      'billing_first_name' => [
        'default'      => NULL,
        'display_name' => 'Billing first name',
        'enabled'      => TRUE,
        'name'         => 'billing_first_name',
        'required'     => FALSE,
        'settings'     => [ 'class' => 'big' ],
        'type'         => 'text',
      ],
      'billing_last_name' => [
        'default'      => NULL,
        'display_name' => 'Billing last name',
        'enabled'      => TRUE,
        'name'         => 'billing_last_name',
        'required'     => FALSE,
        'settings'     => [ 'class' => 'big' ],
        'type'         => 'text',
      ],
      'email' => [
        'default'      => NULL,
        'display_name' => 'Billing Email',
        'enabled'      => TRUE,
        'name'         => 'email',
        'required'     => FALSE,
        'settings'     => [ 'class' => 'big' ],
        'type'         => 'text',
      ],
      'stored_payment_method_id' => [
        'default'      => NULL,
        'display_name' => 'Stored payment method ID',
        'enabled'      => TRUE,
        'name'         => 'stored_payment_method_id',
        'required'     => TRUE,
        'settings'     => [ 'class' => 'big' ],
        'type'         => 'text',
      ],
      'shopper_reference' => [
        'default'      => NULL,
        'display_name' => 'Shopper reference',
        'enabled'      => TRUE,
        'name'         => 'shopper_reference',
        'required'     => FALSE,
        'settings'     => [ 'class' => 'huge' ],
        'type'         => 'text',
      ],
      'account_number' => [
        'default'      => NULL,
        'display_name' => 'Account number',
        'enabled'      => TRUE,
        'name'         => 'account_number',
        'required'     => FALSE,
        'settings'     => [ 'class' => 'huge' ],
        'type'         => 'text',
      ],
    ];
  }

  public static function formVars($params = []) {
    // ...

    return [
      'default_currency' => Civi::settings()->get('defaultCurrency'),
    ];
  }

  public static function getNextScheduledContributionDate(
    int $recurContribID,
    int $cycleDay = NULL,
    string $offset = NULL
  ): string {
    $recurringContribution = Api4\ContributionRecur::get()
      ->addSelect(
        'contribution.receive_date',
        'cylce_day',
        'frequency_interval',
        'frequency_unit:name',
        'start_date',
      )
      ->addJoin(
        'Contribution AS contribution',
        'LEFT',
        ['id', '=', 'contribution.contribution_recur_id']
      )
      ->addWhere('id', '=', $recurContribID)
      ->addOrderBy('contribution.receive_date', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first();

    $cycleDay = $cycleDay ?? $recurringContribution['cycle_day'];
    $offset = new DateTime($offset ?? $recurringContribution['start_date']);

    if (isset($recurringContribution['contribution.receive_date'])) {
      $unitMapping = [
        'month' => 'M',
        'year'  => 'Y',
      ];

      $interval = $recurringContribution['frequency_interval'];
      $unit = $unitMapping[$recurringContribution['frequency_unit:name']];
      $lastContributionDate = new DateTime($recurringContribution['contribution.receive_date']);
      $coveredPeriod = new DateInterval("P$interval$unit");
      $offset = max($offset, $lastContributionDate->add($coveredPeriod));
    }

    return CRM_Contract_Utils::nextCycleDate($cycleDay, $offset->format('Y-m-d'));
  }

  public static function isInstance($recurringContributionID) {
    $paymentProcessorType = Api4\ContributionRecur::get()
      ->addSelect('payment_processor_id.payment_processor_type_id.name')
      ->addWhere('id', '=', $recurringContributionID)
      ->setLimit(1)
      ->execute()
      ->first()['payment_processor_id.payment_processor_type_id.name'];

    return $paymentProcessorType === 'Adyen';
  }

  public static function mapSubmittedFormValues($apiEndpoint, $submitted) {
    dump('$submitted:', $submitted);

    switch ($apiEndpoint) {
      case 'Contract.create': {
        return [
          'payment_method.amount'                   => CRM_Contract_Utils::formatMoney($submitted['amount']),
          'payment_method.payment_processor_id'     => (int) $submitted['pa-adyen-payment_processor'],
          'payment_method.shopper_reference'        => $submitted['pa-adyen-shopper_reference'],
          'payment_method.stored_payment_method_id' => $submitted['pa-adyen-stored_payment_method_id'],
        ];
      }

      default: {
        return [];
      }
    }
  }

  public static function nextCycleDay() {
    // ...

    return 0;
  }

  public static function pause($recurring_contribution_id) {
    // ...

    return;
  }

  public static function resume($recurring_contribution_id, $update = []) {
    // ...

    return $recurring_contribution_id;
  }

  public static function revive($recurringContributionID, $update = []) {
    $pendingOptVal = (int) CRM_Contract_Utils::getOptionValue('contribution_recur_status', 'Pending');
    $update['cancel_date'] = NULL;
    $update['cancel_reason'] = NULL;

    $update['contribution_status_id'] = CRM_Utils_Array::value(
      'contribution_status_id',
      $update,
      $pendingOptVal
    );

    $newRecurContribID = self::update($recurringContributionID, $update);

    return $newRecurContribID;
  }

  public static function terminate($recurringContributionID, $reason = "CHNG") {
    $now = date('Y-m-d H:i:s');

    Api4\ContributionRecur::update()
      ->addValue('cancel_date',                 $now)
      ->addValue('cancel_reason',               $reason)
      ->addValue('contribution_status_id:name', 'Completed')
      ->addValue('end_date',                    $now)
      ->addWhere('id', '=', $recurringContributionID)
      ->execute();

    $rcDAO = new CRM_Contribute_DAO_ContributionRecur();
    $rcDAO->get('id', $recurringContributionID);
    $rcDAO->next_sched_contribution_date = NULL;
    $rcDAO->save();

    return;
  }

  public static function update($recurringContributionID, $params, $activityTypeID = NULL) {
    $oldRC = Api4\ContributionRecur::get()
      ->addSelect('*')
      ->addWhere('id', '=', $recurringContributionID)
      ->execute()
      ->first();

    self::terminate($recurringContributionID);

    $cycleDay = CRM_Utils_Array::value('cycle_day', $params, $oldRC['cycle_day']);
    $startDate = new DateTime(CRM_Utils_Array::value('start_date', $params, 'now'));

    $nextSchedContribDate = self::getNextScheduledContributionDate(
      $oldRC['id'],
      $cycleDay,
      $startDate->format('Y-m-d')
    );

    $recurContribParamMapping = [
      //                                | original name            | required | default                          |
      'amount'                       => [ 'amount'                 , FALSE    , $oldRC['amount']                 ],
      'contact_id'                   => [ NULL                     , FALSE    , $oldRC['contact_id']             ],
      'contribution_status_id'       => [ 'contribution_status_id' , FALSE    , $oldRC['contribution_status_id'] ],
      'currency'                     => [ 'currency'               , FALSE    , $oldRC['currency']               ],
      'cycle_day'                    => [ 'cycle_day'              , FALSE    , $cycleDay                        ],
      'financial_type_id'            => [ 'financial_type_id'      , FALSE    , $oldRC['financial_type_id']      ],
      'frequency_interval'           => [ 'frequency_interval'     , FALSE    , $oldRC['frequency_interval']     ],
      'frequency_unit:name'          => [ 'frequency_unit'         , FALSE    , $oldRC['frequency_unit']         ],
      'next_sched_contribution_date' => [ NULL                     , FALSE    , $nextSchedContribDate            ],
      'payment_instrument_id'        => [ 'payment_instrument_id'  , FALSE    , $oldRC['payment_instrument_id']  ],
      'payment_processor_id'         => [ 'payment_processor_id'   , FALSE    , $oldRC['payment_processor_id']   ],
      'payment_token_id'             => [ 'payment_token_id'       , FALSE    , $oldRC['payment_token_id']       ],
      'start_date'                   => [ 'start_date'             , FALSE    , $oldRC['start_date']             ],
    ];

    $updateParams = self::mapParameters($recurContribParamMapping, $params);

    $newRecurringContribution = civicrm_api4(
      'ContributionRecur',
      'create',
      [ 'values' => $updateParams ]
    )->first();

    return $newRecurringContribution['id'];
  }

  private static function mapParameters(array $mapping, array $originalParams) {
    $result = [];

    foreach ($mapping as $key => $spec) {
      list($originalName, $isRequired, $default) = $spec;

      if (is_null($originalName)) {
        $result[$key] = $default;
        continue;
      }

      if (isset($originalParams[$originalName])) {
        $result[$key] = $originalParams[$originalName];
        continue;
      }

      if ($isRequired) {
        throw new Exception("Missing parameter '$originalName'");
      }

      $result[$key] = $default;
    }

    return $result;
  }

}

?>
