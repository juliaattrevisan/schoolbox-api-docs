<?php

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/lists.html');
$this->setTitle('Admin - Lists');

$this->setBlock(TEMPLATE_content, 'table_tab', 'table_tab_block');
$this->setBlock(TEMPLATE_content, 'table_heading', 'table_heading_block');
$this->setBlock(TEMPLATE_content, 'table_row', 'table_row_block');
$this->setBlock('table_row', 'table_cell_static', 'table_cell_static_block');
$this->setBlock('table_row', 'table_cell', 'table_cell_block');
$this->setBlock('table_row', 'table_cell_select', 'table_cell_select_block');
$this->setBlock('table_row', 'table_cell_checkbox', 'table_cell_checkbox_block');
$this->setBlock('table_cell_select', 'table_cell_select_option', 'table_cell_select_option_block');
$this->setBlock(TEMPLATE_content, 'table_schema', 'table_schema_block');
$this->setBlock(TEMPLATE_content, 'newInput', 'newInput_block');
$this->setBlock(TEMPLATE_content, 'newSelect', 'newSelect_block');
$this->setBlock('newSelect', 'newSelect_option', 'newSelect_option_block');
$this->setBlock(TEMPLATE_content, 'create_new', 'create_new_block');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'list');

$this->setVar('table_cell_static_block', '');
$this->setVar('table_cell_select_block', '');
$this->setVar('table_cell_checkbox_block', '');
$this->setVar('newSelect_block', '');

$tableList = array(
    array(
        'name' => 'groups',
        'title' => 'Roles',
        'index' => 'id',
        'fields' => array(
            'id'         => array('readonly' => '1'),
            'name'       => array('caption' => 'Name'),
            'group_type' => array('caption' => 'Role Type'),
            'can_change_password' => array('caption' => 'Allow Password Change'),
        ),
    ),
    array(
        'name' => 'house',
        'title' => 'Houses',
        'index' => 'id'
    ),
    array(
        'name' => 'luResidentialHouse',
        'title' => 'Residential Houses',
        'index' => 'id'
    ),
    array(
        'name' => 'luCampus',
        'title' => 'Campus',
        'index' => 'id',
        'fields' => array(
            'id' => array('readonly' => '1'),
            'name' => array('caption' => 'Name'),
            'ext_timetable_code' => array('caption'=>'External Timetable Code'),
            'timetable_day' => array('caption'=>'Timetable Days'),
        )
    ),
    array(
        'name' => 'luEventType',
        'title' => 'Event Types',
        'index' => 'eventTypeID'
    ),
    array(
        'name' => 'file_type',
        'title' => 'Assessment Types',
        'index' => 'id'
    ),
    array(
        'name' => 'newsTopic',
        'title' => 'News Topics',
        'index' => 'newsTopicID'
    ),
    array(
        'name' => 'lu_submission_type',
        'title' => 'Submission Types',
        'index' => 'id'
    ),
    array(
        'name'   => 'submission_errors',
        'title'  => 'Submission States',
        'index'  => 'id',
        'fields' => array(
            'id' => array(
                'readonly' => '1'
            ),
            'code' => array(
                'caption' => 'Code',
            ),
            'display_as' => array(
                'caption' => 'Display Name',
            ),
            'normalise_as' => array(
                'caption' => 'Normalised Mark',
            ),
            'is_default_for_new_submissions' => array(
                'caption' => 'Default for new submissions?',
            )
        ),
    ),
    array(
        'name' => 'component_definition',
        'title' => 'Components',
        'index' => 'id',
        'create' => false,
        'fields' => array(
            'id' => array(
                'readonly' => '1'
            ),
            'module' => array(
                'caption' => 'Module',
                'readonly' => '1'
            ),
            'name' => array(
                'caption' => 'Component Name',
                'readonly' => '1'
            ),
            'description' => array(
                'caption' => 'Description',
                'readonly' => '1'
            ),
            'visible' => array(
                'caption' => 'Visible',
                'readonly' => '0'
            ),
        ),
    ),
    array(
        'name' => 'lu_timetable_colour',
        'title' => 'Timetable Colours',
        'index' => 'timetable_colour_id'
    ),
);

