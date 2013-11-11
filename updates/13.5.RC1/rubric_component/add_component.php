<?php
/**
 * Creates new rubric component
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__.'/../../../include/Bootstrap.php';

$definition = array(
    'name'               => 'Rubrics',
    'module'             => 'homepage',
    'component_category' => '3',
    'class'              => 'Component_Homepage_RubricController',
    'description'        => 'View and edit rubrics for this subject or course.',
    'multiple'           => 'false',
    'config'             => array('componentname' => array('default_value' => 'Rubrics', 'label' => 'Heading'),),
);

Component_DefinitionMapper::getInstance()->create($definition);
