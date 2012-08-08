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

if ( !$group->isSuperuser() ) {
    error(19);
}

$this->setFile(TEMPLATE_content, 'adminv2/homepage/homepage.html');
$this->setTitle('Admin - Homepage Templates');

/* Group Selector */
$this->setBlock(TEMPLATE_content, 'template', 'template_block');

$templateHandler = new HomepageTemplate();
$templateList = $templateHandler->getAll();

if(is_array($templateList)) {
    foreach($templateList as $template) {
        $this->setVar(array(
            'template.id'   => $template['id'],
            'template.name' => $template['name']
        ));

        $this->parse('template_block', 'template', true);
    }
}

/* Component Palette */
$this->setBlock(TEMPLATE_content, 'definition', 'definition_block');

$definitionCollection = Component_DefinitionMapper::getInstance()->findByColumn('module', 'homepage');

foreach($definitionCollection as $definition) {
    $this->setVar(array(
        'definition.id'          => $definition->id,
        'definition.name'        => $definition->name,
        'definition.description' => $definition->description
    ));

    $this->parse('definition_block', 'definition', true);
}

/* Initial group placeholders */
$this->setBlock(TEMPLATE_content, 'layout', 'layout_block');
$this->setFile('layout', 'adminv2/homepage/layout.html');

$this->setBlock('layout', 'position_top', 'position_top_block');
$this->setBlock('layout', 'position_left', 'position_left_block');
$this->setBlock('layout', 'position_right', 'position_right_block');

$componentFactory = new Component_Dashboard_Factory(new User, $this);

$componentCollection = $componentFactory->buildComponentList($groupList[0]['id']);

$occupiedPositionList = array();

foreach($componentCollection as $component) {
    $position = "position_{$component->position}";

    if(!$this->template->exists("{$position}_block")) {
        $this->setFile($position, 'adminv2/homepage/placeholder.html');
    }

    $this->setVar(array(
        'component.instance.id'     => $component->instance->id,
        'component.definition.id'   => $component->definition->id,
        'component.definition.name' => $component->definition->name,
    ));

    $this->parse("{$position}_block", $position, true);

    if(!in_array($component->position, $occupiedPositionList)) {
        $occupiedPositionList[] = $component->position;
    }
}

$emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);

foreach($emptyPositionList as $emptyPosition) {
    $this->setVar("position_{$emptyPosition}_block", '');
}

$this->parse('layout_block', 'layout');
