<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Form_RapidCreate_PL extends CRM_Core_Form {

  function buildQuickForm() {
    CRM_Core_Resources::singleton()
      ->addScriptFile('de.systopia.contract', 'templates/CRM/Contract/Form/RapidCreate/PL.js');
    CRM_Core_Resources::singleton()
      ->addScriptFile('de.systopia.contract', 'js/rapidcreate_address_autocomplete.js', 10, 'page-header');
    // ### Contact information ###
    $genders = array_column(civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'gender',
      'is_active'       => 1,
      'options'         => ['limit' => 0]
    ])['values'], 'label', 'value');
    $this->add('select', 'gender_id', 'Gender', $genders, TRUE);
    $this->add('text', 'formal_title', 'Title', ['class' => 'huge']);
    $this->add('text', 'first_name', 'First name', ['class' => 'huge']);
    $this->add('text', 'last_name', 'Last name', ['class' => 'huge'], TRUE);
    $this->add('text', 'phone', 'Phone', ['class' => 'huge']);
    $this->add('text', 'email', 'Email', ['class' => 'huge']);
    $this->add('text', 'street_address', 'Address', ['class' => 'huge']);
    $this->add('text', 'postal_code', 'Postcode', ['class' => 'huge']);
    $this->add('text', 'city', 'City', ['class' => 'huge']);

    $this->addChainSelect('state_province_id');

    $country = ['' => ts('- select -')] + CRM_Core_PseudoConstant::country();
    $this->add('select', 'country_id', ts('Country'), $country, TRUE, ['class' => 'crm-select2']);

    $this->addDate('birth_date', 'Date of Birth', TRUE, ['formatType' => 'birth']);

    $this->addCheckbox('community_newsletter', 'Add to Community newsletter', ['' => TRUE]);

    $this->addCheckbox('post_delivery_only_online', 'Post delivery only online', ['' => TRUE]);

    $this->add('select', 'talk_topic', 'Talk topic', [
      '' => "- none -",
      'Ökobürger' => "DD Ökobürger",
      'Rationalisten' => "DD Rationalisten",
      'Tierfreunde' => "DD Tierfreunde",
      'Aktivisten' => "DD Aktivisten",
    ]);

    $this->add('select', 'groups', 'Additional groups', [
      '' => "- none -",
      'kein ACT' => "kein ACT",
      'kein Danke' => "kein Danke",
      'kein Kalender' => "kein Kalender",
      'keine Geburtstagsgratulation' => "keine Geburtstagsgratulation",
      'keine Geschenke' => "keine Geschenke",
      'keine Lotterie' => "keine Lotterie",
    ], NULL, ['class' => 'crm-select2', 'multiple' => 'multiple']);

    // ### Mandate information ###
    CRM_Core_Resources::singleton()->addVars('de.systopia.contract', [
      'creditor' => CRM_Contract_SepaLogic::getCreditor(),
      'frequencies' => CRM_Contract_SepaLogic::getPaymentFrequencies(),
    ]);
    CRM_Contract_SepaLogic::addJsSepaTools();

    $this->add('select', 'cycle_day', ts('Cycle day'), CRM_Contract_SepaLogic::getCycleDays());
    $this->add('text', 'iban', ts('IBAN'), ['class' => 'huge'], TRUE);
    $this->add('text', 'bic', ts('BIC'), NULL, TRUE);
    $this->add('text', 'payment_amount', ts('Installment amount'), ['size' => 6], TRUE);
    $this->add('select', 'payment_frequency', ts('Payment Frequency'), CRM_Contract_SepaLogic::getPaymentFrequencies(), TRUE);
    $this->assign('bic_lookup_accessible', CRM_Contract_SepaLogic::isLittleBicExtensionAccessible());

    // ### Contract information ###
    $this->addDate('join_date', ts('Member since'), TRUE, ['formatType' => 'activityDate']);
    $this->addDate('start_date', ts('Membership start date'), TRUE, ['formatType' => 'activityDate']);
    $this->add('select', 'campaign_id', ts('Campaign'), CRM_Contract_Configuration::getCampaignList(), TRUE, ['class' => 'crm-select2']);
    foreach (civicrm_api3('MembershipType', 'get', ['options' => ['limit' => 0]])['values'] as $MembershipType) {
      $MembershipTypeOptions[$MembershipType['id']] = $MembershipType['name'];
    };
    $this->add('select', 'membership_type_id', ts('Membership type'), ['' => '- none -'] + $MembershipTypeOptions, TRUE, ['class' => 'crm-select2']);
    foreach (civicrm_api3('Activity', 'getoptions', ['field' => "activity_medium_id", 'options' => ['limit' => 0]])['values'] as $key => $value) {
      $mediumOptions[$key] = $value;
    }
    $this->add('select', 'activity_medium', ts('Source media'), ['' => '- none -'] + $mediumOptions, FALSE, ['class' => 'crm-select2']);
    $this->add('text', 'membership_reference', ts('Reference number'));
    $membershipVenueOptions = [];
    foreach (civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'contract_direct_dialogue_venue',
      'is_active'       => 1,
      'options'         => ['limit' => 0]
    ])['values'] as $optionValue) {
      $membershipVenueOptions[$optionValue['value']] = $optionValue['label'];
    };
    $this->add('select', 'membership_venue', ts('DD-Venue'), ['' => '- none -'] + $membershipVenueOptions, TRUE);
    $this->add('text', 'membership_ts_week', ts('TS Week'), '', TRUE);
    $this->addEntityRef('membership_dialoger', ts('DD-Fundraiser'), [
      'api' => [
        'params' => [
          'contact_type' => 'Individual',
          'contact_sub_type' => 'Dialoger',
        ],
      ],
    ], TRUE);
    foreach (civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'contact_channel',
      'is_active'       => 1,
      'options'         => ['limit' => 0]
    ])['values'] as $optionValue) {
      $membershipChannelOptions[$optionValue['value']] = $optionValue['label'];
    };
    $this->add('select', 'membership_channel', ts('Membership channel'), ['' => '- none -'] + $membershipChannelOptions, TRUE, ['class' => 'crm-select2']);

    if (version_compare(CRM_Utils_System::version(), '4.7', '<')) {
      $this->addWysiwyg('activity_details', ts('Notes'), ['class' => 'huge'], TRUE);
    } else {
      $this->add('wysiwyg', 'activity_details', ts('Notes'));
    }


    $this->addButtons([
      ['type' => 'submit', 'name' => 'Save', 'subName' => 'done', 'isDefault' => TRUE, 'icon' => 'check', 'submitOnce' => TRUE],
      ['type' => 'submit', 'name' => 'Save and new', 'subName' => 'new', 'submitOnce' => TRUE],
      ['type' => 'cancel', 'name' => 'Cancel', 'submitOnce' => TRUE],
    ]);

    $this->setDefaults();

  }

  /**
   * form validation
   */
  function validate() {
    $submitted = $this->exportValues();

    if ($submitted['payment_amount'] && !$submitted['payment_frequency']) {
      HTML_QuickForm::setElementError('payment_frequency', 'Please specify a frequency when specifying an amount');
    }
    if ($submitted['payment_frequency'] && !$submitted['payment_amount']) {
      HTML_QuickForm::setElementError('payment_amount', 'Please specify an amount when specifying a frequency');
    }

    $amount = CRM_Contract_SepaLogic::formatMoney(CRM_Contract_SepaLogic::formatMoney($submitted['payment_amount']) / $submitted['payment_frequency']);
    if ($amount < 0.01) {
      HTML_QuickForm::setElementError('payment_amount', 'Annual amount too small.');
    }

    if (!ctype_digit($submitted['membership_ts_week']) || $submitted['membership_ts_week'] < 1 || $submitted['membership_ts_week'] > 52) {
      HTML_QuickForm::setElementError('membership_ts_week', 'Please enter a value in the range 1-52');
    }

    // SEPA validation
    if (!empty($submitted['iban']) && !CRM_Contract_SepaLogic::validateIBAN($submitted['iban'])) {
      HTML_QuickForm::setElementError('iban', 'Please enter a valid IBAN');
    }
    if (!empty($submitted['iban']) && CRM_Contract_SepaLogic::isOrganisationIBAN($submitted['iban'])) {
      HTML_QuickForm::setElementError('iban', "Do not use any of the organisation's own IBANs");
    }
    if (!empty($submitted['bic']) && !CRM_Contract_SepaLogic::validateBIC($submitted['bic'])) {
      HTML_QuickForm::setElementError('bic', 'Please enter a valid BIC');
    }

    if (!empty($submitted['join_date']) && CRM_Utils_Date::processDate(date('Ymd')) < CRM_Utils_Date::processDate($submitted['join_date'])) {
      HTML_QuickForm::setElementError('join_date', ts('Join date cannot be in the future.'));
    }

    if (!empty($submitted['start_date']) && !empty($submitted['join_date'])) {
      if (CRM_Utils_Date::processDate($submitted['start_date']) < CRM_Utils_Date::processDate($submitted['join_date'])) {
        HTML_QuickForm::setElementError('start_date', ts('Start date must be the same or later than Member since.'));
      }
    }

    return parent::validate();
  }


  function setDefaults($defaultValues = NULL, $filter = NULL) {

    list($defaults['join_date'], $null) = CRM_Utils_Date::setDateDefaults(NULL, 'activityDateTime');
    list($defaults['start_date'], $null) = CRM_Utils_Date::setDateDefaults(NULL, 'activityDateTime');

    // sepa defaults
    $defaults['payment_frequency'] = '12'; // monthly
    $defaults['cycle_day'] = CRM_Contract_SepaLogic::nextCycleDay();

    $config = CRM_Core_Config::singleton();
    $countryDefault = $config->defaultContactCountry;

    if ($countryDefault) {
      $defaults['country_id'] = $countryDefault;
    }

    $defaults['iban'] = 'PL';

    $defaults['membership_type_id'] = civicrm_api3('MembershipType', 'getvalue', [
      'return'  => 'id',
      'name'    => 'Supporter',
      'options' => ['limit' => 0]
    ]);

    $defaults['campaign_id'] = civicrm_api3('Campaign', 'getvalue', [
      'return' => 'id',
      'name' => 'DD',
    ]);

    $defaults['membership_channel'] = 'F2F';

    parent::setDefaults($defaults);
  }

  function postProcess() {
    $submitted = $this->exportValues();

    // Create contact
    $contactParams['gender_id'] = $submitted['gender_id'];
    $contactParams['first_name'] = $submitted['first_name'];
    $contactParams['formal_title'] = $submitted['formal_title'];
    $contactParams['last_name'] = $submitted['last_name'];
    $contactParams['birth_date'] = $submitted['birth_date'];
    $contactParams['contact_type'] = 'Individual';
    $contact = civicrm_api3('Contact', 'create', $contactParams);

    if ($submitted['email']) {
      civicrm_api3('Email', 'create', [
        'contact_id' => $contact['id'],
        'email' => $submitted['email'],
      ]);
    }

    if ($submitted['phone']) {
      civicrm_api3('Phone', 'create', [
        'contact_id' => $contact['id'],
        'phone' => $submitted['phone'],
        'phone_type_id' => 'phone',
        'phone_location_id' => 'home',
      ]);
    }

    if ($submitted['street_address'] || $submitted['city'] || $submitted['postal_code']) {
      civicrm_api3('Address', 'create', [
        'contact_id' => $contact['id'],
        'street_address' => $submitted['street_address'],
        'city' => $submitted['city'],
        'postal_code' => $submitted['postal_code'],
        'state_province_id' => $submitted['state_province_id'],
        'country_id' => $submitted['country_id'],
        'location_type_id' => 'home',
      ]);
    }

    if ($submitted['groups']) {
      foreach ($submitted['groups'] as $groupTitle) {
        $group = civicrm_api3('Group', 'getsingle', ['title' => $groupTitle]);

        civicrm_api3('GroupContact', 'create', [
          'contact_id' => $contact['id'],
          'group_id' => $group['id'],
        ]);
      }
    }

    if ($submitted['talk_topic']) {
      $talktopic = civicrm_api3('Group', 'getsingle', ['title' => $submitted['talk_topic']]);
      civicrm_api3('GroupContact', 'create', [
          'contact_id' => $contact['id'],
          'group_id' => $talktopic['id'],
        ]
      );
    }

    if (isset($submitted['community_newsletter'])) {
      $newsletter = civicrm_api3('Group', 'getsingle', ['title' => "Community NL"]);
      civicrm_api3('GroupContact', 'create', [
          'contact_id' => $contact['id'],
          'group_id' => $newsletter['id'],
        ]
      );
    }
    if (isset($submitted['post_delivery_only_online'])) {
      $postdeliveryonlyonline = civicrm_api3('Group', 'getsingle', ['title' => "Zusendungen nur online"]);
      civicrm_api3('GroupContact', 'create', [
          'contact_id' => $contact['id'],
          'group_id' => $postdeliveryonlyonline['id'],
        ]
      );
    }

    // Create mandate
    if ($submitted['cycle_day'] < 1 || $submitted['cycle_day'] > 30) {
      // invalid cycle day
      $submitted['cycle_day'] = CRM_Contract_SepaLogic::nextCycleDay();
    }

    // calculate amount
    $amount = CRM_Contract_SepaLogic::formatMoney($submitted['payment_amount']);
    $frequency_interval = 12 / $submitted['payment_frequency'];
    $new_mandate = CRM_Contract_SepaLogic::createNewMandate([
      'type' => 'RCUR',
      'contact_id' => $contact['id'],
      'amount' => $amount,
      'currency' => CRM_Contract_SepaLogic::getCreditor()->currency,
      'start_date' => CRM_Utils_Date::processDate($submitted['start_date'], NULL, NULL, 'Y-m-d H:i:s'),
      'creation_date' => date('YmdHis'), // NOW
      'date' => CRM_Utils_Date::processDate($submitted['start_date'], NULL, NULL, 'Y-m-d H:i:s'),
      'validation_date' => date('YmdHis'), // NOW
      'iban' => $submitted['iban'],
      'bic' => $submitted['bic'],
      // 'source'             => ??
      'campaign_id' => $submitted['campaign_id'],
      'financial_type_id' => 2, // Membership Dues
      'frequency_unit' => 'month',
      'cycle_day' => $submitted['cycle_day'],
      'frequency_interval' => $frequency_interval,
    ]);


    $contractParams['contact_id'] = $contact['id'];
    $contractParams['membership_type_id'] = $submitted['membership_type_id'];
    $contractParams['start_date'] = CRM_Utils_Date::processDate($submitted['start_date'], NULL, NULL, 'Y-m-d H:i:s');
    $contractParams['join_date'] = CRM_Utils_Date::processDate($submitted['join_date'], NULL, NULL, 'Y-m-d H:i:s');

    $contractParams['campaign_id'] = $submitted['campaign_id'];

    // 'Custom' fields
    $contractParams['membership_payment.membership_recurring_contribution'] = $new_mandate['entity_id'];
    $contractParams['membership_general.membership_reference'] = $submitted['membership_reference'];
    $contractParams['membership_general.dirdiavenue'] = $submitted['membership_venue'];
    $contractParams['membership_general.ts_week'] = $submitted['membership_ts_week'];
    $contractParams['membership_general.membership_dialoger'] = $submitted['membership_dialoger'];
    $contractParams['membership_general.membership_channel'] = $submitted['membership_channel'];

    $contractParams['note'] = $submitted['activity_details'];
    $contractParams['medium_id'] = $submitted['activity_medium'];

    $contract = civicrm_api3('Contract', 'create', $contractParams);
    $membership_url = CRM_Utils_System::url('civicrm/contact/view/membership', "action=view&cid={$contact['id']}&id={$contract['id']}");
    CRM_Core_Session::setStatus("New Membership <a href=\"{$membership_url}\" style=\"font-weight: bold;\">{$contract['id']}</a> created.", "Success", 'info');

    if (array_key_exists('_qf_PL_submit_new', $submitted)) {
      $this->controller->_destination = CRM_Utils_System::url('civicrm/member/add', "reset=1&action=add&context=standalone");
    } else {
      $this->controller->_destination = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact['id']}");
    }


  }

}
