<?php
require_once('../include/Bootstrap.php');

$allComponents = array();

$test1 = array(
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

$test2 = array(
	'name' => 'Another Test ePortfolio Component',
	'module' => 'eportfolio',
	'component_category' => null,
	'class' => 'Component_Eportfolio_Test2Controller',
	'description' => 'Another random test component manufactured by script',
	'multiple' => 'false',
	'config' => array(
		'pixel' => array(
			'default_value' => 456,
			'label' => 'Dimensions etc'
		)
	)
);

//$allComponents[] = $test1;
$allComponents[] = $test2;

foreach($allComponents as $currentComponent) {
	Component_DefinitionMapper::getInstance()->create($currentComponent);
}