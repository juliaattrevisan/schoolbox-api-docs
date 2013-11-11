<?php
/**
 * Creates new course instance component
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__.'/../../../include/Bootstrap.php';

// This goes in the Learning category for now. Maybe it gets moved later
$definition = array(
    'name'               => 'Course Outline',
    'module'             => 'homepage',
    'component_category' => '3',
    'class'              => 'Component_Homepage_CourseInstanceController',
    'description'        => 'Import a course onto your class page, along with its tasks, assessments and rubrics.',
    'multiple'           => 'true',
    'config'             => array(
        'componentname' => array(
            'default_value' => 'Course Outline',
            'label'         => 'Heading',
        ),
        // Config options: source homepage ID
        // repurpose the Source selector so that it doesn't show as a selectable.
        'courseHomepage' => array(
            'default_value' => '',
            'label'         => '',
        ),
    ),
);

Component_DefinitionMapper::getInstance()->create($definition);
