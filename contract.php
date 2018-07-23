<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

require_once 'contract.civix.php';
use CRM_Contract_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function contract_civicrm_config(&$config) {
  _contract_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function contract_civicrm_xmlMenu(&$files) {
  _contract_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function contract_civicrm_install() {
  _contract_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function contract_civicrm_postInstall() {
  _contract_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function contract_civicrm_uninstall() {
  _contract_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function contract_civicrm_enable() {
  require_once 'CRM/Contract/CustomData.php';
  $customData = new CRM_Contract_CustomData('de.systopia.contract');
  $customData->syncOptionGroup(__DIR__ . '/resources/option_group_contract_cancel_reason.json');
  $customData->syncOptionGroup(__DIR__ . '/resources/option_group_payment_frequency.json');
  $customData->syncOptionGroup(__DIR__ . '/resources/option_group_activity_types.json');
  $customData->syncOptionGroup(__DIR__ . '/resources/option_group_activity_status.json');
  $customData->syncOptionGroup(__DIR__ . '/resources/option_group_shirt_type.json');
  $customData->syncOptionGroup(__DIR__ . '/resources/option_group_shirt_size.json');
  $customData->syncCustomGroup(__DIR__ . '/resources/custom_group_contract_cancellation.json');
  $customData->syncCustomGroup(__DIR__ . '/resources/custom_group_contract_updates.json');
  $customData->syncCustomGroup(__DIR__ . '/resources/custom_group_membership_cancellation.json');
  $customData->syncCustomGroup(__DIR__ . '/resources/custom_group_membership_payment.json');
  $customData->syncEntities(__DIR__ . '/resources/entities_membership_status.json');

  _contract_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function contract_civicrm_disable() {
  _contract_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function contract_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _contract_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
// function contract_civicrm_managed(&$entities) {
//   _contract_civix_civicrm_managed($entities);
// }

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function contract_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _contract_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

function contract_civicrm_pageRun( &$page ){
  if($page->getVar('_name') == 'CRM_Member_Page_Tab'){
    $contractStatuses = array();
    foreach(civicrm_api3('Membership', 'get', ['contact_id' => $page->_contactId])['values'] as $contract){
      $contractStatuses[$contract['id']] = civicrm_api3('Contract', 'get_open_modification_counts', ['id' => $contract['id']]);
    }
    CRM_Core_Resources::singleton()->addStyleFile('de.systopia.contract', 'css/contract.css');
    CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array('contractStatuses' => $contractStatuses));
    CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array('cid' => $page->_contactId));
    CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'templates/CRM/Member/Page/Tab.js');
  }
}

//TODO - shorten this function call - move into an 1 or more alter functions
function contract_civicrm_buildForm($formName, &$form) {

  switch ($formName) {

    // Membership form in view mode
    case 'CRM_Member_Form_MembershipView':
      $contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $form);

      $formUtils = new CRM_Contract_FormUtils($form, 'Membership');
      $formUtils->setPaymentAmountCurrency();
      $formUtils->replaceIdWithLabel('membership_payment.membership_recurring_contribution', 'ContributionRecur');
      $formUtils->replaceIdWithLabel('membership_payment.payment_instrument', 'PaymentInstrument');
      $formUtils->replaceIdWithLabel('membership_payment.to_ba', 'BankAccountReference');
      $formUtils->replaceIdWithLabel('membership_payment.from_ba', 'BankAccountReference');

      // Add link for contract download
      $membershipId = CRM_Utils_Request::retrieve('id', 'Positive', $form);
      // removed: $formUtils->showPaymentContractDetails();
      $formUtils->addMembershipContractFileDownloadLink($membershipId);

      // GP-814 - hide 'edit' button if 'delete in CiviMember' is not granted
      if (!CRM_Core_Permission::check('delete in CiviMember')) {
        CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'js/membership_view_hide_edit.js');
      }

      break;

    // Membership form in add mode
    case 'CRM_Member_Form_Membership':

      $contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $form);
      $id = CRM_Utils_Request::retrieve('id', 'Positive', $form);

      if(in_array($form->getAction(), array(CRM_Core_Action::UPDATE, CRM_Core_Action::ADD))){
        // Use JS to hide form elements
        CRM_Core_Resources::singleton()->addScriptFile( 'de.systopia.contract', 'templates/CRM/Member/Form/Membership.js' );
        $filteredMembershipStatuses = civicrm_api3('MembershipStatus', 'get', ['name' => ['IN' => ['Current', 'Cancelled']]]);
        CRM_Core_Resources::singleton()->addVars( 'de.systopia.contract', ['filteredMembershipStatuses' => $filteredMembershipStatuses]);
        $hiddenCustomFields = civicrm_api3('CustomField', 'get', ['name' => ['IN' => ['membership_annual', 'membership_frequency']]]);
        CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array('hiddenCustomFields' => $hiddenCustomFields));

        if($form->getAction() == CRM_Core_Action::ADD){
          $form->setDefaults(array(
            'is_override' => true,
            'status_id' => civicrm_api3('MembershipStatus', 'getsingle', array('name' => "current"))['id']
          ));
        }

        $formUtils = new CRM_Contract_FormUtils($form, 'Membership');
        if(!isset($form->_groupTree)){
          // NOTE for initial launch: all core membership fields should be editable
          // $formUtils->removeMembershipEditDisallowedCoreFields();
          // NOTE for initial launch: allow editing of payment contracts via the standard form

        // Custom data version
        }else{
          $result = civicrm_api3('CustomField', 'GetSingle', array('custom_group_id' => 'membership_payment', 'name' => 'membership_recurring_contribution'));
          $customGroupTableId = isset($form->_groupTree[$result['custom_group_id']]['table_id']) ? $form->_groupTree[$result['custom_group_id']]['table_id'] : '-1';
          $elementName = "custom_{$result['id']}_{$customGroupTableId}";
          $form->removeElement($elementName);
          $formUtils->addPaymentContractSelect2($elementName, $contactId, true, $id);
          // NOTE for initial launch: all custom membership fields should be editable
          $formUtils->removeMembershipEditDisallowedCustomFields();
        }
      }

      if($form->getAction() === CRM_Core_Action::ADD){
        if($cid = CRM_Utils_Request::retrieve('cid', 'Integer')){
          CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contract/create', 'cid='.$cid, true));
        }else{
          $domain = strtolower(civicrm_api3('Setting', 'GetValue', [
            'name' => 'contract_domain',
            'group' => 'Contract preferences'
          ]));
          if (empty($domain)) {
            $default = civicrm_api3('Setting', 'getdefaults', [
              'name' => 'contract_domain',
              'group' => 'Contract preferences'
            ]);
            $domain = strtolower(reset($default['values'])['contract_domain']);
          }
          CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contract/rapidcreate/' . $domain, true));
        }
      }

      // workaround for GP-671
      if ($form->getAction() === CRM_Core_Action::UPDATE) {
        CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'js/membership_edit_protection.js' );
      }
      break;

    //Activity form in view mode
    case 'CRM_Activity_Form_Activity':
    case 'CRM_Fastactivity_Form_Add':
    case 'CRM_Fastactivity_Form_View':
      if($form->getAction() == CRM_Core_Action::VIEW){

        // Show recurring contribution details
        $id =  CRM_Utils_Request::retrieve('id', 'Positive', $form);
        $formUtils = new CRM_Contract_FormUtils($form, 'Activity');
        $formUtils->replaceIdWithLabel('contract_updates.ch_recurring_contribution', 'ContributionRecur');
        $formUtils->replaceIdWithLabel('contract_updates.ch_payment_instrument', 'PaymentInstrument');
        $formUtils->replaceIdWithLabel('contract_updates.ch_from_ba', 'BankAccountReference');
        $formUtils->replaceIdWithLabel('contract_updates.ch_to_ba', 'BankAccountReference');

        // Show membership label, not id
        $formUtils->showMembershipTypeLabel();

      }elseif($form->getAction() == CRM_Core_Action::UPDATE){
        CRM_Core_Resources::singleton()->addScriptFile( 'de.systopia.contract', 'templates/CRM/Activity/Form/Edit.js' );
      }
      break;

  }
}

