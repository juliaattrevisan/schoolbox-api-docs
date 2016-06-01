<?php
/**
 * Creates St Michael's Awards Component.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../../library/Bootstrap.php';

$component = array(
    'name'               => 'Student Awards',
    'module'             => 'dashboard',
    'component_category' => '1',
    'class'              => 'Component_Dashboard_StMichaelsAwardsController',
    'description'        => 'Shows awards, detentions and absences for your students',
    'multiple'           => 'false',
    'visible'            => 'true',
    'config'             => array(
        'componentname'  => array(
            'default_value' => 'Awards',
            'label'         => 'Heading'
        ),
    )
);

Component_DefinitionMapper::getInstance()->create($component);
