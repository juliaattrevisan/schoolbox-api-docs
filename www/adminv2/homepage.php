<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */

/** @var SitePage $this */

use Schoolbox\Admin\Navigation\Subnav;


if (!$this->getDi()->get('session')->get('user')->isSuperuser()) {
    error(19, "", 401);
}

$this->setFile(TEMPLATE_content, 'adminv2/homepage.html');
$this->setTitle('Admin - Homepage Templates');

/* Group Selector */
$this->setBlock(TEMPLATE_content, 'template', 'template_block');
/* Layout */
$this->setBlock(TEMPLATE_content, 'layout', 'layout_block');
$this->setFile('layout', 'adminv2/component/layout.html');
$this->setBlock('layout', 'position_top', 'position_top_block');
$this->setBlock('layout', 'position_left', 'position_left_block');
$this->setBlock('layout', 'position_right', 'position_right_block');

$this->setBlock(TEMPLATE_content, 'content', 'content_block');
$this->setBlock(TEMPLATE_content, 'empty', 'empty_block');

/* Palette */
$this->setBlock('content', 'component_tab', 'component_tab_block');
$this->setBlock('content', 'component_page', 'component_page_block');
$this->setBlock('component_page', 'component', 'component_block');

// include the admin navigation
(new Subnav($this->getDi()->get('config'), $this->getDi()->get('authorizer'), $this->getDi()->get('user')))
    ->generatePhplib($this, '/adminv2/homepage')
    ->generateBreadcrumbsHtml($this, '/adminv2/homepage');

// Get list of templates
$homepageTemplateMapper = new \Schoolbox\Resource\HomepageTemplateMapper();
$homepageTemplateList = $homepageTemplateMapper->findAll(['order' => ['name' => 'asc']]);

if ($homepageTemplateList->count() > 0) {
    $this->parse('content_block', 'content', true);
    $this->setVar('empty_block', '');
} else {
    $this->parse('empty_block', 'empty', true);
    $this->setVar('content_block', '');
    $this->setVar('layout_block', '');
    return;
}

foreach ($homepageTemplateList as $currentTemplate) {
    $this->setVar(array(
        'template.id'   => $currentTemplate->getId(),
        'template.name' => $currentTemplate->getName()
    ));
    $this->parse('template_block', 'template', true);
}

// Output the components for this template
$componentFactory = new Component_Homepage_Factory($this->getDi()->get('session')->get('user'), $this, true);
$cid = null;
$firstTemplate = $homepageTemplateList->first();
if ($firstTemplate instanceof \Schoolbox\Resource\HomepageTemplate) {
    $cid = $homepageTemplateList->first()->getId();
}
$occupiedPositionList = [];
$componentCounts      = [];
foreach ($componentFactory->buildComponentList($cid) as $component) {
    /** @var Component_Homepage_ControllerAbstract $component */
    $instance_id   = $component->getInstance()->getId();
    $position_no   = $component->getInstance()->getPosition();
    $position      = "position_{$position_no}";
    $defintionId   = $component->getInstance()->getDefinition()->getId();
    $defintionName = $component->getInstance()->getDefinition()->getName();
    $componentCounts[$defintionId]  = !isset($componentCounts[$defintionId]) ? 0 : $componentCounts[$defintionId]++;

    if (!$this->template->exists("{$position}_block")) {
        $this->setFile($position, 'adminv2/component/placeholder.html');
    }

    $component->setup();

    $this->setVar(array(
        'COMPONENT_INSTANCE_ID'     => $instance_id,
        'COMPONENT_HANDLE'          => $instance_id,
        'component.instance.id'     => $instance_id,
        'component.definition.id'   => $defintionId,
        'component.definition.name' => Scrub::text($defintionName),
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


// Output the palette (grouped by category)
$componentFactory  = new Component_Homepage_Factory(new \Schoolbox\User(), $this);
$groupedCollection = [];
foreach ($componentFactory->getDefinitionList('component_category') as $definition) {
    $groupedCollection[$definition->getComponentCategory()][] = $definition;
}

$this->setVar([
    'component_tab_block'  => '',
    'component_page_block' => '',
]);
$firstCategory = true;
foreach ($groupedCollection as $category => $categoryDefinitions) {
    $firstComponent = true;
    foreach ($categoryDefinitions as $definition) {
        /* @var $definition Component_Definition */
        $definitionId = $definition->getId();
        $count        = $componentCounts[$definitionId] ?? 0;
        if ($firstComponent) {
            // First component in category: parse tab
            $this->setVar([
                'COMPONENT_CATEGORY_CLASS' => $firstCategory ? 'active' : '',
                'COMPONENT_CATEGORY_ID'    => $category,
                'COMPONENT_CATEGORY_NAME'  => $definition->getComponentCategoryName(),
                'COMPONENT_CATEGORY_COUNT' => count($categoryDefinitions),
                'component_block'          => '',
            ]);
            $this->parse('component_tab_block', 'component_tab', true);
            $firstComponent = false;
            $firstCategory  = false;
        }

        $multiState = '';
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
    }
    $this->parse('component_page_block', 'component_page', true);
}
