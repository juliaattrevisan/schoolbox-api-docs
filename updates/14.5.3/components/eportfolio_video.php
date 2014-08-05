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
	'name'               => 'Video',
	'module'             => 'eportfolio',
	'component_category' => null,
	'class'              => 'Component_Eportfolio_VideoController',
	'description'        => 'Upload and play videos directly in your ePortfolio',
	'multiple'           => 'true',
	'config'             => array(
		'componentname' => array(
			'default_value' => 'Video',
			'label' => 'Heading',
		),
		'size' => array(
			'default_value' => '10',
			'label' => 'Limit',
		),
		'eportfolioVideo' => array(
			'default_value' => '',
			'label' => 'Video',
		),
		'sort' => array(
			'default_value' => 'date',
			'label' => 'Sort By',
		),
		'sortdir' => array(
			'default_value' => 'desc',
			'label' => 'Direction',
		),
	),
);

Component_DefinitionMapper::getInstance()->create($definition);
