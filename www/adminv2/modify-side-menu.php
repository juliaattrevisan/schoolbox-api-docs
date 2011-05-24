<?php
require_once SITE_root . '/library/sideMenu.php';
require_once SITE_root . '/library/user.php';
require_once SITE_root . '/library/group.php';

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/modify-side-menu.html');
$this->setTitle('Admin - Modify Side Menu');

$menuHandler = new SideMenu;
$userHandler = new User;

if(is_numeric($this->arg[0])) {
    /* Modify existing item */

    $menuItem = $menuHandler->getByID($this->arg[0]);

    $output = array('MENUITEMLIST'  => '',
                    'TEXT'          => $menuItem['txt'],
                    'URL'           => $menuItem['url'],
                    'BLANK_SELECTED'    => $menuItem['target']=='_blank'?'selected="selected"':'',
                    'ID'           => $menuItem['id'],
                    'ACTION'         => 'Update');
    $this->setVar($output);


     /* Output group list */
     $this->checkList('group', $userHandler->getGroupList(), $menuHandler->getMenuItemGroupList($this->arg[0]), TEMPLATE_content, array('id'=>'GROUP_ID','name'=>'GROUP_NAME'));
     $this->checkList('campus', $userHandler->getCampusList(),$menuHandler->getMenuItemCampusList($this->arg[0]), TEMPLATE_content, array('id'=>'CAMPUS_ID','name'=>'CAMPUS_NAME'));


}else {
    /* Create new item */
    $output = array('MENUITEMLIST'  => '',
                    'TEXT'          => '',
                    'URL'           => '',
                    'BLANK_SELECTED'=> '',
                    'ID'           => '',
                    'ACTION'         => 'Create');
    $this->setVar($output);

    $this->setBlock(TEMPLATE_content, 'delete', 'delete_block');
    $this->setVar('delete_block','');

     /* Output group list */
     $this->checkList('group', $userHandler->getGroupList(), '', TEMPLATE_content, array('id'=>'GROUP_ID','name'=>'GROUP_NAME'));
     $this->checkList('campus', $userHandler->getCampusList(),'', TEMPLATE_content, array('id'=>'CAMPUS_ID','name'=>'CAMPUS_NAME'));

}

?>
