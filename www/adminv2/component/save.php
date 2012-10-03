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
foreach ( array('context', 'targetId', 'definitionId', 'position', 'sequence') as $field ) {
    if ( empty($_POST[$field]) ) {
        // necessary information is lacking, and silent death is not preferred
        httpError(400, 2);
    }
}

if ( !empty($_POST['instanceId']) ) {
    $instance = Component_InstanceMapper::getInstance()->findById($_POST['instanceId']);
}
else {
    $instance = new Component_Instance;
}

$contextMap = array(
    'dashboard' => 'dashboard',
    'homepage' => 'homepage_template',
    'eportfolio' => 'eportfolio_template'
);

if(!isset($contextMap[$_POST['context']])) {
    // necessary information is lacking, and silent death is not preferred
    httpError(400, 2);
}
$context = $contextMap[$_POST['context']];

if ( $definition = Component_DefinitionMapper::getInstance()->findById($_POST['definitionId']) ) {
    $instance->definitionId = $definition->id;
    $instance->module       = $context;
    $instance->identifier   = $_POST['targetId'];
    $instance->position     = $_POST['position'];
    $instance->sequence     = $_POST['sequence'];

    Component_InstanceMapper::getInstance()->save($instance);

    header('Content-type: application/json');
    echo json_encode($instance);
}
