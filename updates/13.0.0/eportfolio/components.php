<?php
require_once('../../../include/Bootstrap.php');

$allComponents = array();

$basicList = array(
	'name' => 'Basic List',
	'module' => 'eportfolio',
	'component_category' => null,
	'class' => 'Component_Eportfolio_BasicListController',
	'description' => 'A list component',
	'multiple' => 'false',
	'config' => array(
		'title' => array(
			'default_value' => 'My List',
			'label' => 'List Title'
		)
	)
);

$allComponents[] = $basicList;

foreach($allComponents as $currentComponent) {
	Component_DefinitionMapper::getInstance()->create($currentComponent);
}