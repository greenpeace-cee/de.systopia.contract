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

class CRM_Contract_Utils
{

  private static $_singleton;
  private static $coreMembershipHistoryActivityIds;
  static $customFieldCache;

  /**
   * Is default sepa creditor uses bic?
   * Uses like a cache
   *
   * @var boolean
   * @static
   */
  private static $isDefaultCreditorUsesBic = NULL;

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

  /**
   * Is sepa creditor uses bic?
   *
   * @param $creditorId
   * @return bool
   */
  public static function isCreditorUsesBic($creditorId) {
    if (empty($creditorId)) {
      return FALSE;
    }

    try {
      $sepaCreditors = civicrm_api3('SepaCreditor', 'get', [
        'sequential' => 1,
        'return' => ["uses_bic"],
        'creditor_id' => $creditorId,
      ]);
    } catch (CiviCRM_API3_Exception $e) {
      return FALSE;
    }

    if (!empty($sepaCreditors['values']) && !empty($sepaCreditors['values'][0])
      && isset($sepaCreditors['values'][0]['uses_bic']) && $sepaCreditors['values'][0]['uses_bic'] == 1) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Is default sepa creditor uses bic?
   * Uses cache
   *
   * @return bool
   */
  public static function isDefaultCreditorUsesBic() {
    if (self::$isDefaultCreditorUsesBic == NULL) {
      $defaultCreditorId = (int) CRM_Sepa_Logic_Settings::getSetting('batching_default_creditor');
      self::$isDefaultCreditorUsesBic = self::isCreditorUsesBic($defaultCreditorId);
    }

    return self::$isDefaultCreditorUsesBic;
  }

  /**
   * formats a value to the CiviCRM failsafe format: 0.00 (e.g. 999999.90)
   * even if there are ',' in there, which are used in some countries
   * (e.g. Germany, Austria,) as a decimal point.
   */
  public static function formatMoney($raw_value) {
    // strip whitespaces
    $stripped_value = preg_replace('#\s#', '', $raw_value);

    // find out if there's a problem with ','
    if (strpos($stripped_value, ',') !== FALSE) {
      // if there are at least three digits after the ','
      //  it's a thousands separator
      if (preg_match('#,\d{3}#', $stripped_value)) {
        // it's a thousands separator -> just strip
        $stripped_value = preg_replace('#,#', '', $stripped_value);
      } else {
        // it has to be interpreted as a decimal
        // first remove all other decimals
        $stripped_value = preg_replace('#[.]#', '', $stripped_value);
        // then replace with decimal
        $stripped_value = preg_replace('#,#', '.', $stripped_value);
      }
    }

    // finally format properly
    $clean_value = number_format($stripped_value, 2, '.', '');
    return $clean_value;
  }

  /**
   * Get a default schedule date for contract creation/updates respecting the
   * configured `contract_minimum_change_date`
   *
   * @param string $preferred_date
   *
   * @return string
   */
  public static function getDefaultContractChangeDate ($preferred_date = "now") {
    $preferred_timestamp = strtotime($preferred_date);

    $min_change_date = Civi::settings()->get("contract_minimum_change_date");
    $min_change_timestamp = isset($min_change_date) ? strtotime($min_change_date) : 0;

    $actual_timestamp = max($preferred_timestamp, $min_change_timestamp);

    return date("Y-m-d H:i:s", $actual_timestamp);
  }

  public static function getPaymentAdapterClass ($adapter_id) {
    if ($adapter_id === null) return null;

    return CRM_Contract_Configuration::$paymentAdapters[$adapter_id];
  }

  public static function getPaymentAdapterForRecurringContribution ($recurring_contribution_id) {
    foreach (CRM_Contract_Configuration::$paymentAdapters as $paID => $paClass) {
      if ($paClass::isInstance($recurring_contribution_id)) return $paID;
    }

    CRM_Core_Error::debug_log_message(
      "No matching payment adapter found for recurring contribution with ID $recurring_contribution_id"
    );

    return 'eft';
  }

  public static function calcAnnualAmount(float $amount, int $frequency_interval, string $frequency_unit) {
    return [
      "annual"    => $frequency_unit === "year" ? $amount : (12 / $frequency_interval) * $amount,
      "frequency" => $frequency_unit === "year" ? 1 : 12 / $frequency_interval,
    ];
  }

  public static function calcRecurringAmount(float $annual, int $frequency) {
    return [
      "amount"             => $annual / $frequency,
      "frequency_interval" => $frequency === 1 ? 1 : 12 / $frequency,
      "frequency_unit"     => $frequency === 1 ? "year" : "month",
    ];
  }

  /**
   * Calculate payment changes for a given legacy SEPA contract update activity
   *
   * @param array $activity
   *
   * @return array
   * @throws \Exception
   */
  public static function getPaymentChangesForLegacyUpdate(array $activity) {
    CRM_Contract_CustomData::labelCustomFields($activity);
    $requiredFields = [
      'contract_updates.ch_cycle_day',
      'contract_updates.ch_from_ba',
      'contract_updates.ch_annual',
      'contract_updates.ch_frequency',
    ];
    foreach ($requiredFields as $requiredField) {
      if (empty($activity[$requiredField])) {
        throw new Exception("{$requiredField} must not be empty");
      }
    }
    return [
      'activity_type_id' => $activity['activity_type_id'],
      'adapter' => CRM_Contract_PaymentAdapter_SEPAMandate::ADAPTER_ID,
      'parameters' => [
        'campaign_id' => $activity['campaign_id'] ?? "",
        'cycle_day' => $activity['contract_updates.ch_cycle_day'],
        'from_ba' => $activity['contract_updates.ch_from_ba'],
        'annual' => $activity['contract_updates.ch_annual'],
        'frequency' => $activity['contract_updates.ch_frequency'],
        'defer_payment_start' => $activity['contract_updates.defer_payment_start'] ?? 0,
      ],
    ];
  }

  public static function getFinancialTypeID(string $name) {
    return Api4\FinancialType::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', $name)
      ->execute()
      ->first()['id'];
  }

  public static function getOptionValue(string $optionGroup, string $name) {
    return Api4\OptionValue::get(FALSE)
      ->addSelect('value')
      ->addWhere('option_group_id:name', '=', $optionGroup)
      ->addWhere('name', '=', $name)
      ->execute()
      ->first()['value'];
  }

  public static function resolvePaymentAdapterAlias($adapter) {
    if (empty($adapter)) return NULL;

    switch ($adapter) {
      case 'adyen':
        return 'adyen';

      case 'eft':
        return 'eft';

      case 'psp':
      case 'psp_sepa':
        return 'psp_sepa';

      case 'sepa':
      case 'sepa_mandate':
        return 'sepa_mandate';

      default:
        return NULL;
    }
  }

  public static function getMembershipByID(int $membership_id) {
    return Api4\Membership::get(FALSE)
      ->addWhere('id', '=', $membership_id)
      ->addSelect('*')
      ->setLimit(1)
      ->execute()
      ->first();
  }


}
