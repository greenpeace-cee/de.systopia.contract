<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Page_Review extends CRM_Core_Page {

  public function run() {
    if(!$id = CRM_Utils_Request::retrieve('id', 'Positive')){
      Throw new Exception('Missing a valid contract ID');
    }

    // get contract currency from currently active recurring contribution
    // TODO: make currency changeable/store it with the contract update
    $membership = civicrm_api3('Membership', 'getsingle', [
      'id' => CRM_Utils_Request::retrieve('id', 'Positive')
    ]);

    $rc_id = $membership[CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution')];

    $this->assign('currency', civicrm_api3('ContributionRecur', 'getvalue', [
      'id' => $rc_id,
      'return' => 'currency',
    ]));

    // Set activity params
    $activityParams = [
      'source_record_id' => $id,
      'activity_type_id' => ['IN' => CRM_Contract_Change::getActivityTypeIds()],
      'is_deleted' => 0,
      'return' => [
        'activity_date_time',
        'status_id',
        'activity_type_id',
        'target_contact_id',
        'source_contact_id',
        'details',
        'campaign_id',
        'medium_id'
      ],
      'option.sort'        => 'activity_date_time DESC, id DESC',

    ];
    foreach(civicrm_api3('CustomField', 'get', [ 'custom_group_id' => ['IN' => ['contract_cancellation', 'contract_updates']]])['values'] as $customField){
      $activityParams['return'][]='custom_'.$customField['id'];
    }
    $activities = civicrm_api3('Activity', 'get', $activityParams)['values'];


    // Friendlify custom field names
    CRM_Contract_Utils::warmCustomFieldCache();
    $customFieldIndex = array_flip(CRM_Contract_Utils::$customFieldCache);
    $customFieldIndex = str_replace('.', '_', $customFieldIndex);

    // To collect the campaign ids that we need to get the names of
    $campaigns=[];
    $contacts=[];



    foreach($activities as $key => $activity){

      foreach($activity as $innerKey => $field){
        if(isset($customFieldIndex[$innerKey])){
          unset($activities[$key][$innerKey]);
          $activities[$key][$customFieldIndex[$innerKey]] = $field;
        }
      }
      if(isset($activities[$key]['contract_updates_ch_recurring_contribution']) && $activities[$key]['contract_updates_ch_recurring_contribution']){
        $rc = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $activities[$key]['contract_updates_ch_recurring_contribution']]);
        $activities[$key]['payment_instrument_id'] = $rc['payment_instrument_id'];
        $activities[$key]['recurring_contribution_contact_id'] = $rc['contact_id'];
      }
      if(isset($activities[$key]['contract_updates_ch_annual']) && isset($activities[$key]['contract_updates_ch_frequency']) && $activities[$key]['contract_updates_ch_annual'] && $activities[$key]['contract_updates_ch_frequency']){
        $activities[$key]['contract_updates_ch_amount'] = CRM_Contract_Utils::formatMoney($activities[$key]['contract_updates_ch_annual']) / $activities[$key]['contract_updates_ch_frequency'];
        $activities[$key]['contract_updates_ch_amount'] = CRM_Contract_Utils::formatMoney($activities[$key]['contract_updates_ch_amount']);
      }
      if(isset($activities[$key]['campaign_id'])){
        $campaigns[] = $activities[$key]['campaign_id'];
      }
      if(isset($activities[$key]['contract_cancellation_contact_history_cancel_reason'])){
        $cancelReasons[] = $activities[$key]['contract_cancellation_contact_history_cancel_reason'];
      }
      if(isset($activities[$key]['source_contact_id'])){
        $contacts[] = $activities[$key]['source_contact_id'];
      }
    }

    $this->assign('activities', $activities);

    // Get campaigns
    if($campaigns){
      foreach(civicrm_api3('Campaign', 'get', ['id' => ['IN' => array_unique($campaigns)]])['values'] as $campaign){
        $campaigns[$campaign['id']] = $campaign['title'];
      }
    }
    $this->assign('campaigns', $campaigns);
    if($cancelReasons){
      foreach(civicrm_api3('OptionValue', 'get', ['option_group_id' => "contract_cancel_reason", 'value' => ['IN' => array_unique($cancelReasons)]])['values'] as $campaign){
        $cancelReasons[$campaign['value']] = $campaign['label'];
      }
    }
    $this->assign('cancelReasons', $cancelReasons);


    foreach(civicrm_api3('Contact', 'get', ['id' => ['IN' => array_unique($contacts)]])['values'] as $contact){
      $contacts[$contact['id']] = $contact['display_name'];
    }
    $this->assign('contacts', $contacts);

    foreach(civicrm_api3('OptionValue', 'get', [ 'option_group_id' => 'encounter_medium', 'return' => ['value', 'label'] ])['values'] as $medium){
      $mediums[$medium['value']] = $medium['label'];
    }
    $this->assign('mediums', $mediums);

    foreach(civicrm_api3('OptionValue', 'get', [ 'option_group_id' => 'payment_instrument', 'return' => ['value', 'label'] ])['values'] as $paymentInstrument){
      $paymentInstruments[$paymentInstrument['value']] = $paymentInstrument['label'];
    }
    $this->assign('paymentInstruments', $paymentInstruments);


    // Get activity statuses
    foreach(civicrm_api3('OptionValue', 'get', [ 'option_group_id' => 'activity_status', 'return' => ['value', 'label'] ])['values'] as $activityStatus){
      $activityStatuses[$activityStatus['value']] = $activityStatus['label'];
    }
    $this->assign('activityStatuses', $activityStatuses);

    foreach(civicrm_api3('OptionValue', 'get', [ 'option_group_id' => 'payment_frequency', 'return' => ['value', 'label'] ])['values'] as $paymentFrequency){
      $paymentFrequencies[$paymentFrequency['value']] = $paymentFrequency['label'];
    }
    $this->assign('paymentFrequencies', $paymentFrequencies);

    // Get activity types
    $this->assign('activityTypes', CRM_Contract_Change::getChangeTypes());
    $this->assign('includeWysiwygEditor', true);

    // Get membership types
    foreach(civicrm_api3('MembershipType', 'get', [])['values'] as $membershipType){
      $membershipTypes[$membershipType['id']] = $membershipType['name'];
    }
    $this->assign('membershipTypes', $membershipTypes);

    $this->assign('membershipTypes', $membershipTypes);

    // since Civi 4.7, wysiwyg/ckeditor is a default core resource
    if (version_compare(CRM_Utils_System::version(), '4.7', '<')) {
      CRM_Core_Resources::singleton()->addScriptFile('civicrm', 'packages/ckeditor/ckeditor.js');
    }
    foreach(civicrm_api3('CustomField', 'get', [ 'custom_group_id' => ['IN' => ['contract_cancellation', 'contract_updates']]])['values'] as $customField){
      $activityParams['return'][]='custom_'.$customField['id'];
    }

    $adapter = CRM_Contract_Utils::getPaymentAdapterForRecurringContribution($rc_id);
    $this->assign('has_edit_permission', CRM_Core_Permission::check("edit $adapter contracts"));
    $this->assign('is_admin', CRM_Core_Permission::check('administer CiviContract'));

    parent::run();
  }

}
