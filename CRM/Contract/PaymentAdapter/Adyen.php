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

    $paymentToken = NULL;
    $useExistingToken = isset($params['payment_token_id']);

    if ($useExistingToken) {
      $paymentToken = Api4\PaymentToken::get()
        ->addWhere('id', '=', $params['payment_token_id'])
        ->addSelect('payment_processor_id', 'cr.processor_id')
        ->addJoin(
          'ContributionRecur AS cr',
          'LEFT',
          ['cr.payment_token_id', '=', 'id']
        )
        ->execute()
        ->first();

        unset($params['shopper_reference']);
    } else {
      $paymentToken = civicrm_api4('PaymentToken', 'create', [
        'values' => self::mapParameters($paymentTokenParamMapping, $params),
      ])->first();
    }

    $paymentProcessorID = $paymentToken['payment_processor_id'];
    $defaultShopperReference = CRM_Utils_Array::value('cr.processor_id', $paymentToken);

    $pendingOptVal = (int) CRM_Contract_Utils::getOptionValue('contribution_recur_status', 'Pending');
    $defaultCurrency = Civi::settings()->get('defaultCurrency');
    $memberDuesTypeID = CRM_Contract_Utils::getFinancialTypeID('Member Dues');

    $cycleDay = (int) CRM_Utils_Array::value('cycle_day', $params);
    $startDate = new DateTimeImmutable(CRM_Utils_Array::value('start_date', $params, 'now'));
    $nextSchedContribDate = CRM_Contract_Utils::nextCycleDate($cycleDay, $startDate->format('Y-m-d'));

    $recurContribParamMapping = [
      //                                | original name            | required          | default                     |
      'amount'                       => [ 'amount'                 , TRUE              , NULL                        ],
      'campaign_id'                  => [ 'campaign_id'            , FALSE             , NULL                        ],
      'contact_id'                   => [ 'contact_id'             , TRUE              , NULL                        ],
      'contribution_status_id'       => [ 'contribution_status_id' , FALSE             , $pendingOptVal              ],
      'currency'                     => [ 'currency'               , FALSE             , $defaultCurrency            ],
      'cycle_day'                    => [ 'cycle_day'              , FALSE             , 1                           ],
      'financial_type_id'            => [ 'financial_type_id'      , FALSE             , $memberDuesTypeID           ],
      'frequency_interval'           => [ 'frequency_interval'     , FALSE             , 1                           ],
      'frequency_unit:name'          => [ 'frequency_unit'         , FALSE             , 'month'                     ],
      'next_sched_contribution_date' => [ NULL                     , FALSE             , $nextSchedContribDate       ],
      'payment_instrument_id'        => [ 'payment_instrument_id'  , FALSE             , NULL                        ],
      'payment_processor_id'         => [ NULL                     , FALSE             , $paymentProcessorID         ],
      'payment_token_id'             => [ NULL                     , FALSE             , $paymentToken['id']         ],
      'processor_id'                 => [ 'shopper_reference'      , !$useExistingToken, $defaultShopperReference    ],
      'start_date'                   => [ 'start_date'             , FALSE             , $startDate->format('Y-m-d') ],
      'trxn_id'                      => [ NULL                     , FALSE             , NULL                        ],
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
    $recurringContributionID,
    $current_adapter,
    $update,
    $activity_type_id = NULL
  ) {
    $originalRC = Api4\ContributionRecur::get()
      ->addWhere('id', '=', $recurringContributionID)
      ->addSelect(
        'amount',
        'campaign_id',
        'contact_id',
        'contribution_status_id',
        'currency',
        'cycle_day', 
        'financial_type_id',
        'frequency_interval',
        'frequency_unit',
        'payment_instrument_id',
        'start_date'
      )
      ->execute()
      ->first();

    $createParams = array_merge($originalRC, $update);

    return self::create($createParams);
  }

  public static function cycleDays($params = []) {
    return range(1, 28);
  }

  public static function formFields($params = []) {
    $form = CRM_Utils_Array::value('form', $params, NULL);
    $contactID = CRM_Utils_Array::value('contact_id', $params, NULL);
    $submitted = CRM_Utils_Array::value('submitted', $params, []);

    $useExistingToken =
      isset($submitted['pa-adyen-use_existing_token'])
      && $submitted['pa-adyen-use_existing_token'] === '0';

    $paymentTokenOptions = is_null($contactID) ? [] : self::getPaymentTokensForContact($contactID);

    $defaults = [];

    if (isset($params['recurring_contribution_id'])) {
      $defaults= Api4\ContributionRecur::get()
        ->addWhere('id', '=', $params['recurring_contribution_id'])
        ->addSelect(
          'payment_instrument_id',
          'payment_token_id',
          'payment_processor_id'
        )
        ->execute()
        ->first();
    }

    return [
      'payment_instrument_id' => [
        'default'      => CRM_Utils_Array::value('payment_instrument_id', $defaults),
        'display_name' => 'Payment instrument',
        'enabled'      => TRUE,
        'name'         => 'payment_instrument_id',
        'options'      => CRM_Contract_FormUtils::getOptionValueLabels('payment_instrument'),
        'required'     => FALSE,
        'type'         => 'select',
      ],
      'use_existing_token' => [
        'default'      => NULL,
        'display_name' => 'Create or reuse payment token?',
        'enabled'      => $form === 'sign',
        'name'         => 'use_existing_token',
        'options'      => ['Use existing token', 'Create new token'],
        'required'     => FALSE,
        'type'         => 'select',
      ],
      'payment_token_id' => [
        'default'      => CRM_Utils_Array::value('payment_token_id', $defaults),
        'display_name' => 'Payment token ID',
        'enabled'      => TRUE,
        'name'         => 'payment_token_id',
        'options'      => $paymentTokenOptions,
        'required'     => FALSE,
        'type'         => 'select',
      ],
      'payment_processor_id' => [
        'default'      => CRM_Utils_Array::value('payment_processor_id', $defaults),
        'display_name' => 'Payment processor',
        'enabled'      => $form === 'sign',
        'name'         => 'payment_processor_id',
        'options'      => self::getPaymentProcessors(),
        'required'     => !$useExistingToken,
        'type'         => 'select',
      ],
      'stored_payment_method_id' => [
        'default'      => NULL,
        'display_name' => 'Stored payment method ID',
        'enabled'      => $form === 'sign',
        'name'         => 'stored_payment_method_id',
        'required'     => !$useExistingToken,
        'settings'     => [],
        'type'         => 'text',
      ],
      'shopper_reference' => [
        'default'      => NULL,
        'display_name' => 'Shopper reference',
        'enabled'      => $form === 'sign',
        'name'         => 'shopper_reference',
        'required'     => !$useExistingToken,
        'settings'     => [ 'class' => 'huge' ],
        'type'         => 'text',
      ],
      'billing_first_name' => [
        'default'      => NULL,
        'display_name' => 'Billing first name',
        'enabled'      => $form === 'sign',
        'name'         => 'billing_first_name',
        'required'     => FALSE,
        'settings'     => [],
        'type'         => 'text',
      ],
      'billing_last_name' => [
        'default'      => NULL,
        'display_name' => 'Billing last name',
        'enabled'      => $form === 'sign',
        'name'         => 'billing_last_name',
        'required'     => FALSE,
        'settings'     => [],
        'type'         => 'text',
      ],
      'email' => [
        'default'      => NULL,
        'display_name' => 'E-Mail',
        'enabled'      => $form === 'sign',
        'name'         => 'email',
        'required'     => FALSE,
        'settings'     => [ 'class' => 'big' ],
        'type'         => 'text',
      ],
      'account_number' => [
        'default'      => NULL,
        'display_name' => 'Account number',
        'enabled'      => $form === 'sign',
        'name'         => 'account_number',
        'required'     => FALSE,
        'settings'     => [ 'class' => 'big' ],
        'type'         => 'text',
      ],
      'expiry_date' => [
        'default'      => NULL,
        'display_name' => 'Expiry date',
        'enabled'      => $form === 'sign',
        'name'         => 'expiry_date',
        'required'     => FALSE,
        'settings'     => [],
        'type'         => 'date',
      ],
      'ip_address' => [
        'default'      => NULL,
        'display_name' => 'IP address',
        'enabled'      => $form === 'sign',
        'name'         => 'ip_address',
        'required'     => FALSE,
        'settings'     => [],
        'type'         => 'text',
      ],
    ];
  }

  public static function formVars($params = []) {
    // ...

    return [
      'default_currency' => Civi::settings()->get('defaultCurrency'),
      'payment_token_fields' => [
        'account_number',
        'billing_first_name',
        'billing_last_name',
        'email',
        'expiry_date',
        'ip_address',
        'payment_processor_id',
        'shopper_reference',
        'stored_payment_method_id',
      ],
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
    switch ($apiEndpoint) {
      case 'Contract.create': {
        $startDate = CRM_Utils_Date::processDate($submitted['start_date'], NULL, NULL, 'Y-m-d H:i:s');

        $apiParams = [
          'payment_method.amount'                => CRM_Contract_Utils::formatMoney($submitted['amount']),
          'payment_method.campaign_id'           => $submitted['campaign_id'],
          'payment_method.cycle_day'             => $submitted['cycle_day'],
          'payment_method.frequency_interval'    => 12 / (int) $submitted['frequency'],
          'payment_method.frequency_unit'        => 'month',
          'payment_method.payment_instrument_id' => $submitted['pa-adyen-payment_instrument_id'],
          'payment_method.start_date'            => $startDate,
        ];

        if ($submitted['pa-adyen-use_existing_token'] === '0') {
          $apiParams['payment_method.payment_token_id'] = $submitted['pa-adyen-payment_token_id'];
          return $apiParams;
        }

        $apiParams += [
          'payment_method.account_number'           => $submitted['pa-adyen-account_number'],
          'payment_method.billing_first_name'       => $submitted['pa-adyen-billing_first_name'],
          'payment_method.billing_last_name'        => $submitted['pa-adyen-billing_last_name'],
          'payment_method.email'                    => $submitted['pa-adyen-email'],
          'payment_method.expiry_date'              => $submitted['pa-adyen-expiry_date'],
          'payment_method.ip_address'               => $submitted['pa-adyen-ip_address'],
          'payment_method.payment_processor_id'     => $submitted['pa-adyen-payment_processor_id'],
          'payment_method.shopper_reference'        => $submitted['pa-adyen-shopper_reference'],
          'payment_method.stored_payment_method_id' => $submitted['pa-adyen-stored_payment_method_id'],
        ];

        return $apiParams;
      }

      case 'Contract.modify': {
        $frequency = (int) $submitted['frequency'];
        $amount = (float) CRM_Contract_Utils::formatMoney($submitted['amount']);

        $apiParams = [
          'membership_payment.cycle_day'            => $submitted['cycle_day'],
          'membership_payment.membership_annual'    => $frequency * $amount,
          'membership_payment.membership_frequency' => $frequency,
          'payment_method.payment_instrument_id'    => $submitted['pa-adyen-payment_instrument_id'],
          'payment_method.payment_token_id'         => $submitted['pa-adyen-payment_token_id'],
        ];

        return $apiParams;
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
    Api4\ContributionRecur::update()
      ->addWhere('id', '=', $recurring_contribution_id)
      ->addValue('contribution_status_id:name', 'Paused')
      ->execute();
  }

  public static function resume($recurringContributionID, $update = []) {
    if (count($update) < 1) {
      Api4\ContributionRecur::update()
        ->addWhere('id', '=', $recurringContributionID)
        ->addValue('contribution_status_id:name', 'Pending')
        ->execute();

      return $recurringContributionID;
    }

    $pendingOptVal = (int) CRM_Contract_Utils::getOptionValue(
      'contribution_recur_status',
      'Pending'
    );

    $update['contribution_status_id'] = CRM_Utils_Array::value(
      'contribution_status_id',
      $update,
      $pendingOptVal
    );

    return self::update($recurringContributionID, $update);
  }

  public static function revive($recurringContributionID, $update = []) {
    $update['cancel_date'] = NULL;
    $update['cancel_reason'] = NULL;

    $pendingOptVal = (int) CRM_Contract_Utils::getOptionValue(
      'contribution_recur_status',
      'Pending'
    );

    $update['contribution_status_id'] = CRM_Utils_Array::value(
      'contribution_status_id',
      $update,
      $pendingOptVal
    );

    return self::update($recurringContributionID, $update);
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
      ->addSelect(
        'amount',
        'campaign_id',
        'contact_id',
        'contribution_status_id',
        'currency',
        'cycle_day',
        'financial_type_id',
        'frequency_interval',
        'frequency_unit',
        'payment_instrument_id',
        'payment_processor_id',
        'payment_token_id',
        'processor_id',
        'start_date'
      )
      ->addWhere('id', '=', $recurringContributionID)
      ->execute()
      ->first();

    self::terminate($recurringContributionID);

    $defaultCampaign = CRM_Utils_Array::value('campaign_id', $oldRC, NULL);
    $cycleDay = CRM_Utils_Array::value('cycle_day', $params, $oldRC['cycle_day']);
    $startDate = new DateTime(CRM_Utils_Array::value('start_date', $params, 'now'));

    $nextSchedContribDate = self::getNextScheduledContributionDate(
      $oldRC['id'],
      $cycleDay,
      $startDate->format('Y-m-d')
    );

    $defaultShopperReference = $oldRC['processor_id'];

    if (
      isset($params['payment_token_id'])
      && $params['payment_token_id'] !== $oldRC['payment_token_id']
    ) {
      $defaultShopperReference = Api4\ContributionRecur::get()
        ->addWhere('payment_token_id', '=', $params['payment_token_id'])
        ->addSelect('processor_id')
        ->setLimit(1)
        ->execute()
        ->first()['processor_id'];
    }

    $recurContribParamMapping = [
      //                                | original name            | required | default                          |
      'amount'                       => [ 'amount'                 , FALSE    , $oldRC['amount']                 ],
      'campaign_id'                  => [ 'campaign_id'            , FALSE    , $defaultCampaign                 ],
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
      'processor_id'                 => [ NULL                     , FALSE    , $defaultShopperReference         ],
      'start_date'                   => [ 'start_date'             , FALSE    , $oldRC['start_date']             ],
      'trxn_id'                      => [ NULL                     , FALSE    , NULL                             ],
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

  private static function getPaymentProcessors() {
    $paymentProcessors = [];

    $ppResult = Api4\PaymentProcessor::get()
      ->addSelect('title')
      ->addWhere('payment_processor_type_id:name', '=', 'Adyen')
      ->execute();

    foreach ($ppResult as $processor) {
      $paymentProcessors[$processor['id']] = $processor['title'];
    }

    return $paymentProcessors;
  }

  private static function getPaymentTokensForContact($contactID) {
    $paymentTokens = [];

    $ptResult = Api4\PaymentToken::get()
      ->addWhere('contact_id', '=', $contactID)
      ->addSelect(
        'expiry_date',
        'masked_account_number',
        'payment_processor_id.name',
        'payment_processor_id.payment_instrument_id:label'
      )
      ->execute();

    foreach ($ptResult as $token) {
      $processorName = $token['payment_processor_id.name'];
      $accountNumber = $token['masked_account_number'];
      $paymentInstrument = $token['payment_processor_id.payment_instrument_id:label'];
      $paymentTokens[$token['id']] = "$processorName: $paymentInstrument $accountNumber";

      if ($paymentInstrument !== 'Credit Card') continue;

      $expiryDate = (new Datetime($token['expiry_date']))->format('m/Y'); 
      $paymentTokens[$token['id']] .= " ($expiryDate)";
    }

    return $paymentTokens;
  }

}

?>