// if no page supplied, default to groups
if (!$this->arg[0]) {
    $this->arg[0] = 'groups';
}

$selectedTable = $tableList[0];
$allTableNames = array();

// construct the sidebar
foreach ($tableList as $table) {
// if the table in question does not exist in the target database, don't try to display it (empty table is a different story)
    $gHandle = new GenericTable($table['name'], $table['index']);
    if (!$gHandle->tableExists()) {
        continue;
    }

    $allTableNames[] = $table['name'];

    $output = array(
        'TABLE_NAME' => $table['name'],
        'TABLE_TITLE' => $table['title'],
        'TABLE_CURRENT' => ''
    );

    $this->setVar($output);

    if ($this->arg[0] == $table['name']) {
        $selectedTable = $table;

        $this->setVar('TABLE_CURRENT', 'current');

        /* Remember the name of the table so we know which lookup table to
           * update lists.
           */
        $this->setVar('TABLE_CURRENT_NAME', $table['name']);
        $this->setVar('TABLE_CURRENT_INDEX', $table['index']);
        $this->parse('table_schema_block', 'table_schema');
    }

    $this->parse('table_tab_block', 'table_tab', true);
}

// test page supplied is valid, if not, error
if (!in_array($this->arg[0], $allTableNames)) {
    error(101);
}

$genericHandler = new GenericTable($selectedTable['name'], $selectedTable['index']);

// restrict the visibility of the table if specified
if (isset($selectedTable['fields'])) {
    $fieldNames = array_keys($selectedTable['fields']);
    $tableDefinition = $genericHandler->getLimitedDefinition($fieldNames);
    $table = $genericHandler->getAllInFields($fieldNames);
} else {
    $tableDefinition = $genericHandler->getDefinition();
    $table = $genericHandler->getAll();
}

