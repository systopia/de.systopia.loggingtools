<?php

/*-------------------------------------------------------+
| SYSTOPIA LOGGING TOOLS EXTENSION                       |
| Copyright (C) 2020 SYSTOPIA                            |
| Author: B. Zschiedrich (zschiedrich@systopia.de)       |
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
 * Truncating for logging tables.
 */
abstract class CRM_Loggingtools_Truncater
{
    public static function truncate(
        string $keepSinceDateTime,
        string $tableName,
        bool $cleanupDeletedEntities = false
    ): void {
        $oldTableName = $tableName . '_OLD';
        $tempTableName = $tableName . '_TEMP';

        // FIXME: Check if "$tableName" can be user input. Then we MUST use it as parameter in this function:
        CRM_Core_DAO::executeQuery("DROP TABLE {$oldTableName} IF EXISTS");
        CRM_Core_DAO::executeQuery("DROP TABLE {$tempTableName} IF EXISTS");

        CRM_Core_DAO::executeQuery("RENAME TABLE {$tableName} TO {$oldTableName}");

        CRM_Core_DAO::executeQuery("CREATE TABLE {$tempTableName} LIKE {$oldTableName}");

        $query =
            "INSERT INTO
                {$tempTableName}
            SELECT
                *
            FROM
                {$oldTableName}
            WHERE
                log_date >= %1
            ORDER BY
                log_date DESC";

        CRM_Core_DAO::executeQuery(
            $query,
            [
                1 => [$keepSinceDateTime, 'Timestamp']
            ]
        );

        CRM_Core_DAO::executeQuery("RENAME TABLE {$tempTableName} TO {$tableName}");

        CRM_Core_DAO::executeQuery("DROP TABLE {$oldTableName} IF EXISTS");
        CRM_Core_DAO::executeQuery("DROP TABLE {$tempTableName} IF EXISTS");
    }
}
