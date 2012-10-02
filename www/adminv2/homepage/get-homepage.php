<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

$templateId = $this->arg[0];

if ( empty($templateId) ) {
    exit;
}

$this->setLite(true);

$this->setFile(TEMPLATE_body, 'adminv2/homepage/layout.html');

$this->setBlock(TEMPLATE_body, 'position_top', 'position_top_block');
$this->setBlock(TEMPLATE_body, 'position_left', 'position_left_block');
$this->setBlock(TEMPLATE_body, 'position_right', 'position_right_block');

$componentFactory = new Component_Homepage_Factory(new User, $this, true);

$componentCollection = $componentFactory->buildComponentList($templateId);

$occupiedPositionList = array();
foreach($componentCollection as $component) {
    $position = "position_{$component->position}";

    if ( !$this->template->exists("{$position}_block") ) {
        $this->setFile($position, 'adminv2/homepage/placeholder.html');
    }

    $component->execute();

    $this->setVar(array(
        'COMPONENT_INSTANCE_ID'     => $component->instance->id,
        'component.instance.id'     => $component->instance->id,
        'component.definition.id'   => $component->definition->id,
        'component.definition.name' => $component->definition->name,
    ));

    $component->setup(array(), array());
    $component->parseConfig();

    $this->parse("{$position}_block", $position, true);

    if ( !in_array($component->position, $occupiedPositionList) ) {
        $occupiedPositionList[] = $component->position;
    }
}

$emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);

foreach ( $emptyPositionList as $emptyPosition ) {
    $this->setVar("position_{$emptyPosition}_block", '');
}
