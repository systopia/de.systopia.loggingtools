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
 * Starting queue/runner.
 */
class CRM_Loggingtools_Queue_Runner_TruncatingRunnerEnd
{
    /** @var string $title Will be set as title by the runner. */
    public $title;

    private $loggingWasPreviouslyEnabled;

    public function __construct(bool $loggingWasPreviouslyEnabled)
    {
        $this->loggingWasPreviouslyEnabled = $loggingWasPreviouslyEnabled;

        $this->title = E::ts('Finishing table truncation...');
    }

    public function run(): bool
    {
        if ($this->loggingWasPreviouslyEnabled) {
            $loggingControl = new CRM_Logging_Schema();
            $loggingControl->enableLogging();
        }

        return true;
    }
}
