<?php

require_once __DIR__.'/../../include/Bootstrap.php';

if (Config::get('site_location') === 'bialik') {
    $component = array(
        'name'               => 'Library Search',
        'module'             => 'homepage',
        'component_category' => '5',
        'class'              => 'Component_Homepage_BlankController',
        'description'        => 'Library Search',
        'multiple'           => 'false',
        'visible'            => 'false',
        'config'             => array(
            'componentname'  => array(
                'default_value' => 'Library Search',
                'label'         => 'Heading'
            ),
        )
    );

    Component_DefinitionMapper::getInstance()->create($component);
}
