<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2018 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

require_once 'CRM/Core/DAO.php';
require_once 'CRM/Utils/Type.php';

class CRM_Contract_DAO_ContractPaymentLink extends CRM_Core_DAO
{
  /**
   * static instance to hold the table name
   *
   * @var string
   * @static
   */
  static $_tableName = 'civicrm_contract_payment';
  /**
   * static instance to hold the field values
   *
   * @var array
   * @static
   */
  static $_fields = null;
  /**
   * static instance to hold the keys used in $_fields for each field.
   *
   * @var array
   * @static
   */
  static $_fieldKeys = null;
  /**
   * static instance to hold the FK relationships
   *
   * @var string
   * @static
   */
  static $_links = null;
  /**
   * static instance to hold the values that can
   * be imported
   *
   * @var array
   * @static
   */
  static $_import = null;
  /**
   * static instance to hold the values that can
   * be exported
   *
   * @var array
   * @static
   */
  static $_export = null;
  /**
   * static value to see if we should log any modifications to
   * this table in the civicrm_log table
   *
   * @var boolean
   * @static
   */
  static $_log = true;
  /**
   * ID
   *
   * @var int unsigned
   */
  public $id;

  /**
   * contract id
   *
   * @var int unsigned
   */
  public $contract_id;

  /**
   * linked entity id
   *
   * @var int unsigned
   */
  public $contribution_recur_id;

  /**
   * is this link activ?
   *
   * @var int unsigned
   */
  public $is_active;

  /**
   * creation date
   * by default now()
   *
   * @var datetime
   */
  public $creation_date;

  /**
   * link start date (optional)
   *
   * @var datetime
   */
  public $start_date;

  /**
   * link end date (optional)
   *
   * @var datetime
   */
  public $end_date;


  function __construct()
  {
    $this->__table = 'civicrm_contract_payment';
    parent::__construct();
  }

  public static function getReferenceColumns() {
    if (!isset(Civi::$statics[__CLASS__]['links'])) {
      Civi::$statics[__CLASS__]['links'] = static::createReferenceColumns(__CLASS__);
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'contract_id', 'civicrm_membership', 'id');
      Civi::$statics[__CLASS__]['links'][] = new CRM_Core_Reference_Basic(self::getTableName(), 'contribution_recur_id', 'civicrm_contribution_recur', 'id');
      CRM_Core_DAO_AllCoreTables::invoke(__CLASS__, 'links_callback', Civi::$statics[__CLASS__]['links']);
    }
    return Civi::$statics[__CLASS__]['links'];
  }

  /**
   * returns all the column names of this table
   *
   * @access public
   * @return array
   */
  static function &fields()
  {
    if (!(self::$_fields)) {
      self::$_fields = array(
          'id' => [
              'name' => 'id',
              'type' => CRM_Utils_Type::T_INT,
              'description' => 'Unique ContractPaymentLink ID',
              'required' => TRUE,
              'table_name' => 'civicrm_contract_payment',
              'entity' => 'ContractPaymentLink',
              'bao' => 'CRM_Contract_DAO_ContractPaymentLink',
              'localizable' => 0,
          ],
          'contract_id' => [
              'name' => 'contract_id',
              'type' => CRM_Utils_Type::T_INT,
              'title' => ts('Contract ID'),
              'description' => 'FK to Membership ID',
              'table_name' => 'civicrm_contract_payment',
              'entity' => 'ContractPaymentLink',
              'bao' => 'CRM_Contract_DAO_ContractPaymentLink',
              'localizable' => 0,
          ],
          'contribution_recur_id' => [
              'name' => 'contribution_recur_id',
              'type' => CRM_Utils_Type::T_INT,
              'title' => ts('ContributionRecur ID'),
              'description' => 'FK to civicrm_contribution_recur',
              'required' => TRUE,
              'table_name' => 'civicrm_contract_payment',
              'entity' => 'ContractPaymentLink',
              'bao' => 'CRM_Contract_DAO_ContractPaymentLink',
              'localizable' => 0,
          ],
          'is_active' => [
              'name' => 'is_active',
              'type' => CRM_Utils_Type::T_BOOLEAN,
              'description' => 'Is this link still active?',
              'table_name' => 'civicrm_contract_payment',
              'entity' => 'ContractPaymentLink',
              'bao' => 'CRM_Contract_DAO_ContractPaymentLink',
              'localizable' => 0,
          ],
          'creation_date' => [
              'name' => 'creation_date',
              'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
              'title' => ts('Creation Date'),
              'description' => 'Link creation date',
              'table_name' => 'civicrm_contract_payment',
              'entity' => 'ContractPaymentLink',
              'bao' => 'CRM_Contract_DAO_ContractPaymentLink',
              'localizable' => 0,
          ],
          'start_date' => [
              'name' => 'start_date',
              'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
              'title' => ts('Start Date'),
              'description' => 'Start date of the link (optional)',
              'table_name' => 'civicrm_contract_payment',
              'entity' => 'ContractPaymentLink',
              'bao' => 'CRM_Contract_DAO_ContractPaymentLink',
              'localizable' => 0,
          ],
          'end_date' => [
              'name' => 'end_date',
              'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
              'title' => ts('End Date'),
              'description' => 'End date of the link (optional)',
              'table_name' => 'civicrm_contract_payment',
              'entity' => 'ContractPaymentLink',
              'bao' => 'CRM_Contract_DAO_ContractPaymentLink',
              'localizable' => 0,
          ],
      );
    }
    return self::$_fields;
  }


  /**
   * Returns an array containing, for each field, the arary key used for that
   * field in self::$_fields.
   *
   * @access public
   * @return array
   */
  static function &fieldKeys()
  {
    if (!(self::$_fieldKeys)) {
      self::$_fieldKeys = array(
          'id' => 'id',
          'contract_id' => 'contract_id',
          'contribution_recur_id' => 'contribution_recur_id',
          'is_active' => 'is_active',
          'creation_date' => 'creation_date',
          'start_date' => 'start_date',
          'end_date' => 'end_date'
      );
    }
    return self::$_fieldKeys;
  }
  /**
   * returns the names of this table
   *
   * @access public
   * @static
   * @return string
   */
  static function getTableName()
  {
    return self::$_tableName;
  }
  /**
   * returns if this table needs to be logged
   *
   * @access public
   * @return boolean
   */
  function getLog()
  {
    return self::$_log;
  }
  /**
   * returns the list of fields that can be imported
   *
   * @access public
   * return array
   * @static
   */
  static function &import($prefix = false)
  {
    if (!(self::$_import)) {
      self::$_import = array();
      $fields = self::fields();
      foreach($fields as $name => $field) {
        if (CRM_Utils_Array::value('import', $field)) {
          if ($prefix) {
            self::$_import['contract_payment'] = & $fields[$name];
          } else {
            self::$_import[$name] = & $fields[$name];
          }
        }
      }
    }
    return self::$_import;
  }
  /**
   * returns the list of fields that can be exported
   *
   * @access public
   * return array
   * @static
   */
  static function &export($prefix = false)
  {
    if (!(self::$_export)) {
      self::$_export = array();
      $fields = self::fields();
      foreach($fields as $name => $field) {
        if (CRM_Utils_Array::value('export', $field)) {
          if ($prefix) {
            self::$_export['contract_payment'] = & $fields[$name];
          } else {
            self::$_export[$name] = & $fields[$name];
          }
        }
      }
    }
    return self::$_export;
  }
}
