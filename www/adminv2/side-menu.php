<?php

require_once SITE_root . '/library/sideMenu.php';

$this->setFile(TEMPLATE_content, 'adminv2/side-menu.html');
$this->setTitle('Admin - Side Menu');

$menuHandler = new SideMenu;

$this->setVar('MENU_TREE', $menuHandler->outputTree($menuHandler->getTree()));


?>