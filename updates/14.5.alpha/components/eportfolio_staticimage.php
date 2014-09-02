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
	'name'               => 'Image',
	'module'             => 'eportfolio',
	'component_category' => null,
	'class'              => 'Component_Eportfolio_ImageController',
	'description'        => 'Completely configurable image display, select the image, control the size and link to another location',
	'multiple'           => 'true',
	'config'             => array(
		'eportfolioImage' => array(
			'default_value' => 'Preview Image',
			'label' => 'Image',
		),
		'align' => array(
			'default_value' => 'left',
			'label' => 'Align',
		),
		'pixel' => array(
			'default_value' => '200',
			'label' => 'Max width/height',
		),
	),
);

Component_DefinitionMapper::getInstance()->create($definition);