if ($table) {
    foreach ($table as $row) {
        foreach ($row as $keys => $data) {
            $this->setVar('TABLE_DATA', Scrub::text($data));
            $this->setVar('TABLE_DATA_CHECKED', $data ? 'checked' : '');
            $this->setVar('TABLE_COLUMN', $keys);

            $currentColumn = $selectedTable['fields'][$keys];

            /* Search definition for row */
            foreach ($tableDefinition as $col) {
                if ($col['Field'] == $keys) {
                    break;
                }
            }

            // Get option list out of current column, or the enum for this column
            $option_list = $currentColumn['options'];
            if (stristr($col['Type'], 'enum')) {
                preg_match('/enum\((.*)\)/', $col['Type'], $match);
                foreach (explode(',', str_replace('\'', '', $match[1])) as $enum_option) {
                    $option_list[$enum_option] = $enum_option;
                }
            }

            if (!$rowOne) {
                if ((isset($currentColumn['caption']))) {
                    $this->setVar('TABLE_HEAD', $currentColumn['caption']);
                } else {
                    $this->setVar('TABLE_HEAD', ucwords(str_replace('_', ' ', $keys)));
                }


                $this->parse('table_heading_block', 'table_heading', true);

                /* New row template. Indexes are readonly.
                 */
                if ($keys == $selectedTable['index']) {
                    $this->setVar('TYPE', 'hidden');
                    $this->setVar('READONLY', 'readonly="readonly"');
                    $this->parse('newInput_block', 'newInput', true);
                } // fields specified as readonly are also...readonly.
                else {
                    if (isset($currentColumn) && $currentColumn['readonly'] == '1') {
                        $this->setVar('TYPE', 'hidden');
                        $this->setVar('READONLY', 'readonly="readonly"');
                        $this->parse('newInput_block', 'newInput', true);
                    } else {
                        // if the field is an enum, explode it, and iteratively create select options for the list
                        // Also do this if fields were provided explicitly.
                        if (!empty($option_list)) {
                            $this->setVar('newSelect_option_block', '');
                            foreach ($option_list as $option_value => $option_label) {
                                $this->setVar('OPTION_VALUE', $option_value);
                                $this->setVar('OPTION_LABEL', $option_label);
                                $this->parse('newSelect_option_block', 'newSelect_option', true);
                            }
                            $this->parse('newSelect_block', 'newSelect', true);
                        } elseif ($col['Type'] === 'tinyint(1)') {
                            // boolean
                            $this->setVar('TYPE', 'checkbox');
                            $this->setVar('READONLY', '');
                            $this->setVar('VALUE', '1');
                            $this->parse('newInput_block', 'newInput', true);
                        } else {
                            // generically, just go with a text input
                            $this->setVar('TYPE', 'text');
                            $this->setVar('READONLY', '');
                            $this->setVar('VALUE', '');
                            $this->parse('newInput_block', 'newInput', true);
                        }
                    }
                }

            }

            if ($keys == $selectedTable['index'] || (isset($currentColumn) && $currentColumn['readonly'] == '1')) {
                $this->parse('table_cell_block', 'table_cell_static', true);
            } else {
                if (!empty($option_list)) {
                    $this->setVar('table_cell_select_option_block', '');
                    foreach ($option_list as $option_value => $option_label) {
                        $this->setVar('OPTION_VALUE', $option_value);
                        $this->setVar('OPTION_LABEL', $option_label);
                        if ($option_value == $data) {
                            $this->setVar('OPTION_VALUE_SELECTED', 'selected="selected"');
                        } else {
                            $this->setVar('OPTION_VALUE_SELECTED', '');
                        }
                        $this->parse('table_cell_select_option_block', 'table_cell_select_option', true);
                    }
                    $this->parse('table_cell_block', 'table_cell_select', true);
                } elseif ($col['Type'] === 'tinyint(1)') {
                    $this->parse('table_cell_block', 'table_cell_checkbox', true);
                } else {
                    $this->parse('table_cell_block', 'table_cell', true);
                }
            }
        }

        $rowOne = 'true';

        $this->parse('table_row_block', 'table_row', true);
        $this->setVar('table_cell_block', '');
    }
} // else table contains no data. still display headers
else {foreach ($tableDefinition as $col) {
        $key = $col['Field'];
        if ((isset($selectedTable['fields'][$key]['caption']))) {
            $this->setVar('TABLE_HEAD', $selectedTable['fields'][$key]['caption']);
        } else {
            $this->setVar('TABLE_HEAD', ucwords(str_replace('_', ' ', $key)));
        }
        $this->parse('table_heading_block', 'table_heading', true);

        $this->setVar('TABLE_COLUMN', $key);

        if ($key == $selectedTable['index']) {
            $this->setVar('TYPE', 'hidden');
            $this->setVar('READONLY', 'readonly="readonly"');
            $this->parse('newInput_block', 'newInput', true);
        } // fields specified as readonly are also...readonly.
        else {
            if (isset($selectedTable['fields'][$key]) && $selectedTable['fields'][$key]['readonly'] == '1') {
                $this->setVar('TYPE', 'hidden');
                $this->setVar('READONLY', 'readonly="readonly"');
                $this->parse('newInput_block', 'newInput', true);
            } // if the field is an enum, explode it, and iteratively create select options for the list
            else {
                if (!empty($option_list)) {
                    $this->setVar('newSelect_option_block', '');
                    foreach ($option_list as $option_value => $option_label) {
                        $this->setVar('OPTION_VALUE', $option_value);
                        $this->setVar('OPTION_LABEL', $option_label);
                        $this->parse('newSelect_option_block', 'newSelect_option', true);
                    }
                    $this->parse('newSelect_block', 'newSelect', true);
                } // generically, just go with a text input
                else {
                    $this->setVar('TYPE', 'text');
                    $this->setVar('READONLY', '');
                    $this->parse('newInput_block', 'newInput', true);
                }
            }
        }

}

    $this->setVar('table_row_block', '');
}

// prevent the creation of new entries if admins should not be able to
if (isset($selectedTable['create']) && $selectedTable['create'] == false) {
    $this->setVar('create_new_block', '');
} else {
    $this->parse('create_new_block', 'create_new', false);
}
