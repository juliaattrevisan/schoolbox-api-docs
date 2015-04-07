<?php
/**
 * Creates curriculum component.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../../include/Bootstrap.php';

$component = array(
    'name'               => 'Curriculum Details',
    'module'             => 'homepage',
    'component_category' => '3', // Learning
    'class'              => 'Schoolbox\\Curriculum\\Component\\CurriculumDetailsController',
    'description'        => 'Shows the full details of a curriculum code that has been attached to this homepage',
    'multiple'           => 'true',
    'visible'            => 'false',
    'config'             => array(
        'componentname' => array(
            'default_value' => 'Curriculum Details',
            'label'         => 'Heading',
        ),
        // Select a curriculum code from the ones on the page.
        'curriculumCode' => array(
            'default_value' => '',
            'label'         => 'Curriculum Code',
        ),
    )
);

Component_DefinitionMapper::getInstance()->create($component);
