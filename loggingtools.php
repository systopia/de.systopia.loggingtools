<?php

require_once 'loggingtools.civix.php';

use CRM_Loggingtools_ExtensionUtil as E;

/**
 * Exclude the configured tables from logging
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterLogTables/
 */
function loggingtools_civicrm_alterLogTables(&$logTableSpec)
{
    CRM_Loggingtools_LoggingTables::excludeLogTables($logTableSpec);
}

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function loggingtools_civicrm_config(&$config)
{
    _loggingtools_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function loggingtools_civicrm_install()
{
    _loggingtools_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function loggingtools_civicrm_enable()
{
    _loggingtools_civix_civicrm_enable();
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 *
 * function loggingtools_civicrm_preProcess($formName, &$form) {
 *
 * } // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 *
 * function loggingtools_civicrm_navigationMenu(&$menu) {
 * _loggingtools_civix_insert_navigation_menu($menu, 'Mailings', array(
 * 'label' => E::ts('New subliminal message'),
 * 'name' => 'mailing_subliminal_message',
 * 'url' => 'civicrm/mailing/subliminal',
 * 'permission' => 'access CiviMail',
 * 'operator' => 'OR',
 * 'separator' => 0,
 * ));
 * _loggingtools_civix_navigationMenu($menu);
 * } // */
