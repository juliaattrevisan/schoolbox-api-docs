<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
// require SitePage context
if(!isset($this)) {
    require_once('../../../include/Bootstrap.php');
    httpError(400, 101); // resource not available
}

$this->setAjax(true);
$this->setLite(true);

/* Simple Validation */
if ( empty($_POST['instanceId']) ) {
    httpError('400', 2);
}

try {
	$instance = Component_InstanceMapper::getInstance()->findById($_POST['instanceId']);
	Component_InstanceMapper::getInstance()->delete($instance);
	echo 'Component instance removed.';
}
catch (Schoolbox\Mapper\Exception $e) {}
