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
        $oldTableName = $tableName . '_OLD';
        $tempTableName = $tableName . '_TEMP';

        $keepSinceDateTime = new DateTime($keepSinceDateTimeString);

        $this->initialise($tableName, $oldTableName, $tempTableName);

        /** @var CRM_Core_DAO */
        $dao = CRM_Core_DAO::executeQuery("SELECT * FROM {$oldTableName}");

        $insertedIds = [];

        $lastDateTime = new DateTime('0000-00-00');
        while ($dao->fetch()) {
            $row = $this->getColumnNamesToValues($dao, $tempTableName);

            $date = new DateTime($row['log_date']);

            if ($lastDateTime > $date) {
                throw new Exception(E::ts('The log table is not ordered by time.'));
            }

            if ($date >= $keepSinceDateTime) {
                // If this is the first entry after the cut off time we must drop the index because now IDs do not
                // need to be unique anymore. Furthermore, this will increase insert performance.
                if ($lastDateTime < $keepSinceDateTime) {
                    CRM_Core_DAO::executeQuery("ALTER TABLE {$tempTableName} DROP INDEX IF EXISTS `PRIMARY`");
                }

                $this->insert($row, $tempTableName);
            } else {
                if ($row['log_action'] === 'Delete') {
                    $this->delete($row, $tempTableName);
                } else {
                    $cutOffDateTimeString = $keepSinceDateTime->format('YmdHis');

                    $row['log_date'] = $cutOffDateTimeString;
                    $row['log_conn_id'] = 'NULL';
                    $row['log_user_id'] = CRM_Core_Session::getLoggedInContactID();
                    $row['log_action'] = 'Initialization';

                    if (array_key_exists($row['id'], $insertedIds)) {
                        $this->update($row, $tempTableName);
                    } else {
                        $this->insert($row, $tempTableName);

                        $insertedIds[$row['id']] = true;
                    }
                }
            }

            $lastDateTime = $date;
        }

        $this->finalise($tableName, $oldTableName, $tempTableName);
    }

    /**
     * Initialise the database table structure.
     */
    private function initialise(string $tableName, string $oldTableName, string $tempTableName): void
    {
        CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS {$oldTableName}");
        CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS {$tempTableName}");

        CRM_Core_DAO::executeQuery("RENAME TABLE {$tableName} TO {$oldTableName}");

        CRM_Core_DAO::executeQuery("CREATE TABLE {$tempTableName} AS SELECT * FROM {$oldTableName}");

        //CRM_Core_DAO::executeQuery("CREATE TABLE {$tempTableName} LIKE {$oldTableName}");

        //CRM_Core_DAO::executeQuery("ALTER TABLE {$tempTableName} ENGINE = InnoDB");

        CRM_Core_DAO::executeQuery("ALTER TABLE {$tempTableName} ADD PRIMARY KEY (id)");
    }

    /**
     * Finalise the database table structure by taking it back to its previous structure.
     */
    private function finalise(string $tableName, string $oldTableName, string $tempTableName): void
    {
        CRM_Core_DAO::executeQuery("ALTER TABLE {$tempTableName} DROP INDEX IF EXISTS `PRIMARY`");

        CRM_Core_DAO::executeQuery("RENAME TABLE {$tempTableName} TO {$tableName}");

        CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS {$oldTableName}");
        CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS {$tempTableName}");
    }

    /**
     * Insert a row into the given table.
     */
    private function insert(array $row, string $tableName): void
    {
        // TODO: The column string could be cached.
        $columnsAsString = implode(', ', array_keys($row));

        $parameters = [];
        $placeholders = [];
        $counter = 1;

        $columnsAndTypes = $this->getColumnNamesToTypes($tableName);

        foreach ($columnsAndTypes as $columnName => $type) {
            $value = $row[$columnName];

            if (($type === 'Date') || ($type === 'Timestamp')) {
                $date = new DateTime($value);
                $value = $date->format('YmdHis');
            }

            if ($value === null) {
                $placeholders[] = 'NULL';
            } else {
                $parameters[$counter] = [$value, $type];

                $placeholders[] = "%$counter";

                $counter++;
            }
        }

        $placesholdersAsString = implode(', ', $placeholders);

        CRM_Core_DAO::executeQuery(
            "INSERT INTO
                {$tableName} ({$columnsAsString})
            VALUES
                ($placesholdersAsString)",
            $parameters
        );
    }

    /**
     * Update a row in the given table.
     */
    private function update(array $row, string $tableName): void
    {
        $keyPlaceholderList = [];
        $parameters = [];
        $counter = 1;

        $columnsAndTypes = $this->getColumnNamesToTypes($tableName);

        foreach ($columnsAndTypes as $columnName => $type) {
            $value = $row[$columnName];

            if (($type === 'Date') || ($type === 'Timestamp')) {
                $date = new DateTime($value);
                $value = $date->format('YmdHis');
            }

            if ($value === null) {
                $$keyPlaceholderList[] = "$columnName = NULL";
            } else {
                $parameters[$counter] = [$value, $type];

                $keyPlaceholderList[] = "$columnName = %$counter";

                $counter++;
            }
        }

        $keyPlaceholderListAsString = implode(', ', $keyPlaceholderList);

        $rowId = $row['id'];

        CRM_Core_DAO::executeQuery(
            "UPDATE
                {$tableName}
            SET
                {$keyPlaceholderListAsString}
            WHERE
                id = {$rowId}",
            $parameters
        );
    }

    /**
     * Delete a row from the given table.
     */
    private function delete(array $row, string $tableName): void
    {
        $rowId = $row['id'];

        CRM_Core_DAO::executeQuery("DELETE FROM {$tableName} WHERE id = {$rowId}");
    }

    /**
     * Get the columns and their values of the given DAO as a key-value-pair.
     */
    private function getColumnNamesToValues(CRM_Core_DAO $dao, string $tableName): array
    {
        $columnsAndTypes = $this->getColumnNamesToTypes($tableName);

        $result = [];

        foreach ($columnsAndTypes as $columnName => $type) {
            $result[$columnName] = $dao->$columnName;
        }

        return $result;
    }

    /**
     * Get the columns and their types of the given table as a key-value-pair.
     */
    private function getColumnNamesToTypes(string $tableName): array
    {
        $dao = CRM_Core_DAO::executeQuery(
            "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$tableName}'"
        );

        $result = [];

        while ($dao->fetch()) {
            $type = $dao->DATA_TYPE;

            switch ($type) {
                case 'char':
                case 'varchar':
                case 'enum':
                case 'text':
                case 'longtext':
                case 'blob':
                case 'mediumblob':
                    $type = 'String';
                    break;
                case 'tinyint':
                case 'smallint':
                case 'bigint':
                    $type = 'Int';
                    break;
                case 'datetime':
                    $type = 'Date';
                    break;
                case 'decimal':
                case 'double':
                    $type = 'Float';
                    break;
                default:
                    $type = ucfirst($type);
            }

            $result[$dao->COLUMN_NAME] = $type;
        }

        return $result;
    }
}
