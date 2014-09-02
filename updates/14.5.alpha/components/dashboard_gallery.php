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
	'name'               => 'Image Gallery',
	'module'             => 'dashboard',
	'component_category' => null,
	'class'              => 'Component_Dashboard_ImageGalleryController',
	'description'        => 'Show a grid of image thumbnails sourced from the files uploaded into the Resource Browser.',
	'multiple'           => 'true',
	'config'             => array(
		'componentname' => array(
			'default_value' => 'Image Gallery',
			'label' => 'Heading',
		),
		'size' => array(
			'default_value' => '10',
			'label' => 'Limit',
		),
		'src' => array(
			'default_value' => '200',
			'label' => 'Source',
		),
	),
);

Component_DefinitionMapper::getInstance()->create($definition);
