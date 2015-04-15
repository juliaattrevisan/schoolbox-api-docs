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
    'name'               => 'Curriculum Overview',
    'module'             => 'homepage',
    'component_category' => '3', // Learning
    'class'              => 'Schoolbox\\Curriculum\\Component\\CurriculumOverviewController',
    'description'        => 'Shows an overview of a curriculum code that has been attached to this homepage, as well as any sub-codes that this code might have.',
    'multiple'           => 'true',
    'visible'            => 'false',
    'config'             => array(
        'collapse' => array(
            'default_value' => 0,
            'label'         => 'Collapse Content',
        ),
        'componentname' => array(
            'default_value' => 'Curriculum Overview ',
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
