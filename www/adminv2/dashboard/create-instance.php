<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

$this->setAjax(true);

/*$_POST = array(
    'groupId' => '1',
    'definitionId' => 2,
    'position' => 'top',
    'sequence' => 1
);*/

/* Simple Validation */
foreach ( array('groupId', 'definitionId', 'position', 'sequence') as $field ) {
    if ( empty($_POST[$field]) ) {
        exit;
    }
}

if ( $definition = Component_DefinitionMapper::getInstance()->findById($_POST['definitionId']) ) {
    $instance = new Component_Instance();

    $instance->definitionId = $definition->id;
    $instance->module       = 'dashboard';
    $instance->identifier   = $_POST['groupId'];
    $instance->position     = $_POST['position'];
    $instance->sequence     = $_POST['sequence'];

    Component_InstanceMapper::getInstance()->save($instance);
}
