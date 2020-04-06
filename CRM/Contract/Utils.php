<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

class CRM_Contract_Utils
{

  private static $_singleton;
  private static $coreMembershipHistoryActivityIds;
  static $customFieldCache;

  public static function singleton()
  {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Utils();
    }
    return self::$_singleton;
  }

  static $ContractToModificationActivityField = [
      'id'                                                   => 'source_record_id',
      'contact_id'                                           => 'target_contact_id',
      'campaign_id'                                          => 'campaign_id',
      'membership_type_id'                                   => 'contract_updates.ch_membership_type',
      'membership_payment.membership_recurring_contribution' => 'contract_updates.ch_recurring_contribution',
      'membership_payment.payment_instrument'                => 'contract_updates.ch_payment_instrument',
      'membership_payment.membership_annual'                 => 'contract_updates.ch_annual',
      'membership_payment.membership_frequency'              => 'contract_updates.ch_frequency',
      'membership_payment.from_ba'                           => 'contract_updates.ch_from_ba',
      'membership_payment.to_ba'                             => 'contract_updates.ch_to_ba',
      'membership_payment.cycle_day'                         => 'contract_updates.ch_cycle_day',
      'membership_payment.defer_payment_start'               => 'contract_updates.ch_defer_payment_start',
      'membership_cancellation.membership_cancel_reason'     => 'contract_cancellation.contact_history_cancel_reason',
  ];

  /**
   * Get the name (not the label) of the given membership status ID
   *
   * @param $status_id integer status ID
   * @return string status name
   */
  public static function getMembershipStatusName($status_id) {
    static $status_names = NULL;
    if ($status_names === NULL) {
      $status_names = [];
      $status_query = civicrm_api3('MembershipStatus', 'get', [
          'return'       => 'id,name',
          'option.limit' => 0,
      ]);
      foreach ($status_query['values'] as $status) {
        $status_names[$status['id']] = $status['name'];
      }
    }
    return CRM_Utils_Array::value($status_id, $status_names);
  }

  static function contractToActivityFieldId($contractField)
  {
    $translation   = self::$ContractToModificationActivityField;
    $activityField = $translation[$contractField];
    if (strpos($activityField, '.')) {
      return self::getCustomFieldId($activityField);
    }
    return $activityField;
  }


  static function getCustomFieldId($customField)
  {

    self::warmCustomFieldCache();

    // Look up if not in cache
    if (!isset(self::$customFieldCache[$customField])) {
      $parts = explode('.', $customField);
      try {
        self::$customFieldCache[$customField] = 'custom_' . civicrm_api3('CustomField', 'getvalue', ['return' => "id", 'custom_group_id' => $parts[0], 'name' => $parts[1]]);
      } catch (Exception $e) {
        throw new Exception("Could not find custom field '{$parts[1]}' in custom field set '{$parts[0]}'");
      }
    }

    // Return result or return an error if it does not exist.
    if (isset(self::$customFieldCache[$customField])) {
      return self::$customFieldCache[$customField];
    } else {
      throw new Exception('Could not find custom field id for ' . $customField);
    }
  }

  static function getCustomFieldName($customFieldId)
  {

    self::warmCustomFieldCache();
    $name = array_search($customFieldId, self::$customFieldCache);
    if (!$name) {
      $customField                                                             = civicrm_api3('CustomField', 'getsingle', ['id' => substr($customFieldId, 7)]);
      $customGroup                                                             = civicrm_api3('CustomGroup', 'getsingle', ['id' => $customField['custom_group_id']]);
      self::$customFieldCache["{$customGroup['name']}.{$customField['name']}"] = $customFieldId;
    }
    // Return result or return an error if it does not exist.
    if ($name = array_search($customFieldId, self::$customFieldCache)) {
      return $name;
    } else {
      throw new Exception('Could not find custom field for id' . $customFieldId);
    }
  }

  static function warmCustomFieldCache()
  {
    if (!self::$customFieldCache) {
      $customGroupNames = ['membership_general', 'membership_payment', 'membership_cancellation', 'contract_cancellation', 'contract_updates'];
      $customGroups     = civicrm_api3('CustomGroup', 'get', ['name' => ['IN' => $customGroupNames], 'return' => 'name', 'options' => ['limit' => 1000]])['values'];
      $customFields     = civicrm_api3('CustomField', 'get', ['custom_group_id' => ['IN' => $customGroupNames], 'options' => ['limit' => 1000]]);
      foreach ($customFields['values'] as $c) {
        self::$customFieldCache["{$customGroups[$c['custom_group_id']]['name']}.{$c['name']}"] = "custom_{$c['id']}";
      }
    }
  }

  static function getCoreMembershipHistoryActivityIds()
  {
    if (!self::$coreMembershipHistoryActivityIds) {
      $result = civicrm_api3('OptionValue', 'get', [
              'option_group_id' => 'activity_type',
              'name'            => ['IN' => ['Membership Signup', 'Membership Renewal', 'Change Membership Status', 'Change Membership Type', 'Membership Renewal Reminder']]]
      );
      foreach ($result['values'] as $v) {
        self::$coreMembershipHistoryActivityIds[] = $v['value'];
      }
    }
    return self::$coreMembershipHistoryActivityIds;
  }

  /**
   * Download contract file
   * @param $file
   *
   * @return bool
   */
  static function downloadContractFile($file)
  {
    if (!CRM_Contract_Utils::contractFileExists($file)) {
      return false;
    }
    $fullPath = CRM_Contract_Utils::contractFilePath($file);

    ignore_user_abort(true);
    set_time_limit(0); // disable the time limit for this script

    if ($fd = fopen($fullPath, "r")) {
      $fsize      = filesize($fullPath);
      $path_parts = pathinfo($fullPath);
      $ext        = strtolower($path_parts["extension"]);
      header("Content-type: application/octet-stream");
      header("Content-Disposition: filename=\"" . $path_parts["basename"] . "\"");
      header("Content-length: $fsize");
      header("Cache-control: private"); //use this to open files directly
      while (!feof($fd)) {
        $buffer = fread($fd, 2048);
        echo $buffer;
      }
    }
    fclose($fd);
    exit;
  }

  /**
   * Check if contract file exists, return false if not
   * @param $logFile
   * @return boolean
   */
  static function contractFileExists($file)
  {
    $fullPath = CRM_Contract_Utils::contractFilePath($file);
    if ($fullPath) {
      if (file_exists($fullPath)) {
        return $fullPath;
      }
    }
    return false;
  }

  /**
   * Simple function to get real file name from contract number
   * @param $file
   *
   * @return string
   */
  static function contractFileName($file)
  {
    return $file . '.tif';
  }

  /**
   * This is hardcoded so contract files must be stored in customFileUploadDir/contracts/
   * Extension hardcoded to .tif
   * FIXME: This could be improved to use a setting to configure this.
   *
   * @param $file
   *
   * @return bool|string
   */
  static function contractFilePath($file)
  {
    // We need a valid filename
    if (empty($file)) {
      return FALSE;
    }

    // Use the custom file upload dir as it's protected by a Deny from All in htaccess
    $config = CRM_Core_Config::singleton();
    if (!empty($config->customFileUploadDir)) {
      $fullPath = $config->customFileUploadDir . '/contracts/';
      if (!is_dir($fullPath)) {
        CRM_Core_Error::debug_log_message('Warning: Contract file path does not exist.  It should be at: ' . $fullPath);
      }
      $fullPathWithFilename = $fullPath . self::contractFileName($file);
      return $fullPathWithFilename;
    } else {
      CRM_Core_Error::debug_log_message('Warning: Contract file path undefined! Did you set customFileUploadDir?');
      return FALSE;
    }
  }

  /**
   * If configured this way, this call will delete the defined
   *  list of system-generated activities
   *
   * @param $contract_id int the contract number
   */
  public static function deleteSystemActivities($contract_id) {
    if (empty($contract_id)) return;

    $activity_types_to_delete = CRM_Contract_Configuration::suppressSystemActivityTypes();
    if (!empty($activity_types_to_delete)) {
      // find them
      $activity_search = civicrm_api3('Activity', 'get', [
          'source_record_id'   => $contract_id,
          'activity_type_id'   => ['IN' => $activity_types_to_delete],
          'activity_date_time' => ['>=' => date('Ymd') . '000000'],
          'return'             => 'id',
      ]);

      // delete them
      foreach ($activity_search['values'] as $activity) {
        civicrm_api3('Activity', 'delete', ['id' => $activity['id']]);
      }
    }
  }

  public static function formatExceptionForActivityDetails(Exception $e) {
    return "Error was: {$e->getMessage()}<br><pre>{$e->getTraceAsString()}</pre>";
  }

  public static function formatExceptionForApi(Exception $e) {
    return $e->getMessage() . "\r\n" . $e->getTraceAsString();
  }


  /**
   * Strip all custom_* elements from $data unless they're contract activity fields
   *
   * This serves as a workaround for an APIv3 issue where a call to Activity.get
   * with the "return" parameter set to any custom field will return all other
   * custom fields that have a default value set, even if the custom field is
   * not enabled for the relevant (contract) activity type
   *
   * @todo remove this code once APIv4 is used
   *
   * @param array $data
   */
  public static function stripNonContractActivityCustomFields(array &$data) {
    // whitelist of contract activity custom fields
    $allowedFields = array_map(
      function($field) {
        return $field['id'];
      },
      CRM_Contract_CustomData::getCustomFieldsForGroups(['contract_cancellation','contract_updates'])
    );
    foreach ($data as $field => $value) {
      if (substr($field, 0, 7) === 'custom_') {
        $customFieldId = substr($field, 7);
        if (!in_array($customFieldId, $allowedFields)) {
          // field starts with custom_ and ID is not on whitelist => remove
          unset($data[$field]);
        }
      }
    }
  }

}
