<?php
require_once SITE_root . '/library/sideMenu.php';
require_once SITE_root . '/library/user.php';

$this->setFile(TEMPLATE_content, 'adminv2/modify-side-menu.html');

$menuHandler = new SideMenu;
$userHandler = new User;

if(is_numeric($this->arg[0])) {
    /* Modify existing item */

    $menuItem = $menuHandler->getByID($this->arg[0]);

    $output = array('MENUITEMLIST'  => '',
                    'TEXT'          => $menuItem['txt'],
                    'URL'           => $menuItem['url'],
                    'BLANK_SELECTED'    => $menuItem['target']=='_blank'?'selected="selected"':'',
                    'IDFIELD'       => '<input type="hidden" name');
    $this->setVar($output);



     /* Output group list */
     $this->checkList('group', $userHandler->getGroupList(), $menuHandler->getMenuItemGroupList($this->arg[0]), TEMPLATE_content, array('id'=>'GROUP_ID','name'=>'GROUP_NAME'));
     $this->checkList('campus', $userHandler->getCampusList(),$menuHandler->getMenuItemCampusList($this->arg[0]), TEMPLATE_content, array('id'=>'CAMPUS_ID','name'=>'CAMPUS_NAME'));

}else {
    /* Create new item */
}

?>
