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
    'name'               => 'Tiles',
    'module'             => 'homepage',
    'component_category' => '1',
    'class'              => 'Schoolbox\\Tile\\Component\\HomepageTileController',
    'description'        => 'Shows a list of tiles, arranged in either a fixed-width grid or spanned across a number of columns.',
    'multiple'           => 'true',
    'visible'            => 'true',
    'config'             => array(
        'componentname' => array(
            'default_value' => 'Tiles',
            'label'         => 'Heading',
        ),
        'layout' => array(
            'default_value' => 0,
            'label'         => 'Layout ID',
        ),
    )
);

Component_DefinitionMapper::getInstance()->create($component);
