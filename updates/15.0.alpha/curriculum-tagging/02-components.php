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
    'name'               => 'Curriculum Information',
    'module'             => 'homepage',
    'component_category' => '3', // Learning
    'class'              => 'Schoolbox\\Curriculum\\Component\\CurriculumInformationController',
    'description'        => 'Shows curriculum information',
    'multiple'           => 'true',
    'visible'            => 'true',
    'config'             => array(
        'componentname' => array(
            'default_value' => 'Curriculum Information',
            'label'         => 'Heading',
        ),
    )
);

Component_DefinitionMapper::getInstance()->create($component);
