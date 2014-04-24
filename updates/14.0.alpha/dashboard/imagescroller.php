<?php
/**
 * Creates Dashboard image scroller component
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once __DIR__.'/../../../include/Bootstrap.php';

$definition = array(
	'name'               => 'Image Slideshow',
	'module'             => 'dashboard',
	'component_category' => null,
	'class'              => 'Component_Dashboard_ScrollerController',
	'description'        => 'Display an animated image carousel, fading between all the images',
	'multiple'           => 'true',
	'config'             => array(
		'componentname' => array(
			'default_value' => 'Image Slideshow',
			'label' => 'Heading',
		),
		'align' => array(
			'default_value' => 'left',
			'label' => 'Align',
		),
		'captionState' => array(
			'default_value' => 'Active',
			'label' => 'Captions',
		),
		'pixel' => array(
			'default_value' => '200',
			'label' => 'Max width/height',
		),
		'src' => array(
			'default_value' => '200',
			'label' => 'Source',
		),
		'timing' => array(
			'default_value' => '2',
			'label' => 'Timing',
		),
		'compactMode' => array(
			'default_value' => 1,
			'label' => 'Hide header',
		),
	),
);

Component_DefinitionMapper::getInstance()->create($definition);
