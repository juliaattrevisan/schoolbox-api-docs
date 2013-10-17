<?php
/**
 * Creates new task component
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__.'/../../../include/Bootstrap.php';

// This goes in the Learning category for now. Maybe it gets moved later
$definition = array(
    'name'               => 'Course Builder',
    'module'             => 'homepage',
    'component_category' => '3',
    'class'              => 'Component_Homepage_CourseTaskListController',
    'description'        => 'Use this component to build tasks and assessments for a course.',
    'multiple'           => 'false',
    'config'             => array('componentname' => array('default_value' => 'Course Builder', 'label' => 'Heading'),),
);

Component_DefinitionMapper::getInstance()->create($definition);
