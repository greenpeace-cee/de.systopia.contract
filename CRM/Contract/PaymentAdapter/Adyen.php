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
      $paymentToken = Api4\PaymentToken::get(FALSE)
        ->addWhere('id', '=', $params['payment_token_id'])
        ->addSelect('payment_processor_id', 'cr.payment_instrument_id', 'cr.processor_id')
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
        'checkPermissions' => FALSE,
        'values'           => self::mapParameters($paymentTokenParamMapping, $params),
      ])->first();
    }

    $paymentProcessorID = $paymentToken['payment_processor_id'];
    $defaultShopperReference = CRM_Utils_Array::value('cr.processor_id', $paymentToken);

    $inProgressOptVal = (int) CRM_Contract_Utils::getOptionValue('contribution_recur_status', 'In Progress');
    $defaultCurrency = Civi::settings()->get('defaultCurrency');
    $memberDuesTypeID = CRM_Contract_Utils::getFinancialTypeID('Member Dues');

    $cycleDay = (int) CRM_Utils_Array::value('cycle_day', $params);

    $startDate = self::startDate([
      'cycle_day' => CRM_Utils_Array::value('cycle_day', $params),
      'min_date'  => CRM_Utils_Array::value('start_date', $params),
    ]);

    $recurContribParamMapping = [
      //                                | original name            | required          | default                     |
      'amount'                       => [ 'amount'                 , TRUE              , NULL                        ],
      'campaign_id'                  => [ 'campaign_id'            , FALSE             , NULL                        ],
      'contact_id'                   => [ 'contact_id'             , TRUE              , NULL                        ],
      'contribution_status_id'       => [ 'contribution_status_id' , FALSE             , $inProgressOptVal           ],
      'currency'                     => [ 'currency'               , FALSE             , $defaultCurrency            ],
      'cycle_day'                    => [ 'cycle_day'              , FALSE             , $startDate->format('d')     ],
      'financial_type_id'            => [ 'financial_type_id'      , FALSE             , $memberDuesTypeID           ],
      'frequency_interval'           => [ 'frequency_interval'     , FALSE             , 1                           ],
      'frequency_unit:name'          => [ 'frequency_unit'         , FALSE             , 'month'                     ],
      'next_sched_contribution_date' => [ NULL                     , FALSE             , $startDate->format('Y-m-d') ],
      'payment_instrument_id'        => [ 'payment_instrument_id'  , FALSE             , NULL                        ],
      'payment_processor_id'         => [ NULL                     , FALSE             , $paymentProcessorID         ],
      'payment_token_id'             => [ NULL                     , FALSE             , $paymentToken['id']         ],
      'processor_id'                 => [ 'shopper_reference'      , !$useExistingToken, $defaultShopperReference    ],
      'start_date'                   => [ NULL                     , FALSE             , $startDate->format('Y-m-d') ],
      'trxn_id'                      => [ NULL                     , FALSE             , NULL                        ],
    ];

    $createParams = self::mapParameters($recurContribParamMapping, $params);

    if ($useExistingToken && isset($paymentToken['cr.payment_instrument_id'])) {
      $createParams['payment_instrument_id'] = $paymentToken['cr.payment_instrument_id'];
    }

    $recurContribID = civicrm_api4('ContributionRecur', 'create', [
      'checkPermissions' => FALSE,
      'values'           => $createParams,
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
    $originalRC = Api4\ContributionRecur::get(FALSE)
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

    $cycleDay = CRM_Utils_Array::value('cycle_day', $update, $originalRC['cycle_day']);
    $deferPaymentStart = CRM_Utils_Array::value('defer_payment_start', $update, TRUE);
    $minDate = CRM_Utils_Array::value('start_date', $update, $originalRC['start_date']);

    $update['start_date'] = self::startDate([
      'cycle_day'           => $cycleDay,
      'defer_payment_start' => $deferPaymentStart,
      'membership_id'       => $update['membership_id'],
      'min_date'            => $minDate,
    ])->format('Y-m-d');

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
      $defaults= Api4\ContributionRecur::get(FALSE)
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
      'payment_instrument_id' => [
        'default'      => CRM_Utils_Array::value('payment_instrument_id', $defaults),
        'display_name' => 'Payment instrument',
        'enabled'      => $form === 'sign',
        'name'         => 'payment_instrument_id',
        'options'      => CRM_Contract_FormUtils::getOptionValueLabels('payment_instrument'),
        'required'     => FALSE,
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
    $paymentTokenFields = [
      'account_number',
      'billing_first_name',
      'billing_last_name',
      'email',
      'expiry_date',
      'ip_address',
      'payment_instrument_id',
      'payment_processor_id',
      'shopper_reference',
      'stored_payment_method_id',
    ];

    return [
      'cycle_days'           => self::cycleDays(),
      'default_currency'     => Civi::settings()->get('defaultCurrency'),
      'payment_token_fields' => $paymentTokenFields,
    ];
  }

  public static function isInstance($recurringContributionID) {
    $paymentProcessorType = Api4\ContributionRecur::get(FALSE)
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
          'payment_method.payment_token_id'         => $submitted['pa-adyen-payment_token_id'],
        ];

        return $apiParams;
      }

      default: {
        return [];
      }
    }
  }

  public static function nextScheduledContributionDate($event) {
    $cycle_day = (int) $event->cycle_day;
    $frequency_interval = (int) $event->frequency_interval;
    $frequency_unit = $event->frequency_unit;
    $rc_id = $event->contribution_recur_id;

    $latestContribResult = Api4\Contribution::get(FALSE)
      ->addWhere('contribution_recur_id', '=', $rc_id)
      ->addSelect('receive_date')
      ->addOrderBy('receive_date', 'DESC')
      ->setLimit(1)
      ->execute();

    if ($latestContribResult->count() < 1) return;

    $latestContribution = $latestContribResult->first();

    $coveredUntil = CRM_Contract_DateHelper::nextRegularDate(
      $latestContribution['receive_date'],
      (int) $frequency_interval,
      $frequency_unit
    );

    $nextSchedContribDate = CRM_Contract_DateHelper::findNextOfDays(
      [$cycle_day],
      $coveredUntil->format('Y-m-d')
    );

    Api4\ContributionRecur::update(FALSE)
      ->addWhere('id', '=', $rc_id)
      ->addValue('next_sched_contribution_date', $nextSchedContribDate->format('Y-m-d'))
      ->execute();
  }

  public static function pause($recurring_contribution_id) {
    Api4\ContributionRecur::update(FALSE)
      ->addWhere('id', '=', $recurring_contribution_id)
      ->addValue('contribution_status_id:name', 'Paused')
      ->execute();
  }

  public static function resume($recurringContributionID, $update = []) {
    if (count($update) < 1) {
      Api4\ContributionRecur::update(FALSE)
        ->addWhere('id', '=', $recurringContributionID)
        ->addValue('contribution_status_id:name', 'In Progress')
        ->execute();

      return $recurringContributionID;
    }

    $inProgressOptVal = (int) CRM_Contract_Utils::getOptionValue(
      'contribution_recur_status',
      'In Progress'
    );

    $update['contribution_status_id'] = CRM_Utils_Array::value(
      'contribution_status_id',
      $update,
      $inProgressOptVal
    );

    return self::update($recurringContributionID, $update);
  }

  public static function revive($recurringContributionID, $update = []) {
    $update['cancel_date'] = NULL;
    $update['cancel_reason'] = NULL;

    $inProgressOptVal = (int) CRM_Contract_Utils::getOptionValue(
      'contribution_recur_status',
      'In Progress'
    );

    $update['contribution_status_id'] = CRM_Utils_Array::value(
      'contribution_status_id',
      $update,
      $inProgressOptVal
    );

    return self::update($recurringContributionID, $update);
  }

  public static function startDate($params = [], $today = 'now') {
    $today = new DateTimeImmutable($today);
    $start_date = DateTime::createFromImmutable($today);

    // Minimum date

    if (isset($params['min_date'])) {
      $min_date = new DateTimeImmutable($params['min_date']);
    }

    if (isset($min_date) && $start_date->getTimestamp() < $min_date->getTimestamp()) {
      $start_date = DateTime::createFromImmutable($min_date);
    }

    // Existing contract

    if (isset($params['membership_id'])) {
      $membership = CRM_Contract_Utils::getMembershipByID($params['membership_id']);
      $membership_start_date = new DateTimeImmutable($membership['start_date']);

      if ($start_date->getTimestamp() < $membership_start_date->getTimestamp()) {
        $start_date = DateTime::createFromImmutable($membership_start_date);
      }

      $recurring_contribution = CRM_Contract_RecurringContribution::getCurrentForContract(
        $membership['id']
      );
    }

    if (isset($recurring_contribution)) {
      $params['cycle_day'] = CRM_Utils_Array::value(
        'cycle_day',
        $params,
        $recurring_contribution['cycle_day']
      );
    }

    // Defer payment start

    $defer_payment_start = CRM_Utils_Array::value('defer_payment_start', $params, FALSE);

    if ($defer_payment_start && isset($params['membership_id'])) {
      $latest_contribution = CRM_Contract_RecurringContribution::getLatestContribution(
        $params['membership_id']
      );
    }

    if (isset($latest_contribution)) {
      $latest_contribution_rc = CRM_Contract_RecurringContribution::getById(
        $latest_contribution['contribution_recur_id']
      );

      $last_regular_date = CRM_Contract_DateHelper::findLastOfDays(
        [(int) $latest_contribution_rc['cycle_day']],
        $latest_contribution['receive_date']
      );

      $paid_until = CRM_Contract_DateHelper::nextRegularDate(
        $last_regular_date->format('Y-m-d'),
        $latest_contribution_rc['frequency_interval'],
        $latest_contribution_rc['frequency_unit']
      );

      if ($start_date->getTimestamp() < $paid_until->getTimestamp()) {
        $start_date = $paid_until;
      }
    }

    // Allowed cycle days

    $allowed_cycle_days = self::cycleDays();

    $start_date = CRM_Contract_DateHelper::findNextOfDays(
      $allowed_cycle_days,
      $start_date->format('Y-m-d')
    );

    $cycle_day = (int) (
      isset($params['cycle_day'])
      ? $params['cycle_day']
      : $start_date->format('d')
    );

    if (!in_array($cycle_day, $allowed_cycle_days, TRUE)) {
      throw new Exception("Cycle day $cycle_day is not allowed for Adyen payments");
    }

    // Find next date for expected cycle day

    $start_date = CRM_Contract_DateHelper::findNextOfDays(
      [$cycle_day],
      $start_date->format('Y-m-d')
    );

    return $start_date;
  }

  public static function terminate($recurringContributionID, $reason = "CHNG") {
    $now = date('Y-m-d H:i:s');

    Api4\ContributionRecur::update(FALSE)
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
    $oldRC = Api4\ContributionRecur::get(FALSE)
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

    if (array_key_exists('campaign_id', $params) && empty($params['campaign_id'])) {
      // Remove invalid campaign IDs (GP-34548)
      $params['campaign_id'] = NULL;
    }

    $cycleDay = CRM_Utils_Array::value('cycle_day', $params, $oldRC['cycle_day']);
    $deferPaymentStart = CRM_Utils_Array::value('defer_payment_start', $params, TRUE);
    $minDate = CRM_Utils_Array::value('start_date', $params, $oldRC['start_date']);

    $startDate = self::startDate([
      'cycle_day'           => $cycleDay,
      'defer_payment_start' => $deferPaymentStart,
      'membership_id'       => $params['membership_id'],
      'min_date'            => $minDate,
    ]);

    $defaultShopperReference = $oldRC['processor_id'];

    if (
      isset($params['payment_token_id'])
      && $params['payment_token_id'] !== $oldRC['payment_token_id']
    ) {
      $defaultShopperReference = Api4\ContributionRecur::get(FALSE)
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
      'next_sched_contribution_date' => [ NULL                     , FALSE    , $startDate->format('Y-m-d')      ],
      'payment_processor_id'         => [ 'payment_processor_id'   , FALSE    , $oldRC['payment_processor_id']   ],
      'payment_token_id'             => [ 'payment_token_id'       , FALSE    , $oldRC['payment_token_id']       ],
      'processor_id'                 => [ NULL                     , FALSE    , $defaultShopperReference         ],
      'start_date'                   => [ NULL                     , FALSE    , $startDate->format('Y-m-d')      ],
      'trxn_id'                      => [ NULL                     , FALSE    , NULL                             ],
    ];

    $updateParams = self::mapParameters($recurContribParamMapping, $params);

    $existingRC = Api4\ContributionRecur::get(FALSE)
      ->addWhere('payment_token_id', '=', $updateParams['payment_token_id'])
      ->addSelect('payment_instrument_id')
      ->setLimit(1)
      ->execute()
      ->first();

    $updateParams['payment_instrument_id'] = $existingRC['payment_instrument_id'];

    $newRecurringContribution = civicrm_api4('ContributionRecur', 'create', [
      'checkPermissions' => FALSE,
      'values'           => $updateParams
    ])->first();

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

    $ppResult = Api4\PaymentProcessor::get(FALSE)
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

    $ptResult = Api4\PaymentToken::get(FALSE)
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
