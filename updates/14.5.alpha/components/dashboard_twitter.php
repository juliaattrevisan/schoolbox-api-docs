<?php
/**
 * Creates Dashboard image scroller component
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once __DIR__ . '/../../../include/Bootstrap.php';

$definition = array(
	'name'               => 'Twitter',
	'module'             => 'dashboard',
	'component_category' => null,
	'class'              => 'Component_Dashboard_TwitterController',
	'description'        => 'Completely configurable image display, select the image, control the size and link to another location',
	'multiple'           => 'true',
	'config'             => array(
		'componentname' => array(
			'default_value' => 'Twitter',
			'label' => 'Heading',
		),
		'size' => array(
			'default_value' => '3',
			'label' => 'Limit',
		),
		'keywords' => array(
			'default_value' => '',
			'label' => 'Hashtag',
		),
		'username' => array(
			'default_value' => '',
			'label' => 'Author',
		),
	),
);

Component_DefinitionMapper::getInstance()->create($definition);
