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
 * Form for truncating the logging tables.
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Loggingtools_Form_Truncation extends CRM_Core_Form
{
    public function buildQuickForm()
    {
        parent::buildQuickForm();

        CRM_Utils_System::setTitle(E::ts("Logging Tools Truncation"));

        $this->add(
            'select',
            'time_horizon',
            E::ts('Choose the time horizon:'),
            $this->getTimeHorizons(),
            false,
            ['class' => 'crm-select2 huge']
        );

        $this->add(
            'datepicker',
            'custom_time_horizon',
            E::ts('Custom date:'),
            $this->getTimeHorizons(),
            false,
            ['class' => 'huge disabled']
        );

        $this->addButtons(
            [
                [
                    'type' => 'cancel',
                    'name' => E::ts('Cancel'),
                    'isDefault' => false,
                ],
                [
                    'type' => 'submit',
                    'name' => E::ts('Start'),
                    'isDefault' => true,
                ]
            ]
        );
    }

    public function postProcess()
    {
        parent::postProcess();

        $values  = $this->exportValues();

        $keepSinceDateTime = null;

        if ($values['time_horizon'] === 'custom') {
            $keepSinceDateTime = new DateTime($values['custom_time_horizon']);
        } else {
            $keepSinceDateTime = new DateTime();

            $timeHorizon = new DateInterval('P' . $values['time_horizon'] . 'M');

            $keepSinceDateTime->sub($timeHorizon);
        }

        $keepSinceDateTimeString = $keepSinceDateTime->format('YmdHis');

        $loggingControl = new CRM_Logging_Schema();
        $logTableSpec = $loggingControl->getLogTableSpec();

        $loggingTables = [];
        $dao = new CRM_Core_DAO();
        foreach ($logTableSpec as $key => $value) {
            $potential_logging_table = 'log_' . $key;

            // make sure it exists
            $table_exists = CRM_Core_DAO::executeQuery("
                SELECT TABLE_NAME
                FROM   INFORMATION_SCHEMA.TABLES
                WHERE  TABLE_SCHEMA = '{$dao->_database}'
                AND    TABLE_NAME = '{$potential_logging_table}'
            ");
            if ($table_exists->fetch()) {
                $loggingTables[] = $potential_logging_table;
            }
        }


        $cleanupDeletedEntities = false;

        // Forward back to the previous page:
        $targetUrl = html_entity_decode(CRM_Core_Session::singleton()->readUserContext());

        CRM_Loggingtools_Queue_Runner_TruncationLauncher::launchRunnerViaWeb(
            $keepSinceDateTimeString,
            $loggingTables,
            $cleanupDeletedEntities,
            $targetUrl
        );
    }

    /**
     * Get a list of all possible time horizons for the truncation, including a custom one.
     *
     * @return array
     *   map: number of months => label in human language
     */
    private function getTimeHorizons()
    {
        $result = [
            6 => E::ts('Last %1 months', [1 => 6]),
            12 => E::ts('Last %1 months', [1 => 12]),
            24 => E::ts('Last %1 years', [1 => 2]),
            60 => E::ts('Last %1 years', [1 => 5]),
            'custom' => E::ts('Custom date'),
        ];

        return $result;
    }
}
