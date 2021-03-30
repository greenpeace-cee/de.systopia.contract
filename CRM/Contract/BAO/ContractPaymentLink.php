<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2018 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

class CRM_Contract_BAO_ContractPaymentLink extends CRM_Contract_DAO_ContractPaymentLink {

  /**
   * Create a new payment link
   *
   * @param int $contract_id            the contract to link
   * @param int $contribution_recur_id  the ID of the entity to link to
   * @param bool $is_active             is the link active? default is YES
   * @param string $start_date          start date of the link, default NOW
   * @param string $end_date            end date of the link, default is NONE
   *
   * @return object CRM_Contract_BAO_ContractPaymentLink resulting object
   * @throws Exception if mandatory fields aren't set
   */
  public static function createPaymentLink($contract_id, $contribution_recur_id, $is_active = TRUE, $start_date = 'now', $end_date = NULL) {
    $params = array(
        'contract_id'           => $contract_id,
        'contribution_recur_id' => $contribution_recur_id,
        '$is_active'            => $is_active ? 1 : 0,
    );

    // set dates
    if ($start_date) {
      $params['start_date'] = date('YmdHis', strtotime($start_date));
    }
    if ($end_date) {
      $params['end_date'] = date('YmdHis', strtotime($end_date));
    }

    return self::add($params);
  }

  /**
   * Get all active payment links with the given parameters,
   *  i.e. link fulfills the following criteria
   *        is_active = 1
   *        start_date NULL or in the past
   *
   *
   * @param int $contract_id            the contract to link
   * @param int $contribution_recur_id  ID of the linked entity
   * @param string $date                what timestamp does the "active" refer to? Default is: now
   *
   * @todo: add limit
   *
   * @return array of link data
   * @throws Exception if contract_id is invalid
   */
  public static function getActiveLinks($contract_id = NULL, $contribution_recur_id = NULL, $date = 'now') {
    // build where clause
    $WHERE_CLAUSES = array();

    // process date
    $now = date('YmdHis', strtotime($date));
    $WHERE_CLAUSES[] = "is_active >= 1";
    $WHERE_CLAUSES[] = "start_date IS NULL OR start_date <= '{$now}'";
    $WHERE_CLAUSES[] = "end_date   IS NULL OR end_date   >  '{$now}'";

    // process contract_id
    if (!empty($contract_id)) {
      $contract_id = (int) $contract_id;
      $WHERE_CLAUSES[] = "contract_id = {$contract_id}";
    }

    // process entity restrictions
    if (!empty($contribution_recur_id)) {
      $contribution_recur_id = (int) $contribution_recur_id;
      $WHERE_CLAUSES[] = "contribution_recur_id = {$contribution_recur_id}";
    }

    // build and run query
    $WHERE_CLAUSE = '(' . implode(') AND (', $WHERE_CLAUSES) . ')';
    $query_sql = "SELECT * FROM civicrm_contract_payment WHERE {$WHERE_CLAUSE}";
    $query = CRM_Core_DAO::executeQuery($query_sql);
    $results = array();
    while ($query->fetch()) {
      $results[] = $query->toArray();
    }
    return $results;
  }

  /**
   * End a payment link
   *
   * @param int $link_id               ID of the link
   * @param string $date               at what timestamp should the link be ended - default is "now"
   *
   * @return object CRM_Contract_BAO_ContractPaymentLink resulting object
   * @throws Exception if mandatory fields aren't set
   */
  public static function endPaymentLink($link_id, $date = 'now') {
    $link_id = (int) $link_id;
    if ($link_id) {
      $link = new CRM_Contract_BAO_ContractPaymentLink();
      $link->id = $link_id;
      $link->is_active = 0;
      $link->end_date = date('YmdHis', strtotime($date));
      $link->save();
    }
  }