function contract_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors){
  if($formName == 'CRM_Member_Form_Membership' && in_array($form->getAction(), array(CRM_Core_Action::UPDATE, CRM_Core_Action::ADD))){
    $wrapper = new CRM_Contract_Wrapper_MembershipEditForm();
    $wrapper->validate($form, CRM_Utils_Request::retrieve('id', 'Positive', $form), $fields);
    $errors = $wrapper->getErrors();
  }
}

function contract_civicrm_links( $op, $objectName, $objectId, &$links, &$mask, &$values ){
  if ($objectName == 'Membership') {
    // alter membership link
    $alter = new CRM_Contract_AlterMembershipLinks($objectId, $links, $mask, $values);
    $alter->removeActions(array(CRM_Core_Action::RENEW, CRM_Core_Action::DELETE, CRM_Core_Action::UPDATE));
    $alter->addHistoryActions();

  } elseif ($op=='contribution.selector.row') {
    // add a Contract link to contributions that are connected to memberships
    $contribution_id = (int) $objectId;
    if ($contribution_id) {
      // add 'view contract' link
      $membership_id = CRM_Core_DAO::singleValueQuery("SELECT membership_id FROM civicrm_membership_payment WHERE contribution_id = {$contribution_id} LIMIT 1");
      if ($membership_id) {
        $contact_id = CRM_Core_DAO::singleValueQuery("SELECT contact_id FROM civicrm_membership WHERE id = {$membership_id} LIMIT 1");
        if ($contact_id) {
          $links[] = array(
              'name'  => 'Contract',
              'title' => 'View Contract',
              'url'   => 'civicrm/contact/view/membership',
              'qs'    => "reset=1&id={$membership_id}&cid={$contact_id}&action=view");
        }
      }
    }
  }
}

