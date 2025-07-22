<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
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
  Civi::dispatcher()->addListener('hook_civicrm_post', 'CRM_Contract_RecurringContribution::amendContribution');

  Civi::dispatcher()->addListener(
    'civi.recur.nextschedcontributiondatealter',
    ['CRM_Contract_PaymentAdapter_Adyen', 'updateNextScheduledContributionDate']
  );
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
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function contract_civicrm_enable() {
  _contract_civix_civicrm_enable();
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
 * UI Adjustements for membership forms
 */
function contract_civicrm_pageRun( &$page ){
  $page_name = $page->getVar('_name');
  if ($page_name == 'CRM_Contribute_Page_ContributionRecur') {
    // this is a contribution view
    CRM_Contract_BAO_ContractPaymentLink::injectLinks($page);

  } elseif($page_name == 'CRM_Member_Page_Tab'){
    // thus is the membership summary tab
    $contractStatuses = array();
    foreach(civicrm_api3('Membership', 'get', ['contact_id' => $page->_contactId])['values'] as $contract){
      $contractStatuses[$contract['id']] = civicrm_api3('Contract', 'get_open_modification_counts', ['id' => $contract['id']])['values'];
    }
    CRM_Core_Resources::singleton()->addStyleFile('de.systopia.contract', 'css/contract.css');
    CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array('contractStatuses' => $contractStatuses));
    CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array('cid' => $page->_contactId));
    CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'templates/CRM/Member/Page/Tab.js');
  }
}

/**
 * UI Adjustements for membership forms
 *
 * @todo shorten this function call - move into an 1 or more alter functions
 */
function contract_civicrm_buildForm($formName, &$form) {
  CRM_Core_Resources::singleton()->addMarkup(
    '<script type="importmap">' . json_encode(CRM_Contract_FormUtils::generateJsImportMap()) . '</script>'
  );

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

      // GP-814 - hide core 'edit' button
      CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'js/membership_view_hide_edit.js');

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
          // TODO: is this dead code?
          $formUtils->addPaymentContractSelect2($elementName, $contactId, TRUE, $id);
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

      }elseif($form->getAction() == CRM_Core_Action::UPDATE){
        CRM_Core_Resources::singleton()->addScriptFile( 'de.systopia.contract', 'templates/CRM/Activity/Form/Edit.js' );
      }
      break;

  }
}

/**
 * Custom validation for membership forms
 */
function contract_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if($formName == 'CRM_Member_Form_Membership' && in_array($form->getAction(), array(CRM_Core_Action::UPDATE, CRM_Core_Action::ADD))){
    CRM_Contract_Handler_MembershipForm::validateForm($formName, $fields, $files, $form, $errors);
  }
}

/**
 * Custom links for memberships
 */
function contract_civicrm_links( $op, $objectName, $objectId, &$links, &$mask, &$values ){
  if ($objectName == 'Membership') {
    if ($objectId) {
      // load membership
      $membership_data = civicrm_api3('Membership', 'getsingle', ['id' => $objectId]);

      // alter links
      CRM_Contract_Change::modifyActionLinks($membership_data, $links);
    }

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

/**
 * CiviCRM PRE hook: Monitoring of relevant entity changes
 */
function _contract_civicrm_pre($op, $objectName, $id, &$params) {
  // FIXME: Monitoring currently not implemented in the new engine
}

/**
 * CiviCRM POST hook: Monitoring of relevant entity changes
 */
function _contract_civicrm_post($op, $objectName, $id, &$objectRef){
  // FIXME: Monitoring currently not implemented in the new engine
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
    $wrappers[] = new CRM_Contract_Handler_MembershipAPI();
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
      $tasks[] = array(
          'title' => E::ts('Detach from Contract'),
          'class' => 'CRM_Contract_Form_Task_DetachContributions',
          'result' => false);
    }
  }
}

/**
 * Add CiviContract permissions
 *
 * @param $permissions
 */
function contract_civicrm_permission(&$permissions) {
  $permissions['edit core membership CiviContract'] = [
    'label' => ts('CiviContract: Edit core membership', [ 'domain' => 'de.systopia.contract' ]),
    'description' => ts('Allow editing memberships using the core membership form', [ 'domain' => 'de.systopia.contract' ]),
  ];

  $permissions['edit adyen contracts'] = [
    'label' => ts('CiviContract: edit Adyen contracts', [ 'domain' => 'de.systopia.contract' ]),
    'description' => ts('Allow editing of Adyen contracts', [ 'domain' => 'de.systopia.contract' ]),
    'implied_by' => ['administer CiviCRM'],
  ];

  $permissions['edit eft contracts'] = [
    'label' => ts('CiviContract: Edit EFT contracts', [ 'domain' => 'de.systopia.contract' ]),
    'description' => ts('Allow editing of EFT contracts', [ 'domain' => 'de.systopia.contract' ]),
    'implied_by' => ['administer CiviCRM'],
  ];

  $permissions['edit sepa_mandate contracts'] = [
    'label' => ts('CiviContract: Edit SEPA contracts', [ 'domain' => 'de.systopia.contract' ]),
    'description' => ts('Allow editing of SEPA contracts', [ 'domain' => 'de.systopia.contract' ]),
    'implied_by' => ['administer CiviCRM'],
  ];
}

function contract_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions) {
  if (strtolower($entity) == 'contract' && strtolower($action) == 'start_date') {
    $permissions[$entity][$action] = ['access CiviMember'];
  }
}

/**
 * Entity Types Hook
 * @param $entityTypes
 */
function contract_civicrm_entityTypes(&$entityTypes) {
  // add my DAO's
  $entityTypes[] = array(
      'name' => 'ContractPaymentLink',
      'class' => 'CRM_Contract_DAO_ContractPaymentLink',
      'table' => 'civicrm_contract_payment',
  );
}
