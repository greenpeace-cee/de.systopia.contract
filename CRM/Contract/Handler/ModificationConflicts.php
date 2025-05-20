<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use Civi\Api4;

class CRM_Contract_Handler_ModificationConflicts{

  public static function checkForConflicts($membership_id){
    if (empty($membership_id)) {
      throw new Exception('Missing contract ID, cannot check for conflicts');
    }

    $contract_status = Api4\Membership::get(FALSE)
      ->addSelect('status_id:label')
      ->addWhere('id', '=', $membership_id)
      ->execute()
      ->first()['status_id:label'];

    $scheduled_activities = (array) Api4\Activity::get(FALSE)
      ->addSelect('activity_type_id:name', 'status_id:name')
      ->addWhere('source_record_id', '=', $membership_id)
      ->addWhere('activity_type_id:name', 'IN', array_keys(CRM_Contract_Change::$type2class))
      ->addWhere('status_id:name', 'IN', ['Scheduled', 'Needs Review'])
      ->addOrderBy('activity_date_time', 'ASC')
      ->setLimit(10000)
      ->execute();

    $scheduled_changes = array_map(fn ($activity) => str_replace('Contract_', '', $activity['activity_type_id:name']), $scheduled_activities);

    if (count($scheduled_changes) < 2) return;

    if ($scheduled_changes === ['Paused', 'Resumed']) return;

    if ($scheduled_changes === ['Paused', 'Resumed', 'Updated']) return;

    if ($scheduled_changes === ['Resumed', 'Updated'] && $contract_status === 'Paused') return;

    foreach($scheduled_activities as $activity){
      if ($activity['status_id:name'] === 'Needs Review') continue;

      $reviewers_setting = Api4\Setting::get(FALSE)
        ->addSelect('contract_modification_reviewers')
        ->execute()
        ->first()['value'];

      $reviewers = empty($reviewers_setting)
        ? []
        : array_map('intval', explode(',', $reviewers_setting));

      Api4\Activity::update(FALSE)
        ->addValue('assignee_contact_id', $reviewers)
        ->addValue('status_id:name', 'Needs Review')
        ->addWhere('id', '=', $activity['id'])
        ->execute();
    }
  }
}
