<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */

/** @var SitePage $this */
if (!$this->getDi()->get('session')->get('user')->isSuperuser()) {
    error(19);
}

/** @var SitePage $this */
$this->setFile(TEMPLATE_content, 'adminv2/dashboard.html');
$this->setTitle('Admin - Dashboard');

/* Group Selector */
$this->setBlock(TEMPLATE_content, 'group', 'group_block');
/* Layout */
$this->setBlock(TEMPLATE_content, 'layout', 'layout_block');
$this->setFile('layout', 'adminv2/component/layout.html');
$this->setBlock('layout', 'position_top', 'position_top_block');
$this->setBlock('layout', 'position_left', 'position_left_block');
$this->setBlock('layout', 'position_right', 'position_right_block');
/* Palette */
$this->setBlock(TEMPLATE_content, 'component', 'component_block');

// include the admin navigation
$nav = new AdminV2_Nav($this->getDi()->get('config'));
$nav->parse($this, 'dashboard');

// Get list of roles
$userHandle = new User;
$groupList = $userHandle->getGroupList();

if (is_array($groupList)) {
    foreach ($groupList as $group) {
        $this->setVar(array(
            'group.id'   => $group['id'],
            'group.name' => Scrub::text($group['name'])
        ));
        $this->parse('group_block', 'group', true);
    }
}

// Output the components for this group
$componentFactory     = new Component_Dashboard_Factory($this->getDi()->get('session')->get('user'), $this);
$occupiedPositionList = [];
$componentCounts      = [];
foreach ($componentFactory->buildComponentList($groupList[0]['id']) as $component) {
    /** @var Component_ControllerAbstract $component */
    $instance_id   = $component->getInstance()->getId();
    $position_no   = $component->getInstance()->getPosition();
    $position      = "position_{$position_no}";
    $defintionId   = $component->getInstance()->getDefinition()->getId();
    $defintionName = $component->getInstance()->getDefinition()->getName();
    $componentCounts[$defintionId]++;

    if (!$this->template->exists("{$position}_block")) {
        $this->setFile($position, 'adminv2/component/placeholder.html');
    }

    $this->setVar(array(
        'ID'                        => '',
        'COMPONENT_INSTANCE_ID'     => $instance_id,
        'COMPONENT_HANDLE'          => $instance_id,
        'COMPONENT_DEFINITION_ID'   => $defintionId,
        'component.instance.id'     => $instance_id,
        'component.definition.id'   => $defintionId,
        'component.definition.name' => Scrub::text($defintionName),
    ));

    $component->setup();
    $component->setConfigurationTemplate('component/config/_templatewrapper.html');
    $component->parseConfigForTemplate();

    $this->parse("{$position}_block", $position, true);

    if (!in_array($position_no, $occupiedPositionList)) {
        $occupiedPositionList[] = $position_no;
    }
}

// Empty positions
$emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);
foreach ($emptyPositionList as $emptyPosition) {
    $this->setVar("position_{$emptyPosition}_block", '');
}
$this->parse('layout_block', 'layout');


// Output the palette
$this->setVar('component_block', '');
foreach ($componentFactory->getDefinitionList('component_category') as $definition) {
    /* @var $definition Component_Definition */
    $definitionId = $definition->getId();
    $count        = $componentCounts[$definitionId] ?: 0;
    $multiState   = '';
    // Failse
    if ($definition->getMultiple() == 'false') {
        $multiState .= 'one-only';
        if ($count >= 1) {
            $multiState .= ' disabled';
        }
    }
    $this->setVar([
        'COMPONENT_ID'             => $definitionId,
        'COMPONENT_DESCRIPTION'    => $definition->getDescription(),
        'COMPONENT_CLASS'          => $definition->getCssClass(),
        'COMPONENT_MULTIPLE_STATE' => $multiState,
        'COMPONENT_NAME'           => $definition->getName(),
        'COMPONENT_CATEGORY_NAME'  => $definition->getComponentCategoryName(),
        'COMPONENT_COUNT'          => $count,
    ]);
    $this->parse('component_block', 'component', true);
}
