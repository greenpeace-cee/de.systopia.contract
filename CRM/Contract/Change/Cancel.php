<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use Civi\Api4;
use CRM_Contract_ExtensionUtil as E;

/**
 * "Cancel Membership" change
 */
class CRM_Contract_Change_Cancel extends CRM_Contract_Change {

  /**
   * Get a list of required fields for this type
   *
   * @return array list of required fields
   */
  public function getRequiredFields() {
    return [
        'membership_cancellation.membership_cancel_reason'
    ];
  }

  /**
   * Derive/populate additional data
   */
  public function populateData() {
    if ($this->isNew()) {
      $this->setParameter('contract_cancellation.contact_history_cancel_reason', $this->getParameter('membership_cancellation.membership_cancel_reason'));
      $this->setParameter('subject', $this->getSubject(NULL));
    } else {
      parent::populateData();
      $this->setParameter('membership_cancellation.membership_cancel_reason', $this->getParameter('contract_cancellation.contact_history_cancel_reason'));
    }
  }


  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  public function execute() {
    $contract = $this->getContract();

    // cancel the contract by setting the end date
    $contract_update = [
        'end_date'                => date('YmdHis'),
        'membership_cancellation.membership_cancel_reason' => $this->data['membership_cancellation.membership_cancel_reason'],
        'membership_cancellation.membership_cancel_date' => date('Y-m-d H:i:s'),
        'status_id'               => 'Cancelled',
    ];

    // perform the update
    $this->updateContract($contract_update);

    // also: cancel the payment/recurring contribution
    $recurring_contribution_id = $contract['membership_payment.membership_recurring_contribution'];

    if (isset($recurring_contribution_id)) {
      $cancel_reason = $this->data['membership_cancellation.membership_cancel_reason'];

      $payment_adapter_id = null;
      $payment_adapter = null;

      $payment_adapter_id = CRM_Contract_Utils::getPaymentAdapterForRecurringContribution(
        $recurring_contribution_id
      );

      if (isset($payment_adapter_id)) {
        $payment_adapter = CRM_Contract_Utils::getPaymentAdapterClass($payment_adapter_id);
      }

      if (isset($payment_adapter)) {
        $payment_adapter::terminate($recurring_contribution_id, $cancel_reason);
      } else {
        civicrm_api3("ContributionRecur", "create", [
          "id"                     => $recurring_contribution_id,
          "end_date"               => date("YmdHis"),
          "cancel_date"            => date("YmdHis"),
          "cancel_reason"          => $cancel_reason,
          "contribution_status_id" => 1,
        ]);
      }
      // end the contract payment link
      $contract_payment_link = [];
      try {
        $contract_payment_link = civicrm_api3('ContractPaymentLink', 'getsingle', [
          'contract_id'           => $contract['id'],
          'contribution_recur_id' => $recurring_contribution_id,
          'return'                => ['id'],
        ]);
      } catch (CiviCRM_API3_Exception $e) {
        Civi::log()->warning("Unable to determine ContractPaymentLink when cancelling contract {$contract['id']} with ContributionRecur {$recurring_contribution_id}: {$e->getMessage()}");
      }

      if (isset($contract_payment_link['id'])) {
        CRM_Contract_BAO_ContractPaymentLink::endPaymentLink($contract_payment_link['id']);
      }
    }

    // update change activity
    $contract_after = $this->getContract();
    $this->setParameter('subject', $this->getSubject($contract_after, $contract));
    $this->setParameter("activity_date_time", date('Y-m-d H:i:s'));
    $this->setStatus('Completed');
    $this->save();
  }

