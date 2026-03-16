<?php

use Civi\Api4;

class CRM_Contract_Form_ActivityAttachments extends CRM_Core_Form {

  public function preProcess() {
    $activity_id = CRM_Utils_Request::retrieve('activity_id', 'Integer');

    if (empty($activity_id)) throw new CRM_Core_Exception('Missing activity ID');

    $this->set('activity_id', $activity_id);

    $activity = Api4\Activity::get(FALSE)
      ->addSelect(
        'id',
        'GROUP_CONCAT(file.id) AS file_ids',
        'GROUP_CONCAT(file.file_name) AS file_names',
        'GROUP_CONCAT(file.url) AS file_urls'
      )
      ->addJoin(
        'EntityFile AS entity_file',
        'LEFT',
        ['entity_file.entity_id', '=', $activity_id],
        ['entity_file.entity_table', '=', "'civicrm_activity'"]
      )
      ->addJoin(
        'File AS file',
        'LEFT',
        ['file.id', '=', 'entity_file.file_id']
      )
      ->addWhere('id', '=', $activity_id)
      ->addGroupBy('id')
      ->execute()
      ->first();

    $files = [];

    if (is_array($activity['file_ids'])) {
      foreach ($activity['file_ids'] as $i => $file_id) {
        $files[] = [
          'id'   => $file_id,
          'name' => $activity['file_names'][$i],
          'url'  => $activity['file_urls'][$i],
        ];
      }
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
