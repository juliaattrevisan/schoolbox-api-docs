<?php
require_once('../include/Bootstrap.php');

$allComponents = array();

$test = array(
	'name' => 'Test ePortfolio Component',
	'module' => 'eportfolio',
	'component_category' => null,
	'class' => 'Component_Eportfolio_TestController',
	'description' => 'A test component manufactured by script',
	'multiple' => 'false',
	'config' => array(
		'pixel' => array(
			'default_value' => 100,
			'label' => 'Pixels and stuff'
		)
	)
);

$allComponents[] = $test;

foreach($allComponents as $currentComponent) {
	Component_DefinitionMapper::getInstance()->create($currentComponent);
}