<?php
/**
 * Creates grid component.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../../include/Bootstrap.php';

$component = array(
    'name'               => 'Grid',
    'module'             => 'homepage',
    'component_category' => '1',
    'class'              => 'Component_Homepage_GridController',
    'description'        => 'Shows a grid of tiles',
    'multiple'           => 'true',
    'visible'            => 'true',
    'config'             => array(
        'componentname'  => array(
            'default_value' => 'Grid',
            'label'         => 'Heading',
        ),
    )
);

Component_DefinitionMapper::getInstance()->create($component);
