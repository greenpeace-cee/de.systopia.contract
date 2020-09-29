<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

/**
 * "Pause Membership" change
 */
class CRM_Contract_Change_Pause extends CRM_Contract_Change {

  /**
   * Get a list of required fields for this type
   *
   * @return array list of required fields
   */
  public function getRequiredFields() {
    return [
        'resume_date'
    ];
  }

  /**
   * Derive/populate additional data
   */
  public function populateData() {
    $contract = $this->getContract();
    $this->setParameter('subject', $this->getSubject($contract));

    $resume_date = $this->getParameter('resume_date');
    if (!$resume_date) {
      $resume_date = $this->getParameter('activity_date_time', date('Y-m-d'));
      $this->setParameter('resume_date', date('Y-m-d', strtotime("{$resume_date} + 1 day")));
    }

    if (!$this->isNew()) {
      parent::populateData();
    }
  }

  /**
   * In this case, don't only just save the pause, but also the resume!
   */
  public function save() {
    if ($this->isNew()) {
      // create resume change activity:
      $resume_date = $this->getParameter('resume_date');
      if ($resume_date) {
        $contract = $this->getContract();
        $resume_change = CRM_Contract_Change::getChangeForData(['activity_type_id' => 'Contract_Resumed']);
        $resume_change->setParameter('activity_date_time', $resume_date);
        $resume_change->setParameter('source_record_id', $this->getContractID());
        $resume_change->setParameter('source_contact_id', $this->getParameter('source_contact_id'));
        $resume_change->setParameter('target_contact_id', $contract['contact_id']);
        $resume_change->setParameter('subject', $resume_change->getSubject($contract));
        $resume_change->setStatus('Scheduled');
        $resume_change->save();
      }
    }
    parent::save();
  }

  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  public function execute() {
    $contract = $this->getContract(TRUE);

    // pause the mandate
    $payment_contract_id = CRM_Utils_Array::value('membership_payment.membership_recurring_contribution', $contract);
    if ($payment_contract_id) {
      CRM_Contract_SepaLogic::pauseSepaMandate($payment_contract_id);
      $this->updateContract(['status_id' => 'Paused']);
    }

    // update change activity
    $contract_after = $this->getContract(TRUE);
    $this->setParameter('subject', $this->getSubject($contract_after, $contract));
    $this->setParameter("activity_date_time", date('Y-m-d H:i:s'));
    $this->setStatus('Completed');
    $this->save();
  }

  /**
   * Make sure that the data for this change is valid
   *
   * @throws Exception if the data is not valid
   */
  public function verifyData() {
    parent::verifyData();

    // check that the resume date is not on the same day as the pause
    $pause_date  = date('Y-m-d', strtotime($this->getParameter('activity_date_time')));
    $resume_date = date('Y-m-d', strtotime($this->getParameter('resume_date')));
    if ($pause_date >= $resume_date) {
      throw new Exception(E::ts("Resume date cannot be before or on the same day as the pause."));
    }
  }

  /**
   * Check whether this change activity should actually be created
   *
   * CANCEL activities should not be created, if there is another one already there
   *
   * @throws Exception if the creation should be disallowed
   */
  public function shouldBeAccepted() {
    // TODO: any restrictions?
    parent::shouldBeAccepted();
  }

  /**
   * Render the default subject
   *
   * @param $contract_after       array  data of the contract after
   * @param $contract_before      array  data of the contract before
   * @return                      string the subject line
   */
  public function renderDefaultSubject($contract_after, $contract_before = NULL) {
    $contract_id = $this->getContractID();
    if ($this->isNew()) {
      return "id{$contract_id}: resume scheduled " . date('d/m/Y', strtotime($this->getParameter('resume_date')));
    } else {
      $subject = "id{$contract_id}:";
      if (!empty($this->data['contract_cancellation.contact_history_cancel_reason'])) {
        // FIXME: replicating weird behaviour by old engine
        $subject .= ' cancel reason ' . $this->resolveValue($this->data['contract_cancellation.contact_history_cancel_reason'], 'contract_cancellation.contact_history_cancel_reason');
        //$subject .= ' cancel reason ' . $this->labelValue($this->data['contract_cancellation.contact_history_cancel_reason'], 'contract_cancellation.contact_history_cancel_reason');
      }
      return $subject;
    }
  }

  /**
   * Get a list of the status names that this change can be applied to
   *
   * @return array list of membership status names
   */
  public static function getStartStatusList() {
    return ['New', 'Grace', 'Current'];
  }

  /**
   * Get a (human readable) title of this change
   *
   * @return string title
   */
  public static function getChangeTitle() {
    return E::ts("Pause Contract");
  }

  /**
   * Modify action links provided to the user for a given membership
   *
   * @param $links                array  currently given links
   * @param $current_status_name  string membership status as a string
   * @param $membership_data      array  all known information on the membership in question
   */
  public static function modifyMembershipActionLinks(&$links, $current_status_name, $membership_data) {
    if (in_array($current_status_name, self::getStartStatusList())) {
      $links[] = [
          'name'  => E::ts("Pause"),
          'title' => self::getChangeTitle(),
          'url'   => "civicrm/contract/modify",
          'bit'   => CRM_Core_Action::UPDATE,
          'qs'    => "modify_action=pause&id=%%id%%",
      ];
    }
  }
}
