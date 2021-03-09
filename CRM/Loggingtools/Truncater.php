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
class CRM_Loggingtools_Truncater
{
    private const INDEX_COLUMNS = ['id', 'log_date'];
    private const INDEX_PREFIX = 'truncation_'; // TODO: Do we need a better naming for the indexes?

    /**
     * Truncate the given logging table.
     *
     * @param string $keepSinceDateTimeString The point in time up to which the logging entries will be truncated.
     * @param string $tableName The name of the logging table to truncate.
     *                          NOTE: We assume that this is a safe string, e.g. no user input that needed to be escaped.
     * @param string $cleanupDeletedEntities If true, all deleted entities (including the ones that have been deleted
     *                                       after $keepSinceDateTimeString) will be fully truncated.
     *                                       NOTE: This is not implemented yet.
     */
    public function truncate(
        string $keepSinceDateTimeString,
        string $tableName,
        bool $cleanupDeletedEntities = false
    ): void {
        $transaction = new CRM_Core_Transaction();

        try {
            $this->run($keepSinceDateTimeString, $tableName, $cleanupDeletedEntities);
        } catch (Throwable $error) {
            // TODO: The transaction does not include creation or renaming of tables.
            $transaction->rollback();

            Civi::log()->warning("Truncating logging table {$tableName} failed: " . $error->getMessage());

            throw $error;
        }

        $transaction->commit();
    }

    /**
     * The internal running function for $this->truncate, allowing an easy exception wrapper around it.
     */
    private function run(
        string $keepSinceDateTimeString,
        string $tableName,
        bool $cleanupDeletedEntities = false // TODO: Implement full entity cleanup.
    ): void {
        $helperTableName = $tableName . '_truncation';

        $this->initialise($tableName, $helperTableName);
        $this->populateHelperTable($tableName, $helperTableName, $keepSinceDateTimeString);
        $this->deleteOldEntries($tableName, $helperTableName);
        $this->setInitialisationEntries($tableName, $helperTableName, $keepSinceDateTimeString);
        $this->finalise($tableName, $helperTableName);
    }

    /**
     * Initialise the database table structure.
     */
    private function initialise(string $tableName, string $helperTableName): void
    {
        foreach (self::INDEX_COLUMNS as $indexColumn) {
            $indexName = self::INDEX_PREFIX . $indexColumn;

            /** @var CRM_Core_DAO */
            $dao = CRM_Core_DAO::executeQuery(
                "SELECT
                    COUNT(*)
                FROM
                    information_schema.statistics
                WHERE
                    table_name = {$tableName} AND index_name = '{$indexName}'"
            );

            $indexCount = $dao->fetchValue();

            if ($indexCount == 0) {
                CRM_Core_DAO::executeQuery("CREATE INDEX {$indexName} ON {$tableName}({$indexColumn})");
            }
        }

        CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS {$helperTableName}");
        CRM_Core_DAO::executeQuery(
            "CREATE TABLE
                {$helperTableName}
            (
                `id` INTEGER UNSIGNED NOT NULL,
                `log_date` TIMESTAMP NOT NULL,
                UNIQUE INDEX `id` (`id`),
                INDEX `log_date` (`log_date`)
            )"
        );
    }

    /**
     * Write to the helper table the last timestamp for every entity ID which is before or at the keeping timestamp.
     */
    private function populateHelperTable(
        string $tableName,
        string $helperTableName,
        string $keepSinceDateTimeString
    ): void {
        CRM_Core_DAO::executeQuery(
            "INSERT INTO
                {$helperTableName}
            SELECT
                up_to_cutoff.id AS id,
                MAX(up_to_cutoff.log_date) AS log_date
            FROM
            (
                SELECT
                    *
                FROM
                    {$tableName}
                WHERE
                    log_date <= %1
            ) AS up_to_cutoff
            GROUP BY
                up_to_cutoff.id",
            [
                1 => [$keepSinceDateTimeString, 'Timestamp']
            ]
        );
    }

    /**
     * Delete all entries in the logging table for an entity ID that is present in the helper table and has a log_date
     * that is older than the one in the helper table.
     */
    private function deleteOldEntries(string $tableName, string $helperTableName): void
    {
        CRM_Core_DAO::executeQuery(
            "DELETE FROM
                {$tableName} AS log_table
            LEFT JOIN
                {$helperTableName} AS helper_table
                    ON
                        helper_table.id = log_table.id
            WHERE
                helper_table.id IS NOT NULL
                AND log_table.log_date < helper_table.log_date"
        );
    }

    private function setInitialisationEntries(
        string $tableName,
        string $helperTableName,
        string $keepSinceDateTimeString
    ): void {
        $userId = CRM_Core_Session::getLoggedInContactID();

        CRM_Core_DAO::executeQuery(
            "UPDATE
                {$tableName} AS log_table
            LEFT JOIN
                {$helperTableName} AS helper_table
                    ON
                        helper_table.id = log_table.id
            SET
                log_table.log_action := 'Initialization',
                log_table.log_date := %1,
                log_table.log_user_id := {$userId}
                log_table.log_conn_id := NULL
            WHERE
                helper_table.id IS NOT NULL
                AND log_table.log_date = helper_table.log_date",
            [
                1 => [$keepSinceDateTimeString, 'Timestamp']
            ]
        );
    }

    /**
     * Finalise the database table structure by taking it back to its previous structure.
     */
    private function finalise(string $tableName, string $helperTableName): void
    {
        CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS {$helperTableName}");

        foreach (self::INDEX_COLUMNS as $indexColumn) {
            $indexName = self::INDEX_PREFIX . $indexColumn;

            CRM_Core_DAO::executeQuery("DROP INDEX {$indexName} ON {$tableName}");
        }
    }
}
