<?php
class CRM_Contract_Form_Settings extends CRM_Core_Form{
  function buildQuickForm(){
    $this->addEntityRef('contract_modification_reviewers', 'Contract modification reviewers', ['multiple' => 'multiple']);
    $this->add('select', 'contract_domain', 'Domain', ['AT' => 'Austria', 'PL' => 'Poland'], TRUE);
    $this->addButtons([
      array('type' => 'cancel', 'name' => 'Cancel'),
      array('type' => 'submit', 'name' => 'Save')
    ]);
    $this->setDefaults();
  }

  function setDefaults($defaultValues = null, $filter = null){
    $defaults = [
      'contract_modification_reviewers' => civicrm_api3('Setting', 'GetValue', [
        'name' => 'contract_modification_reviewers',
        'group' => 'Contract preferences'
      ]),
      'contract_domain' => civicrm_api3('Setting', 'GetValue', [
        'name' => 'contract_domain',
        'group' => 'Contract preferences'
      ]),
    ];
    parent::setDefaults($defaults);
  }



  function postProcess(){
    $submitted = $this->exportValues();
    civicrm_api3('Setting', 'Create', ['contract_modification_reviewers' => $submitted['contract_modification_reviewers']]);
    civicrm_api3('Setting', 'Create', ['contract_domain' => $submitted['contract_domain']]);
    CRM_Core_Session::setStatus('Contract settings updated.', null, 'success');

  }
}