  /**
   * Check whether this change activity should actually be created
   *
   * CANCEL activities should not be created, if there is another one already there
   *
   * @throws Exception if the creation should be disallowed
   */
  public function shouldBeAccepted() {
    parent::shouldBeAccepted();

    // check for OTHER CANCELLATION REQUEST for the same day
    //  @see https://redmine.greenpeace.at/issues/1190
    $requested_day = date('Y-m-d', strtotime($this->data['activity_date_time']));
    $scheduled_activities = civicrm_api3('Activity', 'get', array(
        'source_record_id' => $this->getContractID(),
        'activity_type_id' => $this->getActivityTypeID(),
        'status_id'        => 'Scheduled',
        'option.limit'     => 0,
        'sequential'       => 1,
        'return'           => 'id,activity_date_time'));
    foreach ($scheduled_activities['values'] as $scheduled_activity) {
      $scheduled_for_day = date('Y-m-d', strtotime($scheduled_activity['activity_date_time']));
      if ($scheduled_for_day == $requested_day) {
        // there's already a scheduled 'cancel' activity for the same day
        throw new Exception("Scheduling an (additional) cancellation request in not desired in this context.");
      }
    }

    // IF CONTRACT ALREADY CANCELLED, create another cancel activity only
    //  when there are other scheduled (or 'needs review') changes
    //  @see https://redmine.greenpeace.at/issues/1190
    $contract = $this->getContract();

    $contract_cancelled_status = civicrm_api3('MembershipStatus', 'get', array(
        'name'   => 'Cancelled',
        'return' => 'id'));
    if ($contract['status_id'] == $contract_cancelled_status['id']) {
      // contract is cancelled
      $pending_activity_count = civicrm_api3('Activity', 'getcount', array(
          'source_record_id' => $this->getContractID(),
          'activity_type_id' => ['IN' => CRM_Contract_Change::getActivityTypeIds()],
          'status_id'        => ['IN' => ['Scheduled', 'Needs Review']],
      ));
      if ($pending_activity_count == 0) {
        throw new Exception("Scheduling an (additional) cancellation request in not desired in this context.");
      }
    }
  }

  /**
   * Render the default subject
   *
   * @param $contract_after array     not used
   * @param $contract_before array    not used
   * @return string                   the subject line
   */
  public function renderDefaultSubject($contract_after, $contract_before = NULL) {
    if ($this->isNew()) {
      return 'Cancel Contract';
    }

    $contract_id = $this->getContractID();
    $column_name = 'contract_cancellation.contact_history_cancel_reason';

    if (empty($this->data[$column_name])) {
      return "id$contract_id:";
    }

    $cancel_reason = $this->labelValue($this->data[$column_name], $column_name);

    return "id$contract_id: cancel reason $cancel_reason";
  }

  /**
   * Get a list of the status names that this change can be applied to
   *
   * @return array list of membership status names
   */
  public static function getStartStatusList() {
    return ['New', 'Grace', 'Current', 'Pending'];
  }

  /**
   * Get a (human readable) title of this change
   *
   * @return string title
   */
  public static function getChangeTitle() {
    return E::ts("Cancel Contract");
  }

  /**
   * Modify action links provided to the user for a given membership
   *
   * @param $links                array  currently given links
   * @param $current_status_name  string membership status as a string
   * @param $membership_data      array  all known information on the membership in question
   */
  public static function modifyMembershipActionLinks(&$links, $current_status_name, $membership_data) {
    if (in_array($current_status_name, self::getStartStatusList()) && self::hasEditPermission($membership_data['id'])) {
      $links[] = [
          'name'  => E::ts("Cancel"),
          'title' => self::getChangeTitle(),
          'url'   => "civicrm/contract/cancel",
          'bit'   => CRM_Core_Action::UPDATE,
          'qs'    => "id=%%id%%",
          'weight' => 10,
      ];
    }
  }

  public function save() {
    parent::save();

    $activity_id = $this->data['id'] ?? NULL;
    $cancel_tags = $this->data['membership_cancellation.cancel_tags'] ?? [];

    if (empty($activity_id) || empty($cancel_tags)) return;

    foreach ($cancel_tags as $tag) {
      Api4\EntityTag::create(FALSE)
        ->addValue('entity_table', 'civicrm_activity')
        ->addValue('entity_id', $activity_id)
        ->addValue('tag_id.name', $tag)
        ->execute();
    }
  }
}
