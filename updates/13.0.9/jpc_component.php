<?php

require_once __DIR__.'/../../include/Bootstrap.php';

if (Config::get('site_location') === 'jpc') {
    $component = array(
        'name'               => 'Template',
        'module'             => 'homepage',
        'component_category' => '1',
        'class'              => 'Component_Homepage_BlankController',
        'description'        => 'Displays a template.',
        'multiple'           => 'true',
        'visible'            => 'false',
        'config'             => array(
            'template'        => array(
                'default_value' => 'library/header',
                'label'         => 'Template'
            ),
        )
    );

    Component_DefinitionMapper::getInstance()->create($component);
}
