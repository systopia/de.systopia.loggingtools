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
    private const INDEX_PREFIX = 'truncation_';

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
        $timestamp = microtime(true);
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
        Civi::log()->debug("Truncation of '{$tableName}' took %1 seconds", [1 => (int) (microtime(true) - $timestamp)]);
    }

    /**
     * The internal running function for $this->truncate, allowing an easy exception wrapper around it.
     */
    private function run(
        string $keepSinceDateTimeString,
        string $tableName,
        bool $cleanupDeletedEntities = false // TODO: Implement full entity cleanup.
    ): void {

        // make sure table name is sane
        if (!preg_match('/^log_[a-zA-Z_]+$/', $tableName)) {
            throw new Exception(E::ts("Invalid table name '%1'", [1 => $tableName]));
        }

        // convert ARCHIVE type tables
        $this->convertArchiveTable($tableName);

        // now to the actual truncation process
        $helperTableName = $tableName . '_truncation';
        $this->initialise($tableName, $helperTableName);
        $this->populateHelperTable($tableName, $helperTableName, $keepSinceDateTimeString);
        $this->deleteOldEntries($tableName, $helperTableName, $keepSinceDateTimeString);
        $this->setInitialisationEntries($tableName, $helperTableName, $keepSinceDateTimeString);
        $this->finalise($tableName, $helperTableName);
    }

    /**
     * Convert any ARCHIVE tables to InnoDB before proceeding
     */
    private function convertArchiveTable(string $tableName): void
    {
        // check DB engine
        $table_engine = CRM_Core_DAO::singleValueQuery("
         SELECT ENGINE 
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = %1
           AND TABLE_NAME = %2", [
            1 => [CRM_Core_DAO::getDatabaseName(), 'String'],
            2 => [$tableName, 'String'],
        ]);

        if ($table_engine == 'ARCHIVE') {
            // rename table to _archive
            $archive_table_name = $tableName . '_archive';
            CRM_Core_DAO::executeQuery("RENAME TABLE {$tableName} TO {$archive_table_name}");

            // create a copy with ENGINE InnoDB
            //  todo: derive the charset and collation?
            CRM_Core_DAO::executeQuery("
             CREATE TABLE {$tableName} ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
             SELECT * FROM {$archive_table_name}");

            // drop old archive table
            CRM_Core_DAO::executeQuery("DROP TABLE {$archive_table_name}");
        }
    }

    /**
     * Initialise the database table structure.
     */
    private function initialise(string $tableName, string $helperTableName): void
    {
        // create indexes for id and log_date
        $this->createIndexes($tableName);

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
     * Create all indexes on the given table used in truncation, especially for performance improvements.
     */
    private function createIndexes(string $tableName): void
    {
        foreach (self::INDEX_COLUMNS as $indexColumn) {
            $indexName = self::INDEX_PREFIX . $indexColumn;

            /** @var CRM_Core_DAO */
            $dao = CRM_Core_DAO::executeQuery(
                "SELECT
                    COUNT(*)
                FROM
                    information_schema.statistics
                WHERE table_name = %1 
                  AND index_name = %2
                  AND table_schema = %3", [
                        1 => [$tableName, 'String'],
                        2 => [$indexName, 'String'],
                        3 => [CRM_Core_DAO::getDatabaseName(), 'String'],
                    ]
            );

            $indexCount = $dao->fetchValue();

            if ($indexCount == 0) {
                CRM_Core_DAO::executeQuery("CREATE INDEX {$indexName} ON {$tableName}({$indexColumn})");
            }
        }
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
     * Will also delete entries with the log_action set to "Delete" older then the keep date.
     */
    private function deleteOldEntries(
        string $tableName,
        string $helperTableName,
        string $keepSinceDateTimeString
    ): void {
        CRM_Core_DAO::executeQuery(
            "DELETE
                log_table.*
            FROM
                {$tableName} AS log_table
            LEFT JOIN
                {$helperTableName} AS helper_table
                    ON
                        helper_table.id = log_table.id
            WHERE
                (
                    helper_table.id IS NOT NULL
                    AND log_table.log_date < helper_table.log_date
                )
                OR
                (
                    log_table.log_action = 'Delete'
                    AND log_table.log_date < %1
                )",
            [
                1 => [$keepSinceDateTimeString, 'Timestamp']
            ]
        );
    }

    private function setInitialisationEntries(
        string $tableName,
        string $helperTableName,
        string $keepSinceDateTimeString
    ): void {
        $userId = CRM_Core_Session::getLoggedInContactID() ?? 'NULL';

        CRM_Core_DAO::executeQuery(
            "UPDATE
                {$tableName} AS log_table
            LEFT JOIN
                {$helperTableName} AS helper_table
                    ON
                        helper_table.id = log_table.id
                        AND helper_table.log_date = log_table.log_date
            SET
                log_table.log_action := 'Initialization',
                log_table.log_date := %1,
                log_table.log_user_id := {$userId},
                log_table.log_conn_id := NULL
            WHERE
                helper_table.id IS NOT NULL",
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

        // todo: drop created indexes?
    }
}
