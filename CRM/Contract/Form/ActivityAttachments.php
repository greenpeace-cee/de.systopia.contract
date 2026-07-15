<?php

use Civi\Api4;

class CRM_Contract_Form_ActivityAttachments extends CRM_Core_Form {

  public function preProcess() {
    $activity_id = CRM_Utils_Request::retrieve('activity_id', 'Integer');

    if (empty($activity_id)) throw new CRM_Core_Exception('Missing activity ID');

    $this->set('activity_id', $activity_id);
    $files = [];

    foreach (CRM_Core_BAO_File::getEntityFile('civicrm_activity', $activity_id) as $file) {
      $files[] = [
        'id'   => $file['fileID'],
        'name' => $file['cleanName'],
        'url' => $file['url']
      ];
    }

    $this->assign('files', $files);
  }

  public function buildQuickForm() {
    $this->add('file', 'attachment', ts('Add attachment'));

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
  }

  public function postProcess() {
    $attachment_metadata = $this->getElement('attachment')->getValue();

    if (!empty($attachment_metadata)) {
      $attachment_file = CRM_Contract_FormUtils::createFileFromUpload($attachment_metadata);

      Api4\EntityFile::create(FALSE)
        ->addValue('entity_table', 'civicrm_activity')
        ->addValue('entity_id', $this->get('activity_id'))
        ->addValue('file_id', $attachment_file['id'])
        ->execute();
    }
  }
}
