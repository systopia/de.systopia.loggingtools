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
 * The queue/runner.
 */
class CRM_Loggingtools_Queue_Runner_TruncatingRunner
{
    /** @var string $title Will be set as title by the runner. */
    public $title;

    private $keepSinceDateTime;
    private $tableName;
    private $cleanupDeletedEntities;

    public function __construct(string $keepSinceDateTime, string $tableName, bool $cleanupDeletedEntities = false)
    {
        $this->keepSinceDateTime = $keepSinceDateTime;
        $this->tableName = $tableName;
        $this->cleanupDeletedEntities = $cleanupDeletedEntities;

        $this->title = E::ts('Truncating table "%1".', [1 => $tableName]);
    }

    public function run(): bool
    {
        CRM_Loggingtools_Truncater::truncate($this->keepSinceDateTime, $this->tableName, $this->cleanupDeletedEntities);

        return true;
    }
}
