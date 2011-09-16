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
    'instanceId' => 8,
    'position' => 'top',
    'sequence' => 1
);*/

/* Simple Validation */
foreach ( array('instanceId', 'position', 'sequence') as $field ) {
    if ( empty($_POST[$field]) ) {
        exit;
    }
}

if ( $instance = Component_InstanceMapper::getInstance()->findById($_POST['instanceId']) ) {
    $instance->position = $_POST['position'];
    $instance->sequence = $_POST['sequence'];

    Component_InstanceMapper::getInstance()->save($instance);
}