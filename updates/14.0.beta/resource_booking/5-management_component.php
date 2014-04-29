<?php

require_once __DIR__ . '/../../../include/Bootstrap.php';

// Only available to people that have the resource management module enabled.
$component = array(
    'name'               => 'Resource Management',
    'module'             => 'dashboard',
    // Category - doesn't matter for dashboard
    'component_category' => '1',
    'class'              => 'Schoolbox\\ResourceBooking\\Booking\\Component\\ResourceManagement',
    'description'        => 'Resource Management Component',
    'multiple'           => 'false',
    'visible'            => 'false',
    'config'             => array(
        'componentname' => array(
            'default_value' => 'Resource Management',
            'label'         => 'Heading'
        ),
        'days' => array(
            'default_value' => '0',
            'label'         => 'days'
        ),
    )
);

Component_DefinitionMapper::getInstance()->create($component);
