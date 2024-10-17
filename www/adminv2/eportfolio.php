<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */

use Schoolbox\Admin\Navigation\Subnav;

if (!$_SESSION['user']->isSuperuser()) {
    error(19, "", 401);
}

$this->setFile(TEMPLATE_content, 'adminv2/eportfolio.html');
$this->setTitle('Admin - ePortfolio Templates');

$this->setBlock(TEMPLATE_content, 'content', 'content_block');
$this->setBlock(TEMPLATE_content, 'empty', 'empty_block');

/* Group Selector */
$this->setBlock('content', 'template', 'template_block');
/* Layout */
$this->setBlock(TEMPLATE_content, 'layout', 'layout_block');
$this->setFile('layout', 'adminv2/component/layout.html');
$this->setBlock('layout', 'position_top', 'position_top_block');
$this->setBlock('layout', 'position_left', 'position_left_block');
$this->setBlock('layout', 'position_right', 'position_right_block');
/* Palette */
//$this->setBlock('content', 'component_page', 'component_page_block');
//$this->setBlock('component_page', 'component', 'component_block');
$this->setBlock('content', 'component', 'component_block');

// include the admin navigation
(new Subnav($this->getDi()->get('config'), $this->getDi()->get('authorizer'), $this->getDi()->get('user')))
    ->generatePhplib($this, '/adminv2/eportfolio')
    ->generateBreadcrumbsHtml($this, '/adminv2/eportfolio');

// Get list of templates
$eportfolioTemplateMapper = \Eportfolio\TemplateMapper::getInstance();
$templateList    = $eportfolioTemplateMapper->findAll(['order' => ['name' => 'asc']]);
if (count($templateList)) {
    $this->parse('content_block', 'content', true);
    $this->setVar('empty_block', '');
} else {
    $this->parse('empty_block', 'empty', true);
    $this->setVar('content_block', '');
    $this->setVar('layout_block', '');
    return;
}

foreach ($templateList as $template) {
    $this->setVar(
        [
            'template.id'   => $template->getId(),
            'template.name' => $template->getName(),
        ]
    );
    $this->parse('template_block', 'template', true);
}

// Output the components for this template
$componentFactory     = new Component_Eportfolio_Factory($this->getDi()->get('session')->get('user'), $this, true);
$occupiedPositionList = [];
$componentCounts      = [];
foreach ($componentFactory->buildComponentList($templateList->first()->getId()) as $component) {
    /** @var Component_ControllerAbstract $component */
    $instance_id   = $component->getInstance()->getId();
    $position_no   = $component->getInstance()->getPosition();
    $position      = "position_{$position_no}";
    $defintionId   = $component->getInstance()->getDefinition()->getId();
    $defintionName = $component->getInstance()->getDefinition()->getName();
    $componentCounts[$defintionId] = !isset($componentCounts[$defintionId]) ? 0 : $componentCounts[$defintionId]++;

    if (!$this->template->exists("{$position}_block")) {
        $this->setFile($position, 'adminv2/component/placeholder.html');
    }

    $component->setup();

    $this->setVar(array(
        'COMPONENT_INSTANCE_ID'     => $instance_id,
        'COMPONENT_HANDLE'          => $instance_id,
        'component.instance.id'     => $instance_id,
        'component.definition.id'   => $defintionId,
        'component.definition.name' => $defintionName,
    ));

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
$definitionCollection = Component_DefinitionMapper::getInstance()->findByColumnList(array(
    'module' => 'eportfolio',
        'visible' => 'true',
));
$this->setVar('component_block', '');
foreach ($definitionCollection as $definition) {
    /* @var $definition Component_Definition */
    $definitionId = $definition->getId();
    $count        = $componentCounts[$definitionId] ?? 0;
    $multiState   = '';
    // Failse
    if ($definition->getMultiple() === false) {
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
//    $this->parse('component_page_block', 'component_page', true);
}
