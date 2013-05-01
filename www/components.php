<?php
require_once('../include/Bootstrap.php');

$allComponents = array();

$basicList = array(
	'name' => 'Basic List',
	'module' => 'eportfolio',
	'component_category' => null,
	'class' => 'Component_Eportfolio_BasicListController',
	'description' => 'A list component',
	'multiple' => 'true',
	'config' => array(
		'componentname' => array(
			'default_value' => 'My List',
			'label' => 'List Title',
		),
	)
);

$advancedList = array(
	'name' => 'Advanced List',
	'module' => 'eportfolio',
	'component_category' => null,
	'class' => 'Component_Eportfolio_AdvancedListController',
	'description' => 'An advanced list component',
	'multiple' => 'true',
	'config' => array(
		'componentname' => array(
			'default_value' => 'My Advanced List',
			'label' => 'List Title',
		),
	)
);

$linkList = array(
	'name' => 'Link List',
	'module' => 'eportfolio',
	'component_category' => null,
	'class' => 'Component_Eportfolio_LinkListController',
	'description' => 'A link list component',
	'multiple' => 'true',
	'config' => array(
		'componentname' => array(
			'default_value' => 'My Link List',
			'label' => 'List Title',
		),
	)
);

$textbox = array(
	'name' => 'Textbox',
	'module' => 'eportfolio',
	'component_category' => null,
	'class' => 'Component_Eportfolio_TextboxController',
	'description' => 'A textbox component',
	'multiple' => 'true',
	'config' => array(
		'componentname' => array(
			'default_value' => 'My Textbox',
			'label' => 'Textbox Title',
		),
	)
);

$fileList = array(
	'name' => 'My Files',
	'module' => 'eportfolio',
	'component_category' => null,
	'class' => 'Component_Eportfolio_FileListController',
	'description' => 'A files component',
	'multiple' => 'false',
	'config' => array(
		'componentname' => array(
			'default_value' => 'My Files',
			'label' => 'Title',
		),
	)
);

$imageList = array(
	'name' => 'Image Gallery',
	'module' => 'eportfolio',
	'component_category' => null,
	'class' => 'Component_Eportfolio_ImageListController',
	'description' => 'An image component',
	'multiple' => 'false',
	'config' => array(
		'componentname' => array(
			'default_value' => 'Image Gallery',
			'label' => 'Title',
		),
	)
);


$blogList = array(
	'name' => 'Blog',
	'module' => 'eportfolio',
	'component_category' => null,
	'class' => 'Component_Eportfolio_BlogController',
	'description' => 'A blog component',
	'multiple' => 'false',
	'config' => array(
		'componentname' => array(
			'default_value' => 'Blog',
			'label' => 'Title',
		),
	)
);

//$allComponents[] = $basicList;
//$allComponents[] = $advancedList;
//$allComponents[] = $linkList;
//$allComponents[] = $textbox;
//$allComponents[] = $fileList;
//$allComponents[] = $imageList;
$allComponents[] = $blogList;

foreach($allComponents as $currentComponent) {
	Component_DefinitionMapper::getInstance()->create($currentComponent);
}