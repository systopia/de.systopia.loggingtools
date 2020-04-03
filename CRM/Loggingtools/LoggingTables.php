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
 * Tools revolving around the logging tables
 */
class CRM_Loggingtools_LoggingTables
{
    /**
     * Update the list of tables excluded from logging,
     *  and update the schema
     *
     * @param array $table_list
     *      list of table names to be excluded from logging
     */
    public static function setExcludedTables($table_list)
    {
        // check the current setting
        $current_list = self::getExcludedTables();
        if ($table_list != $current_list) {
            // store the setting
            Civi::settings()->set('loggingtools_excluded_tables', $table_list);

            // sync the DB schema
            $logging = new CRM_Logging_Schema();
            if ($logging->isEnabled()) {
                $logging->fixSchemaDifferences();
            }
        }
    }

    /**
     * Get the current list of excluded tables
     *
     * @return array
     *   list of excluded tables
     */
    public static function getExcludedTables()
    {
        $current_list = Civi::settings()->get('loggingtools_excluded_tables');
        if (!empty($current_list) && is_array($current_list)) {
            return $current_list;
        } else {
            return [];
        }
    }

    /**
     * Called from the civicrm_alterLogTables hook to exclude certain tables from logging
     *
     * @param array $logTableSpec
     *   logging table data structure
     *
     * @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterLogTables/
     */
    public static function excludeLogTables(&$logTableSpec)
    {
        $table_list = self::getExcludedTables();
        foreach ($table_list as $table_name) {
            unset($logTableSpec[$table_name]);
        }
    }


}
