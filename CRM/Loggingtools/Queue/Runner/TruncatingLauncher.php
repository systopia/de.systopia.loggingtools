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
 * The launcher for a queue/runner generating documents.
 */
abstract class CRM_Loggingtools_Queue_Runner_TruncatingLauncher
{
    /**
     * Create a runner
     */
    private static function createRunner(
        string $keepSinceDateTime,
        array $tableNames,
        bool $cleanupDeletedEntities,
        string $targetUrl
    ): CRM_Queue_Runner {
        $queue = CRM_Queue_Service::singleton()->create(
            [
                'type' => 'Sql',
                // TODO: Maybe the name should be postfixed with an unique value to prevent collisions:
                'name' => 'loggingtools_truncating_' . CRM_Core_Session::singleton()->getLoggedInContactID(),
                'reset' => true,
            ]
        );

        $queue->createItem(new CRM_Loggingtools_Queue_Runner_TruncatingRunnerStart());

        foreach ($tableNames as $tableName) {
            $queue->createItem(
                new CRM_Loggingtools_Queue_Runner_TruncatingRunner(
                    $keepSinceDateTime,
                    $tableName,
                    $cleanupDeletedEntities
                )
            );
        }

        $loggingControl = new CRM_Logging_Schema();
        $loggingIsEnabled = $loggingControl->isEnabled();

        $queue->createItem(new CRM_Loggingtools_Queue_Runner_TruncatingRunnerEnd($loggingIsEnabled));

        $runner = new CRM_Queue_Runner(
            [
                'title' => E::ts('Truncating logging tables'),
                'queue' => $queue,
                'errorMode' => CRM_Queue_Runner::ERROR_ABORT,
                'onEndUrl' => $targetUrl,
            ]
        );

        return $runner;
    }

    public static function launchRunnerViaWeb(
        string $keepSinceDateTime,
        array $tableNames,
        bool $cleanupDeletedEntities,
        string $targetUrl
    ): void {
        $runner = self::createRunner($keepSinceDateTime, $tableNames, $cleanupDeletedEntities, $targetUrl);

        $runner->runAllViaWeb();
    }
}
