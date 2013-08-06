<?php

$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/side-menu.html');
$this->setTitle('Admin - Side Menu');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'side-menu');

$menuHandler = new SideMenu;

$this->setVar('MENU_TREE', $menuHandler->outputTree($menuHandler->getTree()));


?>