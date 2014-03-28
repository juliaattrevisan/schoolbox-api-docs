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
	'name'               => 'Collaborative Documents',
	'module'             => 'homepage',
	'component_category' => '2',
	'class'              => 'Component_Homepage_EtherpadController',
	'description'        => 'A pad in the ether',
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
