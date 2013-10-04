<?php

require_once __DIR__.'/../../include/Bootstrap.php';

$component = array(
    'name'               => 'Subject List',
    'module'             => 'dashboard',
    'component_category' => '1',
    'class'              => 'Component_Dashboard_SubjectController',
    'description'        => 'Displays a template.',
    'multiple'           => 'false',
    'visible'            => 'true',
    'config'             => array()
);

Component_DefinitionMapper::getInstance()->create($component);