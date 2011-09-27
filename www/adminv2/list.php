<?php
require_once SITE_root . '/library/generic.php';
require_once SITE_root . '/library/group.php';

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
$this->setBlock('table_cell_select','table_cell_select_option','table_cell_select_option_block');
$this->setBlock(TEMPLATE_content, 'table_schema', 'table_schema_block');
$this->setBlock(TEMPLATE_content, 'newData', 'newData_block');

$this->setVar('table_cell_static_block','');
$this->setVar('table_cell_select_block','');

$tableList = array(
	array(
		'name'      => 'groups',
		'title'     => 'Groups',
		'index'     => 'id'
	),
	array(
		'name'      => 'luCampus',
		'title'     => 'Campus',
		'index'     => 'id'
	),
	array(
		'name'      => 'luEventType',
		'title'     => 'Event Types',
		'index'     => 'eventTypeID'
	),
	array(
		'name'      => 'file_type',
		'title'     => 'Assessment Types',
		'index'     => 'id'
	),
	array(
		'name'      => 'newsTopic',
		'title'     => 'News Topics',
		'index'     => 'newsTopicID'
	),
    	array(
		'name'      => 'assess_question_category',
		'title'     => 'Question Bank',
		'index'     => 'question_category_id'
	)
);

if (!$this->arg[0]) {
	$this->arg[0] = 'groups';
}

$selectedTable = $tableList[0];

foreach($tableList as $table) {
    $output = array(
		'TABLE_NAME'    => $table['name'],
		'TABLE_TITLE'   => $table['title'],
		'TABLE_CURRENT' => ''
	);

    $this->setVar($output);

    if ($this->arg[0] == $table['name']) {
		$selectedTable = $table;
		
		$this->setVar('TABLE_CURRENT','current');

		/* Remember the name of the table so we know which lookup table to
		 * update lists.
		 */
		$this->setVar('TABLE_CURRENT_NAME', $table['name']);
		$this->setVar('TABLE_CURRENT_INDEX', $table['index']);
		$this->parse('table_schema_block', 'table_schema');
    }
    
    $this->parse('table_tab_block', 'table_tab',true);
}

$genericHandler = new GenericTable($selectedTable['name'], $selectedTable['index']);

$tableDefinition = $genericHandler->getDefinition();
$table = $genericHandler->getAll();

foreach($table as $row) {
    foreach($row as $keys => $data) {
        $this->setVar('TABLE_DATA', $data);
		$this->setVar('TABLE_COLUMN', $keys);

        /* Search definition for row */
        foreach($tableDefinition as $col) {
            if($col['Field']==$keys)
                break;
        }


        if (!$rowOne) {
            $this->setVar('TABLE_HEAD', ucwords(str_replace('_',' ',$keys)));
            $this->parse('table_heading_block','table_heading',true);

			/* New row template. Indexes are readonly.
			 */
			if ($keys == $selectedTable['index']) {
				$this->setVar('TYPE', 'hidden');
				$this->setVar('READONLY', 'readonly="readonly"');
			}
			else {
				$this->setVar('TYPE', 'text');
				$this->setVar('READONLY', '');
			}

			$this->parse('newData_block', 'newData', true);
        }

        if ($keys == $selectedTable['index']) {
            $this->parse('table_cell_block','table_cell_static',true);
		}
        else if(stristr($col['Type'],'enum') ){
            $this->setVar('table_cell_select_option_block','');
            
            preg_match('/enum\((.*)\)/', $col['Type'], $match);
            $list = explode(',',str_replace('\'','',$match[1]));
            foreach($list as $option) {
                $this->setVar('OPTION_VALUE', $option);
                if($option==$data) {
                    $this->setVar('OPTION_VALUE_SELECTED', 'selected="selected"');
                }else {
                    $this->setVar('OPTION_VALUE_SELECTED', '');
                }
                $this->parse('table_cell_select_option_block','table_cell_select_option',true);
            }
            $this->parse('table_cell_block','table_cell_select',true);
        }
        else {
            $this->parse('table_cell_block','table_cell',true);
		}
    }

    $rowOne = 'true';

    $this->parse('table_row_block','table_row',true);
    $this->setVar('table_cell_block','');
}

?>