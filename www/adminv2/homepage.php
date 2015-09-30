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

$this->setFile(TEMPLATE_content, 'adminv2/homepage.html');
$this->setTitle('Admin - Homepage Templates');

/* Group Selector */
$this->setBlock(TEMPLATE_content, 'template', 'template_block');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'homepage');


$homepageTemplateMapper = new \Schoolbox\Resource\HomepageTemplateMapper();
$homepageTemplateList = $homepageTemplateMapper->findAll();

foreach ($homepageTemplateList as $currentTemplate) {
    $this->setVar(array(
            'template.id'   => $currentTemplate->getId(),
            'template.name' => $currentTemplate->getName()
        ));

    $this->parse('template_block', 'template', true);
}

/* Component Palette */
//$this->setBlock(TEMPLATE_content, 'definition', 'definition_block');
$this->setBlock(TEMPLATE_content, 'component_tab', 'component_tab_block');
$this->setBlock(TEMPLATE_content, 'component_page', 'component_page_block');
$this->setBlock('component_page', 'component', 'component_block');

$componentFactory     = new Component_Homepage_Factory(new \Schoolbox\User(), $this);
$definitionCollection = $componentFactory->getDefinitionList('component_category');

// Group by category
$groupedCollection = [];
foreach ($definitionCollection as $definition) {
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

//        $this->setVar(array(
//            'definition.id'          => $definition->getId(),
//            'definition.name'        => Scrub::text($definition->getName()),
//            // description deliberately not scrubbed
//            'definition.description' => $definition->getDescription(),
//            'definition.singleton'   => $definition->getMultiple() == 'true' ? '' : 'single-instance'
//        ));
//
//        $this->parse('definition_block', 'definition', true);
    }
    $this->parse('component_page_block', 'component_page', true);
}

/* Initial group placeholders */
$this->setBlock(TEMPLATE_content, 'layout', 'layout_block');
$this->setFile('layout', 'adminv2/component/layout.html');

$this->setBlock('layout', 'position_top', 'position_top_block');
$this->setBlock('layout', 'position_left', 'position_left_block');
$this->setBlock('layout', 'position_right', 'position_right_block');

$componentFactory = new Component_Homepage_Factory(new \Schoolbox\User(), $this, true);

$cid = null;
$firstTemplate = $homepageTemplateList->first();
if ($firstTemplate instanceof \Schoolbox\Resource\HomepageTemplate) {
    $cid = $homepageTemplateList->first()->getId();
}
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

    $component->setup(
        new Schoolbox\Resource\Folder\Model(),
        $component->getInstance()->getConfigArray()
    );

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
