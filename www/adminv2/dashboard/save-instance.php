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

/* Simple Validation */
foreach ( array('groupId', 'definitionId', 'position', 'sequence') as $field ) {
    if ( empty($_POST[$field]) ) {
        exit;
    }
}

if ( !empty($_POST['instanceId']) ) {
    $instance = Component_InstanceMapper::getInstance()->findById($_POST['instanceId']);
}
else {
    $instance = new Component_Instance;
}

if ( $definition = Component_DefinitionMapper::getInstance()->findById($_POST['definitionId']) ) {

    $instance->definitionId = $definition->id;
    $instance->module       = 'dashboard';
    $instance->identifier   = $_POST['groupId'];
    $instance->position     = $_POST['position'];
    $instance->sequence     = $_POST['sequence'];

    Component_InstanceMapper::getInstance()->save($instance);

    header('Content-type: application/json');
    echo json_encode($instance);
}