  /**
   * Create/edit a ContractPaymentLink entry
   *
   * @param array  $params (reference ) an assoc array of name/value pairs
   * @return object CRM_Contract_BAO_ContractPaymentLink object on success, null otherwise
   * @access public
   * @static
   * @throws Exception if mandatory parameters not set
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    if ($hook == 'create') {
      // check mandatory fields
      if (empty($params['contract_id'])) {
        throw new Exception("Field contract_id is mandatory.");
      }
      if (empty($params['contribution_recur_id'])) {
        throw new Exception("Field contribution_recur_id is mandatory.");
      }

      // set create date
      $params['creation_date'] = date('YmdHis');
    }

    CRM_Utils_Hook::pre($hook, 'ContractPaymentLink', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Contract_BAO_ContractPaymentLink();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'ContractPaymentLink', $dao->id, $dao);
    return $dao;
  }

  /**
   * Inject some information on the potential payment links of this recurring contribution
   *
   * @param $page
   */
  public static function injectLinks(&$page) {
    $contribution_recur = $page->getTemplate()->get_template_vars("recur");
    if (!empty($contribution_recur['id'])) {
      // gather some data
      $contribution_recur_id = (int) $contribution_recur['id'];
      $all_links = civicrm_api3('ContractPaymentLink', 'get', array(
          'contribution_recur_id' => $contribution_recur_id,
          'sequential'            => 1,
          'option.limit'          => 0))['values'];

      // render all links
      $active_links = array();
      $inactive_links = array();
      foreach ($all_links as $link) {
        $rendered_link = self::renderLink($link);
        if ($rendered_link['active']) {
          $active_links[] = $rendered_link;
        } else {
          $inactive_links[] = $rendered_link;
        }
      }

      if (!empty($active_links) || !empty($inactive_links)) {
        $page->assign('contract_payment_links_active',   $active_links);
        $page->assign('contract_payment_links_inactive', $inactive_links);

        CRM_Core_Region::instance('page-body')->add(array(
            'template' => 'CRM/Contribute/Page/ContributionRecur/ContractPaymentLink.tpl'
        ));
      }
    }
  }

  /**
   * Render a textual description of the link data
   *
   * @param array $link_data
   * @return array description, containing [text, link, active]
   */
  public static function renderLink($link_data) {
    try {
      // load contract
      $contract = civicrm_api3('Membership', 'getsingle', array(
          'id'     => $link_data['contract_id'],
          'return' => 'contact_id,membership_type_id,id'));

      // load membership type
      $membership_type = civicrm_api3('MembershipType', 'getvalue', array(
          'return' => 'name',
          'id'     => $contract['membership_type_id']));

      // render date
      if (empty($link_data['start_date'])) {
        $start_date = E::ts("unknown");
      } else {
        $start_date = date('Y-m-d', strtotime($link_data['start_date']));
      }
      if (empty($link_data['end_date'])) {
        $end_date = E::ts("unknown");
      } else {
        $end_date = date('Y-m-d', strtotime($link_data['end_date']));
      }

      // check if active
      $now = strtotime('now');
      $active =    (!empty($link_data['is_active']))
                && (empty($link_data['start_date']) || strtotime($link_data['start_date']) <= $now)
                && (empty($link_data['end_date'])   || strtotime($link_data['end_date'])   > $now);

      if ($active) {
        return array(
            'text'   => E::ts("%2 [%1] since %3", array(1 => $contract['id'], 2 => $membership_type, 3 => $start_date)),
            'link'   => CRM_Utils_System::url('civicrm/contact/view/membership', "action=view&reset=1&cid={$contract['contact_id']}&id={$contract['id']}"),
            'active' => $active);
      } else {
        return array(
            'text'   => E::ts("%2 [%1] from %3 to %4", array(1 => $contract['id'], 2 => $membership_type, 3 => $start_date, 4 => $end_date)),
            'link'   => CRM_Utils_System::url('civicrm/contact/view/membership', "action=view&reset=1&cid={$contract['contact_id']}&id={$contract['id']}"),
            'active' => $active);
      }

    } catch (Exception $ex) {
      return array(
          'text'   => 'RENDER ERROR',
          'link'   => $ex->getMessage(),
          'active' => 1);
    }

  }

  /**
   * Link the given contribution to the contract,
   * ending any previously existing links
   *
   * @param $contract_id            int     contract (membership) ID
   * @param $contribution_recur_id  int     paymenyt ID
   * @param $date                   string  timestamp of change, default: 'now'
   */
  public static function setContractPaymentLink($contract_id, $contribution_recur_id, $date = 'now') {
    if (empty($contract_id) || empty($contribution_recur_id)) {
      // nothing to link here
      return;
    }

    try {
      // first: check if link already there
      $current_links = self::getActiveLinks($contract_id);
      foreach ($current_links as $current_link) {
        if ($current_link['contribution_recur_id'] == $contribution_recur_id) {
          // link already there and active
          return;
        }
      }

      // then: end any old links
      foreach ($current_links as $current_link) {
        self::endPaymentLink($current_link['id'], $date);
      }

      // then: create a new link
      self::createPaymentLink(
          $contract_id,
          $contribution_recur_id,
          TRUE,
          $date
      );

    } catch(Exception $ex) {
      // link couldn't be generated
      CRM_Core_Error::debug_log_message("Contract: Couldn't create payment link: " . $ex->getMessage());
    }
  }

}
