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

$cim = Component_InstanceMapper::getInstance();
$cdm = Component_DefinitionMapper::getInstance();

if ( !empty($_POST['instanceId']) ) {
    $instance = $cim->findById($_POST['instanceId']);
}
else {
    $instance = new Component_Instance();
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

if ($definition = $cdm->findById($_POST['definitionId'])) {
    /* @var $definition Component_Definition */
    $instance
        ->setDefinitionId($definition->getId())
        ->setModule($context)
        ->setIdentifier($_POST['targetId'])
        ->setPosition($_POST['position'])
        ->setSequence($_POST['sequence']);

    // it's an array, but there will only be 1
    $ids = $cim->save($instance);
    $cim->resequenceComponents($context, $_POST['targetId'], $ids[0]);

    header('Content-type: application/json');
    echo json_encode($instance);
}
