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

$this->setFile(TEMPLATE_content, 'adminv2/eportfolio.html');
$this->setTitle('Admin - ePortfolio Templates');

/* Group Selector */
$this->setBlock(TEMPLATE_content, 'template', 'template_block');

/* Empty default list of components
 */
$this->setVar('definition_block', '');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'eportfolio');

$templateHandler = new EportfolioTemplate();
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

$definitionCollection = Component_DefinitionMapper::getInstance()->findByColumnList(array(
    'module' => 'eportfolio',
    'visible' => 'true',
));

foreach($definitionCollection as $definition) {
    $this->setVar(array(
        'definition.id'          => $definition->id,
        'definition.name'        => $definition->name,
        'definition.description' => $definition->description,
        'definition.singleton'   => $definition->multiple == 'true' ? '' : 'single-instance'
    ));

    $this->parse('definition_block', 'definition', true);
}

/* Initial group placeholders */
$this->setBlock(TEMPLATE_content, 'layout', 'layout_block');
$this->setFile('layout', 'adminv2/component/layout.html');

$this->setBlock('layout', 'position_top', 'position_top_block');
$this->setBlock('layout', 'position_left', 'position_left_block');
$this->setBlock('layout', 'position_right', 'position_right_block');

$componentFactory = new Component_Eportfolio_Factory(new User, $this, true);

$cid = $templateList[0]['id'];
$componentCollection = $componentFactory->buildComponentList($cid);

$occupiedPositionList = array();

foreach($componentCollection as $component) {

    $position = "position_{$component->position}";

    if(!$this->template->exists("{$position}_block")) {
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


    $component->parseConfig();

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
