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

if (is_array($templateList)) {
    foreach ($templateList as $template) {
        $this->setVar(array(
            'template.id'   => $template['id'],
            'template.name' => $template['name']
        ));

        $this->parse('template_block', 'template', true);
    }
}

/* Component Palette */
$this->setBlock(TEMPLATE_content, 'component', 'component_block');

$definitionCollection = Component_DefinitionMapper::getInstance()->findByColumnList(array(
    'module' => 'eportfolio',
    'visible' => 'true',
));

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

$componentFactory = new Component_Eportfolio_Factory(new \Schoolbox\User(), $this, true);

$cid = $templateList[0]['id'];
$componentCollection = $componentFactory->buildComponentList($cid);

$occupiedPositionList = array();

foreach ($componentCollection as $component) {
    /** @var Component_ControllerAbstract $component */
    $instance_id = $component->getInstance()->getId();
    $position_no = $component->getInstance()->getPosition();
    $position    = "position_{$position_no}";

    if (!$this->template->exists("{$position}_block")) {
        $this->setFile($position, 'adminv2/component/placeholder.html');
    }

    $component->setup(null, array());

    $this->setVar(array(
        'COMPONENT_INSTANCE_ID'     => $instance_id,
        'COMPONENT_HANDLE'          => $instance_id,
        'component.instance.id'     => $instance_id,
        'component.definition.id'   => $component->getInstance()->getDefinition()->getId(),
        'component.definition.name' => Scrub::text($component->getInstance()->getDefinition()->getName()),
    ));

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
