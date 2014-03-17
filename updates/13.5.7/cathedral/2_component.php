<?php

require_once __DIR__.'/../../../include/Bootstrap.php';

if (Config::get('site_location') === 'cathedral') {
    $component = array(
        'name'               => 'Daily Quote',
        'module'             => 'dashboard',
        'component_category' => '1',
        'class'              => 'Component_Dashboard_DailyQuoteController',
        'description'        => 'A quote that appears daily',
        'multiple'           => 'false',
        'visible'            => 'true',
        'config'             => array(
            'componentname'  => array(
                'default_value' => 'Daily Quote',
                'label'         => 'Heading'
            ),
        )
    );

    Component_DefinitionMapper::getInstance()->create($component);
}
