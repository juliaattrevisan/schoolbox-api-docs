<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

$group = new Group();

if (!$group->isSuperuser()) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/dashboard.html');
$this->setTitle('Admin - Dashboard');

/* Group Selector */
$this->setBlock(TEMPLATE_content, 'group', 'group_block');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'dashboard');


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

/* Component Palette */
$this->setBlock(TEMPLATE_content, 'component', 'component_block');

$componentFactory     = new Component_Dashboard_Factory(new \Schoolbox\User(), $this);
$definitionCollection = $componentFactory->getDefinitionList('component_category');

$this->setVar('component_block', '');
$firstCategory = true;
foreach ($definitionCollection as $definition) {
    /* @var $definition Component_Definition */
    $this->setVar([
        'COMPONENT_ID'             => $definition->getId(),
        'COMPONENT_DESCRIPTION'    => $definition->getDescription(),
        'COMPONENT_CLASS'          => str_replace('\\', '_', $definition->getClass()),
        'COMPONENT_MULTIPLE_STATE' => $definition->getMultiple() ? 'active' : '',
        'COMPONENT_NAME'           => $definition->getName(),
        'COMPONENT_CATEGORY_NAME'  => $definition->getComponentCategoryName(),
        // Not known yet
        'COMPONENT_COUNT'          => 0,
    ]);
    $this->parse('component_block', 'component', true);
}

/* Initial group placeholders */
$this->setBlock(TEMPLATE_content, 'layout', 'layout_block');
$this->setFile('layout', 'adminv2/component/layout.html');

$this->setBlock('layout', 'position_top', 'position_top_block');
$this->setBlock('layout', 'position_left', 'position_left_block');
$this->setBlock('layout', 'position_right', 'position_right_block');

$componentFactory = new Component_Dashboard_Factory($_SESSION['user'], $this);
$componentCollection = $componentFactory->buildComponentList($groupList[0]['id']);

$occupiedPositionList = array();

foreach ($componentCollection as $component) {
    /** @var Component_ControllerAbstract $component */
    $instance_id = $component->getInstance()->getId();
    $position_no = $component->getInstance()->getPosition();
    $position    = "position_{$position_no}";

    if (!$this->template->exists("{$position}_block")) {
        $this->setFile($position, 'adminv2/component/placeholder.html');
    }

    $this->setVar(array(
        'COMPONENT_INSTANCE_ID'     => $instance_id,
        'COMPONENT_HANDLE'          => $instance_id,
        'component.instance.id'     => $instance_id,
        'component.definition.id'   => $component->getInstance()->getDefinition()->getId(),
        'component.definition.name' => Scrub::text($component->getInstance()->getDefinition()->getName()),
    ));

    $component->setup(
        array(),
        $component->getInstance()->getConfigArray()
    );
    $component->setConfigurationTemplate('component/config/_templatewrapper.html');
    $component->parseConfigForTemplate();

    $this->parse("{$position}_block", $position, true);

    if (!in_array($position_no, $occupiedPositionList)) {
        $occupiedPositionList[] = $position_no;
    }
}

$emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);

foreach ($emptyPositionList as $emptyPosition) {
    $this->setVar("position_{$emptyPosition}_block", '');
}

$this->parse('layout_block', 'layout');
