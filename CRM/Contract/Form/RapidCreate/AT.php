<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Form_RapidCreate_AT extends CRM_Core_Form{

  function buildQuickForm(){
    CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'templates/CRM/Contract/Form/RapidCreate/AT.js');
    CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'js/rapidcreate_address_autocomplete.js', 10, 'page-header');
    // ### Contact information ###
    $prefixes = array_column(civicrm_api3('OptionValue', 'get', ['option_group_id' => 'individual_prefix', 'is_active' => 1, 'options' => ['limit' => 0, 'sort' => 'weight']])['values'], 'label', 'value');
    $this->add('select', 'prefix_id', 'Prefix', $prefixes, true);
    $this->add('text', 'formal_title', 'Title', array('class' => 'huge'));
    $this->add('text', 'first_name', 'First name', array('class' => 'huge'));
    $this->add('text', 'last_name', 'Last name', array('class' => 'huge'), true);
    $this->add('text', 'phone', 'Phone', array('class' => 'huge'));
    $this->add('text', 'email', 'Email', array('class' => 'huge'));
    $this->add('text', 'street_address', 'Address', array('class' => 'huge'));
    $this->add('text', 'postal_code', 'Postcode', array('class' => 'huge'));
    $this->add('text', 'city', 'City', array('class' => 'huge'));

    $this->addChainSelect('state_province_id');

    $country = array('' => ts('- select -')) + CRM_Core_PseudoConstant::country();
    $this->add('select', 'country_id', ts('Country'), $country, TRUE, array('class' => 'crm-select2'));

    $this->addDate('birth_date', 'Date of Birth', true, array('formatType' => 'birth'));

    $this->addCheckbox('community_newsletter', 'Add to Community newsletter', ['' => true]);

    $this->addCheckbox('post_delivery_only_online', 'Post delivery only online', ['' => true]);

    $this->add('select', 'interest', 'Interest', [
      '' => "- none -",
      'Wald' => "Interesse an Wald",
      'Landwirtschaft (aka Gentech)' => "Interesse an Landwirtschaft (aka Gentech)",
      'Meere' => "Interesse an Meeren",
      'Konsum/Marktcheck' => "Interesse an Konsum/Marktcheck",
      'Klima/Arktis' => "Interesse an Klima/Arktis",
      'Atom/Kohle/Erneuerbare' => "Interesse an Atom/Kohle/Erneuerbare"
    ]);

    $this->add('select', 'talk_topic', 'Talk topic', [
      '' => "- none -",
      'Ökobürger' => "DD Ökobürger",
      'Rationalisten' => "DD Rationalisten",
      'Tierfreunde' => "DD Tierfreunde",
      'Aktivisten' => "DD Aktivisten"
    ]);

    $this->add('select', 'groups', 'Additional groups', [
      '' => "- none -",
      'kein ACT' => "kein ACT",
      'kein Danke' => "kein Danke",
      'kein Kalender' => "kein Kalender",
      'keine Geburtstagsgratulation' => "keine Geburtstagsgratulation",
      'keine Geschenke' => "keine Geschenke",
      'keine Lotterie' => "keine Lotterie"
    ], null, array('class' => 'crm-select2', 'multiple' => 'multiple') );

    $this->addCheckbox('tshirt_order', 'Is this a T-shirt order?', ['' => true]);
    // A dropdown-field "Shirt Type" needs to be in rthe form - the T-Shirt types available should be taken from the option group "shirt_type"
    $shirtDesigns = array_column(civicrm_api3('OptionValue', 'get', ['option_group_id' => 'order_type', 'label' => ['LIKE' => '%T-Shirt%'], 'options' => ['limit' => 0, 'sort' => 'weight']])['values'], 'name', 'value');
    $shirtSizes = array_column(civicrm_api3('OptionValue', 'get', ['option_group_id' => 'shirt_size', 'options' => ['limit' => 0, 'sort' => 'weight']])['values'], 'name', 'value');
    $shirtTypes = array_column(civicrm_api3('OptionValue', 'get', ['option_group_id' => 'shirt_type', 'options' => ['limit' => 0, 'sort' => 'weight']])['values'], 'name', 'value');
    $this->add('select', 'shirt_design', 'Shirt design', $shirtDesigns);
    $this->add('select', 'shirt_type', 'Shirt cut', $shirtTypes);
    $this->add('select', 'shirt_size', 'Shirt size', $shirtSizes);


    // ### Mandate information ###
    CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array(
      'creditor'    => CRM_Contract_SepaLogic::getCreditor(),
      'frequencies' => CRM_Contract_SepaLogic::getPaymentFrequencies()));
    CRM_Contract_SepaLogic::addJsSepaTools();

    $this->add('select', 'cycle_day', ts('Cycle day'), CRM_Contract_SepaLogic::getCycleDays());
    $this->add('text',   'iban', ts('IBAN'), array('class' => 'huge'), true);
    $this->add('text',   'bic', ts('BIC'), null, true);
    $this->add('text',   'payment_amount', ts('Installment amount'), array('size' => 6));
    $this->add('select', 'payment_frequency', ts('Payment Frequency'), CRM_Contract_SepaLogic::getPaymentFrequencies());
    $this->assign('bic_lookup_accessible', CRM_Contract_SepaLogic::isLittleBicExtensionAccessible());

    // ### Contract information ###
    $this->addDate('join_date', ts('Member since'), TRUE, array('formatType' => 'activityDate'));
    $this->addDate('start_date', ts('Membership start date'), TRUE, array('formatType' => 'activityDate'));
    $this->add('select', 'campaign_id', ts('Campaign'), CRM_Contract_Configuration::getCampaignList(), TRUE, array('class' => 'crm-select2'));
    // $this->addEntityRef('campaign_id', ts('Campaign'), [
    //   'entity' => 'campaign',
    //   'placeholder' => ts('- none -')
    // ], true);
    // Membership type (membership)
    foreach(civicrm_api3('MembershipType', 'get', ['options' => ['limit' => 0, 'sort' => 'weight']])['values'] as $MembershipType){
      $MembershipTypeOptions[$MembershipType['id']] = $MembershipType['name'];
    };
    $this->add('select', 'membership_type_id', ts('Membership type'), array('' => '- none -') + $MembershipTypeOptions, true, array('class' => 'crm-select2'));
    // Source media (activity)
    foreach(civicrm_api3('Activity', 'getoptions', ['field' => "activity_medium_id", 'options' => ['limit' => 0, 'sort' => 'weight']])['values'] as $key => $value){
      $mediumOptions[$key] = $value;
    }
    $this->add('select', 'activity_medium', ts('Source media'), array('' => '- none -') + $mediumOptions, false, array('class' => 'crm-select2'));
    // Reference number text
    $this->add('text', 'membership_reference', ts('Reference number'));
    // Contract number text
    $this->add('text', 'membership_contract', ts('Contract number'), null, true);
    // DD-Fundraiser
    $this->addEntityRef('membership_dialoger', ts('DD-Fundraiser'), array('api' => array('params' => array('contact_type' => 'Individual', 'contact_sub_type' => 'Dialoger'))), true);
    // Membership channel
    foreach(civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'contact_channel',
      'is_active'       => 1,
      'options'         => ['limit' => 0, 'sort' => 'weight']])['values'] as $optionValue){
      $membershipChannelOptions[$optionValue['value']] = $optionValue['label'];
    };
    $this->add('select', 'membership_channel', ts('Membership channel'), array('' => '- none -') + $membershipChannelOptions, true, array('class' => 'crm-select2'));

    // Notes
    $this->addWysiwyg('activity_details', ts('Notes'), ['class' => 'huge'], TRUE);


    $this->addButtons([
      ['type' => 'submit', 'name' => 'Save', 'subName' => 'done', 'isDefault' => TRUE, 'icon' => 'check'],
      ['type' => 'submit', 'name' => 'Save and new', 'subName' => 'new'],
      ['type' => 'cancel', 'name' => 'Cancel'],
    ]);

    $this->setDefaults();

  }

  /**
   * form validation
   */
  function validate() {
    $submitted = $this->exportValues();

    if($submitted['payment_amount'] && !$submitted['payment_frequency']){
      HTML_QuickForm::setElementError ( 'payment_frequency', 'Please specify a frequency when specifying an amount');
    }
    if($submitted['payment_frequency'] && !$submitted['payment_amount']){
      HTML_QuickForm::setElementError ( 'payment_amount', 'Please specify an amount when specifying a frequency');
    }

    $amount = CRM_Contract_SepaLogic::formatMoney($submitted['payment_amount'] / $submitted['payment_frequency']);
    if ($amount < 0.01) {
      HTML_QuickForm::setElementError ( 'payment_amount', 'Annual amount too small.');
    }

    // SEPA validation
    if (!empty($submitted['iban']) && !CRM_Contract_SepaLogic::validateIBAN($submitted['iban'])) {
      HTML_QuickForm::setElementError ( 'iban', 'Please enter a valid IBAN');
    }
    if (!empty($submitted['iban']) && CRM_Contract_SepaLogic::isOrganisationIBAN($submitted['iban'])) {
      HTML_QuickForm::setElementError ( 'iban', "Do not use any of the organisation's own IBANs");
    }
    if (!empty($submitted['bic']) && !CRM_Contract_SepaLogic::validateBIC($submitted['bic'])) {
      HTML_QuickForm::setElementError ( 'bic', 'Please enter a valid BIC');
    }

    // contract number
    if (!empty($submitted['membership_contract'])) {
      $reference_error = CRM_Contract_Validation_ContractNumber::verifyContractNumber($submitted['membership_contract']);
      if ($reference_error) {
        HTML_QuickForm::setElementError ( 'membership_contract', $reference_error);
      }
    }

    return parent::validate();
  }


  function setDefaults($defaultValues = null, $filter = null){

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

    parent::setDefaults($defaults);
  }

  function postProcess(){
    $submitted = $this->exportValues();

    // Create contact
    $contactParams['prefix_id']    = $submitted['prefix_id'];
    $contactParams['gender_id']    = CRM_Contract_Configuration::getGenderID($submitted['prefix_id']);
    $contactParams['first_name']   = $submitted['first_name'];
    $contactParams['formal_title'] = $submitted['formal_title'];
    $contactParams['last_name']    = $submitted['last_name'];
    $contactParams['birth_date']   = $submitted['birth_date'];
    $contactParams['contact_type'] = 'Individual';
    $contact = civicrm_api3('Contact', 'create', $contactParams);

    if($submitted['email']){
      civicrm_api3('Email', 'create', ['contact_id' => $contact['id'], 'email' => $submitted['email']]);
    }

    if($submitted['phone']){
      civicrm_api3('Phone', 'create', [
        'contact_id' => $contact['id'],
        'phone' => $submitted['phone'],
        'phone_type_id' => 'phone',
        'phone_location_id' => 'home'
      ]);
    }

    if($submitted['street_address'] || $submitted['city'] || $submitted['postal_code']){
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

    if($submitted['groups']){
      foreach($submitted['groups'] as $groupTitle){
        $group = civicrm_api3('Group', 'getsingle', [ 'title' => $groupTitle]);

        civicrm_api3('GroupContact', 'create', [
          'contact_id' => $contact['id'],
          'group_id' => $group['id']
        ]);
      }
    }

    if($submitted['talk_topic']){
      $talktopic = civicrm_api3('Group', 'getsingle', [ 'title' => $submitted['talk_topic']]);
      civicrm_api3('GroupContact', 'create', [
        'contact_id' => $contact['id'],
        'group_id' => $talktopic['id']]
      );
    }

    if($submitted['interest']){
      $interest = civicrm_api3('Group', 'getsingle', [ 'title' => $submitted['interest']]);
      civicrm_api3('GroupContact', 'create', [
        'contact_id' => $contact['id'],
        'group_id' => $interest['id']]
      );
    }

    if(isset($submitted['community_newsletter'])){
      $newsletter = civicrm_api3('Group', 'getsingle', [ 'title' => "Community NL"]);
      civicrm_api3('GroupContact', 'create', [
        'contact_id' => $contact['id'],
        'group_id' => $newsletter['id']]
      );
    }
    if(isset($submitted['post_delivery_only_online'])){
      $postdeliveryonlyonline = civicrm_api3('Group', 'getsingle', [ 'title' => "Zusendungen nur online"]);
      civicrm_api3('GroupContact', 'create', [
        'contact_id' => $contact['id'],
        'group_id' => $postdeliveryonlyonline['id']]
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
    $new_mandate = CRM_Contract_SepaLogic::createNewMandate(array(
      'type'               => 'RCUR',
      'contact_id'         => $contact['id'],
      'amount'             => $amount,
      'currency'           => CRM_Contract_SepaLogic::getCreditor()->currency,
      'start_date'         => CRM_Utils_Date::processDate($submitted['start_date'], null, null, 'Y-m-d H:i:s'),
      'creation_date'      => date('YmdHis'), // NOW
      'date'               => CRM_Utils_Date::processDate($submitted['start_date'], null, null, 'Y-m-d H:i:s'),
      'validation_date'    => date('YmdHis'), // NOW
      'iban'               => $submitted['iban'],
      'bic'                => $submitted['bic'],
      // 'source'             => ??
      'campaign_id'        => $submitted['campaign_id'],
      'financial_type_id'  => 2, // Membership Dues
      'frequency_unit'     => 'month',
      'cycle_day'          => $submitted['cycle_day'],
      'frequency_interval' => $frequency_interval,
    ));
    $contractParams['membership_payment.membership_recurring_contribution'] = $new_mandate['entity_id'];
    $contractParams['membership_general.membership_dialoger'] = $submitted['membership_dialoger']; // DD fundraiser

    $contractParams['contact_id'] = $contact['id'];
    $contractParams['membership_type_id'] = $submitted['membership_type_id'];
    $contractParams['start_date'] = CRM_Utils_Date::processDate($submitted['start_date'], null, null, 'Y-m-d H:i:s');
    $contractParams['join_date'] = CRM_Utils_Date::processDate($submitted['join_date'], null, null, 'Y-m-d H:i:s');

    $contractParams['campaign_id'] = $submitted['campaign_id'];

    // 'Custom' fields
    $contractParams['membership_general.membership_reference'] = $submitted['membership_reference']; // Reference number
    $contractParams['membership_general.membership_contract']  = $submitted['membership_contract'];  // Contract number
    $contractParams['membership_general.membership_dialoger']  = $submitted['membership_dialoger'];  // DD fundraiser
    $contractParams['membership_general.membership_channel']   = $submitted['membership_channel'];   // Membership Channel

    $contractParams['note'] = $submitted['activity_details']; // Membership channel
    $contractParams['medium_id'] = $submitted['activity_medium']; // Membership channel

    $contract = civicrm_api3('Contract', 'create', $contractParams);
    $membership_url = CRM_Utils_System::url('civicrm/contact/view/membership', "action=view&cid={$contact['id']}&id={$contract['id']}");
    CRM_Core_Session::setStatus("New Membership <a href=\"{$membership_url}\" style=\"font-weight: bold;\">{$contract['id']}</a> created.", "Success", 'info');

    // Create T-shirt order

    if(isset($submitted['tshirt_order'])){
      $webshopCustomFields = array_column(civicrm_api3('CustomField', 'get', [ 'custom_group_id' => 'webshop_information'])['values'], 'id', 'name');
      $tshirtActivityParams['target_contact_id'] = $contact['id'];
      $tshirtActivityParams['status_id'] = 'Scheduled';
      $tshirtActivityParams['activity_type_id'] = civicrm_api3('OptionValue', 'getvalue', [
        'option_group_id' => 'activity_type',
        'name' => 'webshop order',
        'return' => 'value',
      ]);
      $tshirtActivityParams['custom_'.$webshopCustomFields['order_type']] = $submitted['shirt_design'];
      $tshirtActivityParams['custom_'.$webshopCustomFields['shirt_type']] = $submitted['shirt_type'];
      $tshirtActivityParams['custom_'.$webshopCustomFields['shirt_size']] = $submitted['shirt_size'];
      $tshirtActivityParams['custom_'.$webshopCustomFields['linked_membership']] = $contract['id'];

      // write subject line for tshirt order
      $shirtDesignLabel = civicrm_api3('OptionValue', 'getvalue', ['option_group_id' => 'order_type', 'value' => $submitted['shirt_design'], 'return' => 'label']);
      $shirtSizeLabel   = civicrm_api3('OptionValue', 'getvalue', ['option_group_id' => 'shirt_size', 'value' => $submitted['shirt_size'], 'return' => 'label']);
      $shirtTypeLabel   = civicrm_api3('OptionValue', 'getvalue', ['option_group_id' => 'shirt_type', 'value' => $submitted['shirt_type'], 'return' => 'label']);
      $tshirtActivityParams['subject'] = "order type {$shirtDesignLabel} AND t-shirt type {$shirtTypeLabel} AND t-shirt size {$shirtSizeLabel} AND number of items 1";

      $tshirtResult = civicrm_api3('Activity', 'create', $tshirtActivityParams);
    }

    if (array_key_exists('_qf_AT_submit_new', $submitted)) {
      $this->controller->_destination = CRM_Utils_System::url('civicrm/member/add', "reset=1&action=add&context=standalone");
    } else {
      $this->controller->_destination = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact['id']}");
    }

  }

}
