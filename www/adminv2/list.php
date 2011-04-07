<?php
require_once SITE_root . '/library/generic.php';

$this->setFile(TEMPLATE_content, 'adminv2/lists.html');

$this->setBlock(TEMPLATE_content, 'table_tab', 'table_tab_block');
$this->setBlock(TEMPLATE_content, 'table_heading', 'table_heading_block');
$this->setBlock(TEMPLATE_content, 'table_row', 'table_row_block');
$this->setBlock('table_row', 'table_cell_static', 'table_cell_static_block');
$this->setBlock('table_row', 'table_cell', 'table_cell_block');
$this->setBlock(TEMPLATE_content, 'table_schema', 'table_schema_block');
$this->setBlock(TEMPLATE_content, 'newData', 'newData_block');

$this->setVar('table_cell_static_block','');

$tableList = array(
        array(  'name'      => 'groups',
                'title'     => 'Groups',
                'index'     => 'id'),
        array(  'name'      => 'luCampus',
                'title'     => 'Campus',
                'index'     => 'id'),
        array(  'name'      => 'luEventType',
                'title'     => 'Event Types',
                'index'     => 'eventTypeID'),
        array(  'name'      => 'file_type',
                'title'     => 'Assessment Types',
                'index'     => 'id')
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

$table = $genericHandler->getAll();

foreach($table as $row) {
    foreach($row as $keys => $data) {
        $this->setVar('TABLE_DATA', $data);
		$this->setVar('TABLE_COLUMN', $keys);

        if (!$rowOne) {
            $this->setVar('TABLE_HEAD', $keys);
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
        else {
            $this->parse('table_cell_block','table_cell',true);
		}
    }

    $rowOne = 'true';

    $this->parse('table_row_block','table_row',true);
    $this->setVar('table_cell_block','');
}

?>