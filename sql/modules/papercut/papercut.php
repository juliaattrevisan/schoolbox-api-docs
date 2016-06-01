<?php
/**
 * Creates new breadcrump component
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../../library/Bootstrap.php';

$definition = array(
	'name' => 'Papercut',
	'module' => 'dashboard',
	'class' => 'Component_Dashboard_PapercutController',
	'description' => 'Papercut',
	'multiple' => 'false',
	'config' => array(
		'componentname' => array(
			'default_value' => 'Papercut',
			'label' => 'Heading'
		),
		'url' => array(
			'default_value' => '',
			'label' => 'Server Address'
		),
	)
);

Component_DefinitionMapper::getInstance()->create($definition);
