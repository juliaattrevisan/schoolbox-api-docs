<?php
/**
 * Creates grid component for dashboard
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__ . '/../../../include/Bootstrap.php';

$component = array(
    'name'               => 'Tiles',
    'module'             => 'dashboard',
    'component_category' => '1',
    'class'              => 'Schoolbox\\Tile\\Component\\DashboardTileController',
    'description'        => 'Shows a list of tiles, arranged in either a fixed-width grid or spanned across a number of columns.',
    'multiple'           => 'true',
    'visible'            => 'true',
    'config'             => array(
        'layoutId' => array(
            'default_value' => 0,
            'label'         => 'Layout',
        ),
    )
);

Component_DefinitionMapper::getInstance()->create($component);
