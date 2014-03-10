<?php
/**
 * Creates new breadcrump component
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

require_once __DIR__.'/../../../include/Bootstrap.php';

$definition = array(
    'name'               => 'Breadcrumbs',
    'module'             => 'homepage',
    'component_category' => '5',
    'class'              => 'Component_Homepage_BreadcrumbController',
    'description'        => 'Display full path of parent folders about this folder',
    'multiple'           => 'false',
    'config'             => array('componentname' => array('default_value' => 'Breadcrumbs', 'label' => 'Heading'),
                                  'src'           => array('default_value' => '', 'label' => 'Source'),
                                 ),
);

Component_DefinitionMapper::getInstance()->create($definition);