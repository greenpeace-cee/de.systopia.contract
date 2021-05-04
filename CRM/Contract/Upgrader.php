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
 * Collection of upgrade steps.
 */
class CRM_Contract_Upgrader extends CRM_Contract_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).


  public function install() {
    $this->executeSqlFile('sql/contract.sql');
  }

  public function enable() {
    require_once 'CRM/Contract/CustomData.php';
    $customData = new CRM_Contract_CustomData('de.systopia.contract');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/option_group_contact_channel.json');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/option_group_contract_cancel_reason.json');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/option_group_contract_cancel_reason.json');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/option_group_payment_frequency.json');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/option_group_activity_types.json');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/option_group_activity_status.json');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/option_group_shirt_type.json');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/option_group_shirt_size.json');
    $customData->syncCustomGroup(__DIR__ . '/../../resources/custom_group_contract_cancellation.json');
    $customData->syncCustomGroup(__DIR__ . '/../../resources/custom_group_contract_updates.json');
    $customData->syncCustomGroup(__DIR__ . '/../../resources/custom_group_membership_cancellation.json');
    $customData->syncCustomGroup(__DIR__ . '/../../resources/custom_group_membership_payment.json');
    $customData->syncCustomGroup(__DIR__ . '/../../resources/custom_group_membership_general.json');
    $customData->syncEntities(__DIR__ . '/../../resources/entities_membership_status.json');

    // create sub-type 'Dialoger'
    $dialoger_exists = civicrm_api3('ContactType', 'getcount', ['name' => 'Dialoger']);
    if (!$dialoger_exists) {
      civicrm_api3('ContactType', 'create', [
          'name'      => 'Dialoger',
          'parent_id' => 'Individual',
      ]);
    }
  }

  public function postInstall() {
  }

  public function uninstall() {
  }

  /**
   * Add custom field "defer_payment_start"
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1360() {
    $this->ctx->log->info('Applying update 1360');
    $customData = new CRM_Contract_CustomData('de.systopia.contract');
    $customData->syncCustomGroup(__DIR__ . '/../../resources/custom_group_contract_updates.json');
    $customData->syncCustomGroup(__DIR__ . '/../../resources/custom_group_membership_payment.json');
    return TRUE;
  }

  public function upgrade_1370() {
    $this->ctx->log->info('Applying update 1370');
    $this->executeSqlFile('sql/contract.sql');
    return TRUE;
  }

  public function upgrade_1390() {
    $this->ctx->log->info('Applying update 1390');
    $logging = new CRM_Logging_Schema();
    $logging->fixSchemaDifferences();
    return TRUE;
  }

  public function upgrade_1402() {
    $this->ctx->log->info('Applying updates for 14xx');
    $customData = new CRM_Contract_CustomData('de.systopia.contract');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/option_group_contact_channel.json');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/option_group_order_type.json');
    $customData->syncCustomGroup(__DIR__ . '/../../resources/custom_group_membership_general.json');
    return TRUE;
  }

  public function upgrade_1403() {
    $this->ctx->log->info('Applying update 1403');
    $customData = new CRM_Contract_CustomData('de.systopia.contract');
    $customData->syncCustomGroup(__DIR__ . '/../../resources/custom_group_contract_updates.json');
    return TRUE;
  }
}
