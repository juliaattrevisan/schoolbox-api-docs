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
    'name'               => 'Course Instance',
    'module'             => 'homepage',
    'component_category' => '3',
    'class'              => 'Component_Homepage_CourseInstanceController',
    'description'        => 'Adds a course to a class page. Once you add this component, you will be able to select a course - any tasks in that course will be imported into this class.',
    'multiple'           => 'true',
    'config'             => array(
        'componentname' => array(
            'default_value' => 'Course',
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
