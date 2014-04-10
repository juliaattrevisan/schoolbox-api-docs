<?php
/**
* ${NAME}
*
* @author      Steve Chan <steve.chan@alaress.com.au>
* @copyright   Copyright 2011, Alaress Pty Ltd
* @link        http://www.alaress.com.au/
* @version     SVN: $Id$
*/
require_once __DIR__ . '/../../../include/Bootstrap.php';

$component = array(
	'name'               => 'Collab Docs',
	'module'             => 'homepage',
	'component_category' => '2',
	'class'              => 'Component_Homepage_EtherpadController',
	'description'        => 'Create documents with simultaneous multiple authors and real-time editing',
	'multiple'           => 'false',
	'visible'            => 'true',
	'config'             => array(
		'componentname'  => array(
			'default_value' => 'Collab Docs',
			'label'         => 'Heading'
		),
		'size' => array(
			'default_value' => '5',
			'label' => 'Size'
		),
		'src' => array(
			'default_value' => '',
			'label' => 'Source'
		),
		'sort' => array(
			'default_value' => 'name',
			'label' => 'Sort By'
		),
		'sortdir' => array(
			'default_value' => 'asc',
			'label' => 'Direction'
		),
		'compactmode' => array(
			'default_value' => 1,
			'label' => 'Compact Mode'
		),
	)
);

Component_DefinitionMapper::getInstance()->create($component);
