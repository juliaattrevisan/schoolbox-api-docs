<?php
require_once SITE_root . '/library/generic.php';

$this->setFile(TEMPLATE_content, 'adminv2/lists.html');

$this->setBlock(TEMPLATE_content, 'table_tab', 'table_tab_block');
$this->setBlock(TEMPLATE_content, 'table_heading', 'table_heading_block');
$this->setBlock(TEMPLATE_content, 'table_row', 'table_row_block');
$this->setBlock('table_row', 'table_cell_static', 'table_cell_static_block');
$this->setBlock('table_row', 'table_cell', 'table_cell_block');

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

 if(!$this->arg[0])
     $this->arg[0] = 'groups';

$selectedTable = $tableList[0];

foreach($tableList as $table) {
    $output = array('TABLE_NAME'    => $table['name'],
                    'TABLE_TITLE'   => $table['title'],
                    'TABLE_CURRENT' => '');
    $this->setVar($output);

    if($this->arg[0] == $table['name']) {
        $selectedTable = $table;
        $this->setVar('TABLE_CURRENT','current');
    }
    
    $this->parse('table_tab_block', 'table_tab',true);
}

$genericHandler = new GenericTable($selectedTable['name'],$selectedTable['index']);

$table = $genericHandler->getTable();

foreach($table as $row) {
    foreach($row as $keys => $data) {
        if(!$rowOne) {
            $this->setVar('TABLE_HEAD', $keys);
            $this->parse('table_heading_block','table_heading',true);
        }

        $this->setVar('TABLE_DATA', $data);
        if($keys == $selectedTable['index'])
            $this->parse('table_cell_block','table_cell_static',true);
        else
            $this->parse('table_cell_block','table_cell',true);
    }

    $rowOne = 'true';
    $this->parse('table_row_block','table_row',true);
    $this->setVar('table_cell_block','');
}

?>