<?php

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/modify-side-menu.html');
$this->setTitle('Admin - Modify Side Menu');
$this->setBlock(TEMPLATE_content, 'parent_select', 'parent_select_block');
$this->setBlock('parent_select', 'menu_select', 'menu_select_block');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'side-menu');

$menuHandler = new SideMenu;
$userHandler = new User;

if (is_numeric($this->arg[0])) {
    /* Modify existing item */

    $menuItem = $menuHandler->getByID($this->arg[0]);

    $output = array(
        'MENUITEMLIST' => '',
        'TEXT' => $menuItem['txt'],
        'URL' => $menuItem['url'],
        'BLANK_SELECTED' => $menuItem['target'] == '_blank' ? 'selected="selected"' : '',
        'ID' => $menuItem['id'],
        'PARENT_ID' => $menuItem['parent'],
        'ACTION' => 'Update'
    );
    $this->setVar($output);

    /* Output group list */
    $this->checkList('group', $userHandler->getGroupList(), $menuHandler->getMenuItemGroupList($this->arg[0]), TEMPLATE_content, array(
        'id' => 'GROUP_ID',
        'name' => 'GROUP_NAME'
    ));
    $this->checkList('campus', $userHandler->getCampusList(), $menuHandler->getMenuItemCampusList($this->arg[0]), TEMPLATE_content, array(
        'id' => 'CAMPUS_ID',
        'name' => 'CAMPUS_NAME'
    ));

    // blank the parent selector
    $this->setVar('parent_select_block', '');

} else {
    /* Create new item */
    $output = array(
        'MENUITEMLIST' => '',
        'TEXT' => '',
        'URL' => '',
        'BLANK_SELECTED' => '',
        'ID' => '',
	    'PARENT_ID' => '0',
        'ACTION' => 'Create'
    );
    $this->setVar($output);

    $this->setBlock(TEMPLATE_content, 'delete', 'delete_block');
    $this->setVar('delete_block', '');

    /* Output group list */
    $this->checkList('group', $userHandler->getGroupList(), '', TEMPLATE_content, array(
        'id' => 'GROUP_ID',
        'name' => 'GROUP_NAME'
    ));
    $this->checkList('campus', $userHandler->getCampusList(), '', TEMPLATE_content, array(
        'id' => 'CAMPUS_ID',
        'name' => 'CAMPUS_NAME'
    ));

    // don't hide the parent select block

    $this->setVar('MENU_TREE', $menuHandler->outputTree($menuHandler->getTree(), 0, true));
    $this->parse('parent_select_block', 'parent_select', false);
}

?>
