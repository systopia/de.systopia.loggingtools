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
 * Settings Form
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Loggingtools_Form_Settings extends CRM_Core_Form
{
    public function buildQuickForm()
    {
        CRM_Utils_System::setTitle(E::ts("Logging Tools"));

        // add form elements
        $this->add(
            'select',
            'exclude_logging_tables',
            E::ts('Exclude tables from extended logging'),
            $this->getAllTables(),
            false,
            ['class' => 'crm-select2 huge', 'multiple' => 'multiple']
        );

        $this->setDefaults([
            'exclude_logging_tables' => CRM_Loggingtools_LoggingTables::getExcludedTables()
        ]);

        $this->addButtons([
                [
                    'type'      => 'submit',
                    'name'      => E::ts('Save'),
                    'isDefault' => true,
                ],
            ]);

        parent::buildQuickForm();
    }

    public function postProcess()
    {
        $values  = $this->exportValues();

        // update logging tables
        CRM_Loggingtools_LoggingTables::setExcludedTables($values['exclude_logging_tables']);

        parent::postProcess();
    }


    /**
     * Get a list of all CiviCRM tables
     *
     * @return array
     *   map: table name => table label
     */
    protected function getAllTables()
    {
        $tables = [];

        // Copied from CRM_Logging_Schema::__construct
        $dao = new CRM_Contact_DAO_Contact();
        $dao = CRM_Core_DAO::executeQuery("
            SELECT TABLE_NAME
            FROM   INFORMATION_SCHEMA.TABLES
            WHERE  TABLE_SCHEMA = '{$dao->_database}'
            AND    TABLE_TYPE = 'BASE TABLE'
            AND    TABLE_NAME LIKE 'civicrm_%'
        ");
        while ($dao->fetch()) {
            $tables[] = $dao->TABLE_NAME;
        }

        // exclude the same tables as CRM_Logging_Schema::__construct
        $tables = preg_grep('/^civicrm_import_job_/', $tables, PREG_GREP_INVERT);
        $tables = preg_grep('/_cache$/', $tables, PREG_GREP_INVERT);
        $tables = preg_grep('/_log/', $tables, PREG_GREP_INVERT);
        $tables = preg_grep('/^civicrm_queue_/', $tables, PREG_GREP_INVERT);
        $tables = preg_grep('/^civicrm_menu/', $tables, PREG_GREP_INVERT);
        $tables = preg_grep('/_temp_/', $tables, PREG_GREP_INVERT);
        $tables = preg_grep('/_bak$/', $tables, PREG_GREP_INVERT);
        $tables = preg_grep('/_backup$/', $tables, PREG_GREP_INVERT);
        $tables = preg_grep('/^civicrm_mailing_event_/', $tables, PREG_GREP_INVERT);
        $tables = array_diff($tables, array('civicrm_mailing_recipients'));

        // create a labelled version of it
        $labeled_tables = [];
        foreach ($tables as $table_name) {
            $short_name = preg_replace('/^civicrm_/', '', $table_name);
            $labeled_tables[$table_name] = str_replace(' ', '', ucwords(str_replace('_', ' ', $short_name))) . " ({$table_name})";
        }
        return $labeled_tables;
    }
}
