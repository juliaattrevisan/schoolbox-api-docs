<?php
/**
 * Creates Pinterest component
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once __DIR__ . '/../../../include/Bootstrap.php';

$definition = array(
    'name' => 'Pinterest',
    'module' => 'homepage',
    'component_category' => 6,
    'class' => 'Component_Homepage_PinterestController',
    'description' => 'A Pinterest Board',
    'multiple' => 'true',
    'config' => array(
        'componentname' => array(
            'default_value' => 'Pinterest',
            'label' => 'Heading',
        ),
        'url' => array(
            'default_value' => '',
            'label' => 'Board URL',
        ),
    ),
);

Component_DefinitionMapper::getInstance()->create($definition);
