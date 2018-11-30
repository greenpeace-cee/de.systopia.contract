<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
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

}
