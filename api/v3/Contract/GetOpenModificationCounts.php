<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Get the number of scheduled modifications for a contract
 */
function _civicrm_api3_Contract_get_open_modification_counts_spec(&$params) {
  $params['id'] = array(
    'name'         => 'id',
    'title'        => 'Contract ID',
    'api.required' => 1,
    'description'  => 'Contract (Membership) ID of the contract to be modified',
    );
}

/**
 * Get the number of scheduled modifications for a contract
 */
function civicrm_api3_Contract_get_open_modification_counts($params) {
  $activitiesForReview = civicrm_api3('Activity', 'getcount', [
    'source_record_id' => $params['id'],
    'status_id' => 'Needs Review'
  ]);
  $activitiesScheduled = civicrm_api3('Activity', 'getcount', [
    'source_record_id' => $params['id'],
    'status_id' => ['IN' => ['Scheduled']]
  ]);
  $activitiesFailed = civicrm_api3('Activity', 'getcount', [
    'source_record_id' => $params['id'],
    'status_id' => ['IN' => ['Failed']]
  ]);
  return civicrm_api3_create_success([
    'needs_review' => $activitiesForReview,
    'scheduled' => $activitiesScheduled,
    'failed' => $activitiesFailed,
  ]);
}
