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

// get the side menu tree
$mapper = new Schoolbox\SideMenu\Mapper();
$list   = $mapper->findAll(array('order' => array('sequence' => 'asc')));
$tree   = $mapper->getCollectionAsTree($list);

// and render the tree
$renderer = new Schoolbox\SideMenu\Renderer\Admin(new Scrub());
$this->setVar('MENU_TREE', $renderer->renderTree($mapper->placeInRootNode($tree)));
