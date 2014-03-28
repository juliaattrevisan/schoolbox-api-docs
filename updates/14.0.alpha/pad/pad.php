<?php

require_once __DIR__ . '/../../../include/Bootstrap.php';

$component = array(
	'name'               => 'Collaborative Documents',
	'module'             => 'homepage',
	'component_category' => '1',
	'class'              => 'Component_Homepage_PadController',
	'description'        => 'Create collaborative documents with multiple authors in real time',
	'multiple'           => 'false',
	'visible'            => 'true',
	'config'             => array(
		'componentname'  => array(
			'default_value' => 'Collaborative Documents',
			'label'         => 'Heading'
		),
	)
);

Component_DefinitionMapper::getInstance()->create($component);
