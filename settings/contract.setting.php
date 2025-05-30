<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.6                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2015                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2015
 * $Id$
 *
 */
/*
 * Settings metadata file
 */
return array(
  'contract_modification_reviewers' => array(
    'group_name' => 'Contract preferences',
    'group' => 'Contract preferences',
    'name' => 'contract_modification_reviewers',
    'type' => 'string',
    'default' => '',
    'add' => '1.0',
    'title' => 'Contract modification reviewer(s)',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Contacts that will be notified of updates that need review.',
    'help_text' => NULL,
  ),
  'contract_domain' => array(
    'group_name' => 'Contract preferences',
    'group' => 'Contract preferences',
    'name' => 'contract_domain',
    'type' => 'string',
    'default' => 'AT',
    'add' => '1.0',
    'title' => 'Contract Domain',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Some features like the Rapid Create form might work or
                      look different depending on the selected value.',
    'help_text' => NULL,
  ),
  'contract_minimum_change_date' => array(
    'group_name' => 'Contract preferences',
    'name' => 'contract_minimum_change_date',
    'type' => 'string',
    'default' => NULL,
    'add' => '1.0',
    'title' => 'Minimum Change Date',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'Contract changes cannot be scheduled up until this date. '
                     . 'Already scheduled modifications before this date will '
                     . 'be marked with "Needs Review"',
    'help_text' => NULL,
  ),
  'contract_payment_adapters' => [
    'group_name' => 'Contract preferences',
    'name' => 'contract_payment_adapters',
    'title' => 'Contract Payment Adapters',
    'type' => 'Array',
    'default' => [
      'adyen'        => 'CRM_Contract_PaymentAdapter_Adyen',
      'eft'          => 'CRM_Contract_PaymentAdapter_EFT',
      'sepa_mandate' => 'CRM_Contract_PaymentAdapter_SEPAMandate',
    ],
    'add' => '1.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'List of enabled payment adapter classes',
  ],
);
