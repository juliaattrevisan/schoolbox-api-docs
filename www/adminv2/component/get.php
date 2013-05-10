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

$this->setLite(true);

$context = $this->arg[0];
$groupId = $this->arg[1];

if(empty($context) || empty($groupId)) {
    httpError(400, 2); // info missing
}

$this->setFile(TEMPLATE_body, 'adminv2/component/layout.html');

$this->setBlock(TEMPLATE_body, 'position_top', 'position_top_block');
$this->setBlock(TEMPLATE_body, 'position_left', 'position_left_block');
$this->setBlock(TEMPLATE_body, 'position_right', 'position_right_block');

$componentFactory = buildComponentFactory($context, new User(), $this);

if(!$componentFactory) {
    httpError(400, 'Invalid context specified.');
}

$componentCollection = $componentFactory->buildComponentList($groupId);

$occupiedPositionList = array();

foreach($componentCollection as $component) {

    $position = "position_{$component->position}";

    if ( !$this->template->exists("{$position}_block") ) {
        $this->setFile($position, 'adminv2/component/placeholder.html');
    }

    $component->setup(array('id' => '-1', 'code' => '', 'name' => ''), array());
    $component->execute();

    $this->setVar(array(
        'COMPONENT_INSTANCE_ID'     => $component->instance->id,
        'COMPONENT_HANDLE'          => $component->instance->id,
        'component.instance.id'     => $component->instance->id,
        'component.definition.id'   => $component->definition->id,
        'component.definition.name' => $component->definition->name,
    ));


    $component->parseConfigForTemplate();

    $this->parse("{$position}_block", $position, true);

    if ( !in_array($component->position, $occupiedPositionList) ) {
        $occupiedPositionList[] = $component->position;
    }
}

$emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);

foreach ( $emptyPositionList as $emptyPosition ) {
    $this->setVar("position_{$emptyPosition}_block", '');
}


/**
 * Factory function to produce...uh, a Factory -_-
 * @param $context
 * @param User $user
 * @param SitePage $sitepage
 * @return Component_FactoryAbstract|null
 */
function buildComponentFactory($context, User $user, SitePage $sitepage) {
    $contextMap = array(
        'dashboard' => false,
        'homepage' => true,
        'eportfolio' => true
    );

    if(isset($contextMap[$context])) {
        $class = sprintf('Component_%s_Factory', ucfirst(strtolower($context)));

        if($contextMap[$context]) {
            return new $class($user, $sitepage, true);
        }
        else {
            return new $class($user, $sitepage);
        }
    }
    return null;
}