function contract_civicrm_pre($op, $objectName, $id, &$params){

  // Using elseif would save *some* resources but make the code more brittle
  // since the comparisons are a little involved and may change over time.
  // Lets keep them independent by just using ifs

  // Wrap calls to the Membership BAO so we can reverse engineer modification
  // activities if necessary
  if($objectName == 'Membership' && in_array($op, array('create', 'edit'))){
    $wrapperMembership = CRM_Contract_Wrapper_Membership::one_shot_singleton();
    $wrapperMembership->pre($op, $id, $params);
  }

  // Wrap calles to the Activity BAO so we can execute contract modifications
  // and check for potential conflicts as appropriate

  if($objectName == 'Activity'){

    // TODO address weird CiviCRM where this hook is fired when deleting a
    // membership via the UI. It gets called with $op == 'delete', a null $id
    // and some odd params. This solves the issue for now.
    if($op == 'delete' && is_null($id)){
      return;
    }
    $wrapperModificationActivity = CRM_Contract_Wrapper_ModificationActivity::one_shot_singleton();
    $wrapperModificationActivity->pre($op, $params);
  }
}

function contract_civicrm_post($op, $objectName, $id, &$objectRef){

  // Wrap calls to the Membership BAO so we can reverse engineer modification
  // activities if necessary
  if($objectName == 'Membership' && in_array($op, array('create', 'edit'))){
    $wrapperMembership = CRM_Contract_Wrapper_Membership::one_shot_singleton();
    $wrapperMembership->post($id);
  }

  // Wrap calles to the Activity BAO to execute contract
  // modifications and check when appropriate
  if($objectName == 'Activity'){
    $wrapperModificationActivity = CRM_Contract_Wrapper_ModificationActivity::one_shot_singleton();
    $wrapperModificationActivity->post($id, $objectRef);
  }

  // Delete build in membership log activities
  if($objectName == 'Activity'){
    if($op == 'create' && in_array($objectRef->activity_type_id, CRM_Contract_Utils::getCoreMembershipHistoryActivityIds())){
      civicrm_api3('Activity', 'delete', array('id' => $id));
    }
  }
}

/**
 * Add config link
 */
function contract_civicrm_navigationMenu(&$menus){
  // Find the mailing menu
  foreach($menus as &$menu){
    if($menu['attributes']['name'] == 'Memberships'){
      $nextId = max(array_keys($menu['child']));
      $menu['child'][$nextId]=[
        'attributes' => array(
          'label'      => 'Contract settings',
          'name'       => 'Contract settings',
          'url'        => 'civicrm/admin/contract',
          'permission' => 'access CiviMember',
          'navID'      => $nextId,
          'operator'   => FALSE,
          'separator'  => TRUE,
          'parentID'   => $menu['attributes']['navID'],
          'active'     => 1
        ),
      ];
    }
  }
}

/**
  * Implements hook_civicrm_apiWrappers
  */
function contract_civicrm_apiWrappers(&$wrappers, $apiRequest) {
  // add contract reference validation for Memberships
  if ($apiRequest['entity'] == 'Membership') {
    $wrappers[] = new CRM_Contract_Validation_ContractNumber();
  }
}

/**
 * Add an "Assign to Campaign" for contact / membership search results
 *
 * @param string $objectType specifies the component
 * @param array $tasks the list of actions
 *
 * @access public
 */
function contract_civicrm_searchTasks($objectType, &$tasks) {
  if ($objectType == 'contribution') {
    if (CRM_Core_Permission::check('edit memberships')) {
      $tasks[] = array(
          'title' => E::ts('Assign to Contract'),
          'class' => 'CRM_Contract_Form_Task_AssignContributions',
          'result' => false);
    }
  }
}
