<?php

require_once __DIR__.'/../../../include/Bootstrap.php';

if (Config::get('site_location') === 'cathedral') {
    $component = array(
        'name'               => 'Daily Quote',
        'module'             => 'homepage',
        'component_category' => '1',
        'class'              => 'Component_Homepage_DailyQuoteController',
        'description'        => 'The Daily Quote',
        'multiple'           => 'false',
        'visible'            => 'false',
        'config'             => array(
            'componentname'  => array(
                'default_value' => 'Daily Quote',
                'label'         => 'Heading'
            ),
        )
    );

    Component_DefinitionMapper::getInstance()->create($component);
}
