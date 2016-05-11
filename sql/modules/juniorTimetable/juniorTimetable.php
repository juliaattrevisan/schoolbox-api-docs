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
	'name' => 'Junior Timetable',
	'module' => 'dashboard',
	'class' => 'Component_Dashboard_JuniorTimetableController',
	'description' => 'Junior Timetable',
	'multiple' => 'false',
	'config' => array(
		'componentname' => array(
			'default_value' => 'Junior Timetable',
			'label' => 'Heading'
		),
	)
);

Component_DefinitionMapper::getInstance()->create($definition);
