<?php

use Civi\Api4;

class CRM_Contract_Form_EditMembership extends CRM_Core_Form {

  public function preProcess () {
    $membership_id = CRM_Utils_Request::retrieve('membership_id', 'Integer');

    if (empty($membership_id)) throw new CRM_Core_Exception('Missing membership ID');

    $membership = Api4\Membership::get(FALSE)
      ->addSelect(
        'join_date',
        'membership_general.contract_file',
        'membership_general.membership_channel',
        'membership_general.membership_contract',
        'membership_general.membership_dialoger',
        'membership_general.membership_reference',
        'membership_referral.membership_referrer',
        'start_date',
        'status_id',
      )
      ->addWhere('id', '=', $membership_id)
      ->execute()
      ->first();

    $this->set('membership', $membership);
    $this->set('membership_channels', CRM_Contract_FormUtils::getOptionValueLabels('contact_channel'));

    $contract_file = Api4\File::get(FALSE)
      ->addWhere('id', '=', $membership['membership_general.contract_file'])
      ->addSelect('id', 'file_name', 'url')
      ->execute()
      ->first();

    $this->assign('contract_file', $contract_file);
  }

  public function buildQuickForm () {
    // Reference number (membership_reference)
    $this->add('text', 'membership_reference', ts('Reference number'));

    // Contract number (membership_contract)
    $this->add('text', 'membership_contract', ts('Contract number'));

    // DD-Fundraiser (membership_dialoger)
    $this->addEntityRef(
      'membership_dialoger',
      ts('DD-Fundraiser'),
      [
        'entity' => 'Contact',
        'api' => [
          'params' => [
            'contact_type'     => 'Individual',
            'contact_sub_type' => 'Dialoger',
          ],
        ],
      ]
    );

    // Membership channel (membership_channel)
    $this->add(
      'select',
      'membership_channel',
      ts('Membership channel'),
      [ '' => '- none -' ] + $this->get('membership_channels'),
      FALSE,
      [ 'class' => 'crm-select2' ]
    );

    // Referrer (membership_referrer)
    $this->addEntityRef(
      'membership_referrer',
      ts('Referrer'),
      [ 'entity' => 'Contact' ]
    );

    // Contract file (contract_file)
    $this->add('file', 'contract_file', ts('Contract file'));

    // Member since (join_date)
    $this->add('datepicker', 'join_date', ts('Member since'), [], TRUE, [ 'time' => FALSE ]);

    // Membership start date (start_date)
    $this->add('datepicker', 'start_date', ts('Membership start date'), [], TRUE, [ 'time' => FALSE ]);

    // Form buttons
    $this->addButtons([
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
        'submitOnce' => TRUE,
      ],
      [
        'type' => 'submit',
        'name' => ts('Confirm'),
        'isDefault' => TRUE,
        'submitOnce' => TRUE,
      ],
    ]);

    $this->setDefaults();
  }

  public function setDefaults($default_values = NULL, $filter = NULL) {
    $membership = $this->get('membership');

    parent::setDefaults([
      'join_date'            => $membership['join_date'],
      'membership_channel'   => $membership['membership_general.membership_channel'],
      'membership_contract'  => $membership['membership_general.membership_contract'],
      'membership_dialoger'  => $membership['membership_general.membership_dialoger'],
      'membership_reference' => $membership['membership_general.membership_reference'],
      'membership_referrer'  => $membership['membership_referral.membership_referrer'],
      'start_date'           => $membership['start_date'],
    ]);
  }

  public function validate () {
    $membership = $this->get('membership');
    $submitted = $this->exportValues();

    $contract_nr_error = CRM_Contract_Validation_ContractNumber::verifyContractNumber(
      $submitted['membership_contract'],
      $membership['id']
    );

    if (!empty($contract_nr_error)) {
      HTML_QuickForm::setElementError('membership_contract', $contract_nr_error);
    }

    return parent::validate();
  }

  public function postProcess () {
    $membership = $this->get('membership');
    $submitted = $this->exportValues();

    $membership_update = Api4\Membership::update(FALSE)
      ->addWhere('id', '=', $membership['id'])
      ->addValue('join_date',                               $submitted['join_date']           )
      ->addValue('membership_general.membership_channel',   $submitted['membership_channel']  )
      ->addValue('membership_general.membership_contract',  $submitted['membership_contract'] )
      ->addValue('membership_general.membership_dialoger',  $submitted['membership_dialoger'] )
      ->addValue('membership_general.membership_reference', $submitted['membership_reference'])
      ->addValue('membership_referral.membership_referrer', $submitted['membership_referrer'] )
      ->addValue('start_date',                              $submitted['start_date']          )
      ->addValue('status_id',                               $membership['status_id']          );

    $contract_file_metadata = $this->getElement('contract_file')->getValue();

    if (!empty($contract_file_metadata)) {
      $uri = CRM_Utils_File::makeFileName($contract_file_metadata['name']);
      $path = CRM_Core_Config::singleton()->customFileUploadDir . DIRECTORY_SEPARATOR . $uri;
      rename($contract_file_metadata['tmp_name'], $path);

      $contract_file = Api4\File::create(FALSE)
        ->addValue('mime_type', $contract_file_metadata['type'])
        ->addValue('uri',       $uri                           )
        ->execute()
        ->first();

      $membership_update->addValue('membership_general.contract_file', $contract_file['id']);
    }

    $membership_update->execute();
  }

}
