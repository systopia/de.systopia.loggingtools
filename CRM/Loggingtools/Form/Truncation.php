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

        $this->add(
            'select',
            'logging_tables',
            E::ts('Logging tables:'),
            $this->getLoggingTables(),
            false,
            [
                'class' => 'crm-select2 huge',
                'multiple' => true,
            ]
        );
        // TODO: There must be a possibility to easily select all logging tables.

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

    public function validate()
    {
        $values  = $this->exportValues();

        $allowedLoggingTables = $this->getLoggingTables();
        $loggingTables = $values['logging_tables'];

        foreach ($loggingTables as $loggingTable) {
            // We assume that getLoggingTables returns a map (as it does for the UI elements) with the logging table
            // name both as value and as key, so we can use the O(1) performing operation of array_key_exists to check
            // whether the table name is valid:
            if (!array_key_exists($loggingTable, $allowedLoggingTables)) {
                $this->_errors['logging_tables'] = E::ts(
                    'The given table name "%1" is not a valid logging table.',
                    [1 => $loggingTable]
                );
                break;
            }
        }

        parent::validate();
        return (0 == count($this->_errors));
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

        $loggingTables = $values['logging_tables'];
        if (empty($loggingTables)) {
            $loggingTables = $this->getLoggingTables();
            // TODO: This is not as clean as it should because getLoggingTables is a UI function returning a map.
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

    /**
     * Get a list of all logging tables.
     *
     * @return array
     *   map: logging table name => logging table name (key and value are identical)
     */
    private function getLoggingTables(): array
    {
        $loggingControl = new CRM_Logging_Schema();
        $logTableSpec = $loggingControl->getLogTableSpec();

        $loggingTables = [];
        $dao = new CRM_Core_DAO();
        foreach ($logTableSpec as $key => $value) {
            $potential_logging_table = 'log_' . $key;

            // Make sure it exists:
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

        // We need the logging tables as name => name for the UI select elements:
        /* TODO: This is a bit dirty as we do not only need these table names for the UI but for the logic as well.
                 It would be best to have the possibility to get the names either as map or as a bare list.
                 For this, something like a naming difference UI and logic lists/maps functions would be great. */
        $loggingTablesAsKeyToValue = [];
        foreach ($loggingTables as $loggingTable) {
            $loggingTablesAsKeyToValue[$loggingTable] = $loggingTable;
        }

        return $loggingTablesAsKeyToValue;
    }
}
