<?php
/*-------------------------------------------------------+
| SYSTOPIA LOGGING TOOLS EXTENSION                       |
| Copyright (C) 2020 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

use CRM_Loggingtools_ExtensionUtil as E;

/**
 * Provide Metadata for LoggingTruncator.truncate_table
 */
function _civicrm_api3_logging_truncator_truncate_table_spec(&$params)
{
    $params['table_names'] = [
        'name' => 'table_names',
        'api.required' => 1,
        'type' => CRM_Utils_Type::T_STRING,
        'title' => E::ts('Table name(s)'),
        'description' => E::ts('The table name(s) of the log table to be truncated. If more than one, separate with comma, or use "all" for all of them.'),
    ];
    $params['cutoff'] = [
        'name' => 'cutoff',
        'api.required' => 1,
        'type' => CRM_Utils_Type::T_TIMESTAMP,
        'title' => E::ts('Cut-Off'),
        'description' => E::ts('Timestamp string for the cut-off time. All log entries before that will be purged.'),
    ];
}


/**
 * Run the LoggingTruncator for the specified table(s)
 */
function civicrm_api3_logging_truncator_truncate_table($params)
{
    // extract table names
    $table_names = $params['table_names'];
    if (is_string($table_names)) {
        $table_names = explode(',', $table_names);
    }

    // sanitise and check input
    $table_names = array_map('trim', $table_names);
    foreach ($table_names as $table_name) {
        if (substr($table_name, 0, 4) != 'log_') {
            if ($table_name == 'all') {
                $table_names = array_keys(CRM_Loggingtools_Form_Truncation::getLoggingTables());
                break;
            } else {
                return civicrm_api3_create_error(E::ts("Table '%1' is not a log table.", [1 => $table_name]));
            }
        }
    }

    // check if logging is enabled
    $loggingControl = new CRM_Logging_Schema();
    $logging_enabled = $loggingControl->isEnabled();

    // disable logging
    if ($logging_enabled) {
        $loggingControl->disableLogging();
    }

    // truncate table(s)
    $truncator = new CRM_Loggingtools_Truncater();
    foreach ($table_names as $table_name) {
        Civi::log()->debug("Starting truncation of log table '{$table_name}'");
        $truncator->truncate($params['cutoff'], $table_name);
    }

    // re-enable logging
    if ($logging_enabled) {
        $loggingControl->enableLogging();
    }

    return civicrm_api3_create_success();
}
