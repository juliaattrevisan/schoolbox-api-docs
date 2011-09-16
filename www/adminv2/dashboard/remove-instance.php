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
);*/

/* Simple Validation */
if ( empty($_POST['instanceId']) ) {
    exit;
}

if ( $instance = Component_InstanceMapper::getInstance()->findById($_POST['instanceId']) ) {
    Component_InstanceMapper::getInstance()->delete($instance);
}