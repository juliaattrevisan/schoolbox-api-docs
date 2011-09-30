<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

$groupId = $this->arg[0];

if ( empty($groupId) ) {
    exit;
}

$this->setLite(true);

$this->setFile(TEMPLATE_body, 'adminv2/dashboard/layout.html');

$this->setBlock(TEMPLATE_body, 'position_top', 'position_top_block');
$this->setBlock(TEMPLATE_body, 'position_left', 'position_left_block');
$this->setBlock(TEMPLATE_body, 'position_right', 'position_right_block');

$componentFactory = new Component_Dashboard_Factory(new User, $this);

$componentCollection = $componentFactory->buildComponentList($groupId);

$occupiedPositionList = array();
foreach($componentCollection as $component) {
    $position = "position_{$component->position}";

    if ( !$this->template->exists("{$position}_block") ) {
        $this->setFile($position, 'adminv2/dashboard/placeholder.html');
    }

    $this->setVar(array(
        'component.instance.id'     => $component->instance->id,
        'component.definition.id'   => $component->definition->id,
        'component.definition.name' => $component->definition->name,
    ));

    $this->parse("{$position}_block", $position, true);

    if ( !in_array($component->position, $occupiedPositionList) ) {
        $occupiedPositionList[] = $component->position;
    }
}

$emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);

foreach ( $emptyPositionList as $emptyPosition ) {
    $this->setVar("position_{$emptyPosition}_block", '');
}