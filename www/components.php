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

$allComponents[] = $basicList;
$allComponents[] = $advancedList;

foreach($allComponents as $currentComponent) {
	Component_DefinitionMapper::getInstance()->create($currentComponent);
}