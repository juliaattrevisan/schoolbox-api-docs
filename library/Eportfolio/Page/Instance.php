<?php
/**
 * Eportfolio_Page
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Eportfolio_Page_Instance extends Eportfolio_Page_Abstract
{
    /**
	 * The template file to use
	 *
	 * @var string
	 */
    protected $templateFile = 'eportfolio/instance.html';

    /**
	 * An instance of Component_FactoryAbstract
	 *
	 * @var Component_FactoryAbstract
	 */
    private $componentFactory = null;

    /**
	 * An instance of Collection
	 *
	 * @var null
	 */
    private $componentCollection = null;

    /**
	 * A list of colours to use
	 *
	 * @var null
	 */
    private $colours = null;

    /**
	 * A list of component tab categories
	 *
	 * @var array
	 */
    private $tabList = array();

    /**
	 * Set up classes and collections beyond the basic norms
	 */
    protected function initialise()
    {
        $this->validatePageTemplate();

        /* Assemble list of components
		 */
        $this->setComponentFactory(new Component_Eportfolio_Factory($this->getUserHandler(), $this->getPage()));
        $this->setComponentCollection($this->getComponentFactory()->buildComponentList($this->getPageId()));

        /* Page colours
		 */
        $this->setColours($this->getEportfolioHandler()->getColorList());
    }

    /**
	 * Set up blocks and stuff
	 */
    protected function setup()
    {
        $this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
        $this->getPage()->setTab('ePortfolio');

        /* Set up blocks and stuff
		 */
        $this->getPage()->setTitle($this->getEportfolioHandler()->getSettingsPageTitle());
        $this->getPage()->setBlock('custom_head', 'modify_layout', 'modify_layout_block');
        $this->getPage()->setBlock('custom_head', 'allowed_files', 'allowed_files_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'edit_page', 'edit_page_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'customise_page', 'customise_page_block');
        $this->getPage()->setBlock('modify_layout', 'colour_list', 'colour_list_block');
        $this->getPage()->setBlock('customise_page', 'eportfolio_component_tab', 'eportfolio_component_tab_block');
        $this->getPage()->setBlock('customise_page', 'eportfolio_component_page', 'eportfolio_component_page_block');
        $this->getPage()->setBlock('eportfolio_component_page', 'eportfolio_component', 'eportfolio_component_block');
        $this->getPage()->setBlock('customise_page', 'no_eportfolio_components', 'no_eportfolio_components_block');

        /* Permissions list for groups
		 */
        $this->getPage()->setBlock('customise_page', 'has_group_permissions', 'has_group_permissions_block');
        $this->getPage()->setBlock('has_group_permissions', 'group_permission', 'group_permission_block');

        /* Set meta data
		 */
        $this->getPage()->setVar(array(
            'HEADING' => Scrub::text($this->getUserName()),
            'ID' => $this->getUserId(),
            'USER_TOKEN' => $_SESSION['key'],
            'PAGE_TITLE' => $this->getEportfolioHandler()->getSettingsPageTitle(),
            'PAGE_ID' => $this->getEportfolioHandler()->getSettingsPageId(),
        ));

        $this->getPage()->setVar(array(
            'edit_page_block' => '',
            'modify_layout_block' => '',
            'customise_page_block' => '',
            'colour_list_block' => '',
        ));
    }

    /**
	 * Render everything
	 */
    protected function render()
    {
        $this->renderComponents();

        if ($this->getPermission() > WRITE_ACCESS) {
            $this->renderTabList();
        }

        $this->renderCurrentColours();
        $this->renderColours();
        $this->renderColumnWidths();
        $this->renderAllowedFileTypes();
        $this->renderPermissions();

        /* Container blocks must parse after their inner organs
         */
        if ($this->getPermission() > WRITE_ACCESS) {
            $this->getPage()->parse('edit_page_block', 'edit_page');
            $this->getPage()->parse('modify_layout_block', 'modify_layout');
            $this->getPage()->parse('customise_page_block', 'customise_page');
        }
    }

    /**
	 * Check that the page being viewed has a template
	 */
    private function validatePageTemplate()
    {
        if ($this->getEportfolioHandler()->getSettingsTemplate() === false) {
            die('no template');
        }
    }

    /**
	 * Render components
	 */
    private function renderComponents()
    {
        $instanceCollection = $this->getComponentFactory()->getDefinitionList('eportfolio_template');

        foreach ($instanceCollection as $instance) {
            /** @var Component_Instance $instance */

            $definition = $instance->getDefinition();
            $definition->setComponentCategory($instance->getIdentifier());
            $definition->setComponentCategoryName($this->getEportfolioHandler()->getTemplateNameById($instance->getIdentifier()));

            foreach ($this->getComponentCollection() as $inst) {
                /** @var Component_Eportfolio_ControllerAbstract $inst */
                if ($inst->getInstance()->getDefinitionId() == $instance->getDefinitionId()) {
                    $instance->incrementInstanceCount();
                }
            }
        }

        $tabNumber = 0;
        if ($total = $instanceCollection->count()) {
            $i = 0;
            $previous = new Component_Instance();

            foreach ($instanceCollection as $component) {
                /** @var Component_Instance $component */
                ++$i;
                $class = str_replace('\\', '_', $component->getDefinition()->getClass());
                $multipleClass = '';

                if ($component->getDefinition()->getMultiple() == 'false') {
                    $multipleClass .= ' one-only';

                    if ($component->getDefinition()->getMultiple() == 'false' && $component->getInstanceCount() > 0) {
                        $multipleClass .= ' disabled';
                    }
                }

                $class = trim($class);

                /* Draw the name and description from the config, instead of the definition
				 */
                if (count($component->getConfig())) {
                    $componentName = '';
                    $componentDescription = '';
                    foreach($component->getConfig() as $currentConfig) {
                        /** @var Component_ConfigDefinition $currentConfig */
                        if ($currentConfig->getKey() == 'componentname') {
                            $componentName = $currentConfig->getValue();
                        } elseif ($currentConfig->getKey() == 'description') {
                            $componentDescription = $currentConfig->getValue();
                        }
                    }

                    /* If a component exists but hasn't had its name/purpose customised,
					 * use the defaults from component definition
					 */
                    if (!strlen($componentName)) {
                        $componentName = $component->getDefinition()->getName();
                    }

                    if (!strlen($componentDescription)) {
                        $componentDescription = $component->getDefinition()->getDescription();
                    }
                }
                /* If there aren't any configs, use a default pair from definition
				 */
                else {
                    $componentName = $component->getDefinition()->getName();
                    $componentDescription = $component->getDefinition()->getDescription();
                }

                $this->getPage()->setVar(array(
                    'EPORTFOLIO_COMPONENT_NAME' => Scrub::text($componentName),
                    'EPORTFOLIO_COMPONENT_DESCRIPTION' => Scrub::text($componentDescription),
                    'EPORTFOLIO_COMPONENT_ID' => Scrub::text($component->getId()),
                    'EPORTFOLIO_COMPONENT_DEFINITION_ID' => Scrub::text($component->getDefinitionId()),
                    'EPORTFOLIO_COMPONENT_COUNT' => $component->getInstanceCount(),
                    'EPORTFOLIO_COMPONENT_CLASS' => $class,
                    'EPORTFOLIO_COMPONENT_CATEGORY_ID' => $previous->getIdentifier(),
                    'EPORTFOLIO_COMPONENT_MULTIPLE_STATE' => $multipleClass,
                    'EPORTFOLIO_COMPONENT_CATEGORY_CLASS' => $tabNumber === 0 ? 'active' : '',
                ));

                $this_category = $component->getDefinition()->getComponentCategory();
                $last_category = null;
                if ($previous->getDefinition() instanceof Component_Definition) {
                    $last_category = $previous->getDefinition()->getComponentCategory();
                }
                if ($this_category != $last_category && $i > 1) {
                    $this->getPage()->parse('eportfolio_component_page_block', 'eportfolio_component_page', true);
                    $this->getPage()->setVar('eportfolio_component_block', '');
                    $this->getPage()->parse('eportfolio_component_block', 'eportfolio_component', true);
                    $tabNumber++;

                    // last rotation
                    if ($total == $i) {
                        $this->getPage()->setVar('EPORTFOLIO_COMPONENT_CATEGORY_ID', $this_category);
                        $this->getPage()->parse('eportfolio_component_page_block', 'eportfolio_component_page', true);
                    }
                } else {
                    // last rotation
                    if ($total == $i) {
                        $this->getPage()->parse('eportfolio_component_block', 'eportfolio_component', true);
                        $this->getPage()->setVar('EPORTFOLIO_COMPONENT_CATEGORY_ID', $this_category);
                        $this->getPage()->parse('eportfolio_component_page_block', 'eportfolio_component_page', true);
                    } else {
                        $this->getPage()->parse('eportfolio_component_block', 'eportfolio_component', true);
                    }
                }

                $previous = $component;

                /* >:/
				 */
                $this->tabList[$this_category]['name'] = Scrub::text($component->getDefinition()->getComponentCategoryName());
                $this->tabList[$this_category]['count']++;
            }

            $this->getPage()->setVar(array(
                'no_eportfolio_components_block' => ''
            ));
        }
        else {
            $this->getPage()->parse('no_eportfolio_components_block', 'no_eportfolio_components');

            $this->getPage()->setVar(array(
                'eportfolio_component_block' => '',
                'eportfolio_component_page_block' => '',
                'eportfolio_component_tab_block' => '',
            ));
        }

        $occupiedPositionList = array();

        foreach ($this->getComponentCollection() as $component) {
            /** @var Component_ControllerAbstract $component */
            $templateTarget = "component_position_{$component->getInstance()->getPosition()}";
            $templateHandle = "component_{$component->getInstance()->getId()}";

            $component->setup($this, $component->getInstance()->getConfigArray());
            if ($component->execute() !== false) {
                if ($component->renderTemplate($templateHandle) !== false) {
                    if (!in_array($component->getInstance()->getPosition(), $occupiedPositionList)) {
                        $occupiedPositionList[] = $component->getInstance()->getPosition();
                    }

                    $this->getPage()->parse($templateTarget, $templateHandle, true);
                }
            }
        }

        $emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);

        foreach ($emptyPositionList as $emptyPosition) {
            $this->getPage()->setVar("component_position_{$emptyPosition}", '');
        }
    }

    /**
	 * Render tabs (categories for components)
	 */
    private function renderTabList()
    {
        $tabList = $this->getTabList();

        if (isset($tabList) && count($tabList)) {
            $tabNumber = 0;
            foreach ($tabList as $id => $tab) {
                $this->getPage()->setVar(array(
                    'COMPONENT_CATEGORY_ID' => $id,
                    'COMPONENT_CATEGORY_NAME' => $tab['name'],
                    'COMPONENT_CATEGORY_COUNT' => $tab['count'],
                    'COMPONENT_CATEGORY_CLASS' => $tabNumber === 0 ? 'active' : '',
                ));

                $this->getPage()->parse('eportfolio_component_tab_block', 'eportfolio_component_tab', true);
                $tabNumber++;
            }
        }
    }

    /**
     * Macro
     *
     * @param $set
     * @param $key
     */
    private function renderColourSet($set, $key) {
        foreach ($set as $field => $colour) {
            $vars = array(
                $field => $colour
            );

            $this->getPage()->setVar($vars);
        }

        $this->getPage()->setVar(array(
            'COLOUR_ARRAY' => $key
        ));

        $this->getPage()->parse('colour_list_block', 'colour_list', true);
    }

    /**
     * Render the current colour set
     * These are unblocked phplibbery
     */
    private function renderCurrentColours() {
        foreach ($this->getColours() as $field => $colour) {
            $vars['current_' . $field] = $colour;
        }

        $this->getPage()->setVar($vars);
    }

    /**
	 * Render customised colours for:
     * the pickers' boxes,
     * the currently-chosen set of colours on the components themselves,
     * and the values to which pickers' boxes reset themselves
	 */
    private function renderColours()
    {
        /* Pickers colour set (when you choose new colours for components)
         */
        $this->renderColourSet($this->getColours(), 'pickers');

        /* Default colour set (reset)
         */
        $this->renderColourSet($this->getEportfolioHandler()->getDefaultColorList(), 'defaults');

        /* Current colour set (currently chosen and saved in db)
         */
        $this->renderColourSet($this->getColours(), 'original');
    }

    /**
	 * Figure out column widths
	 */
    private function renderColumnWidths()
    {
        $allWidths = $this->getEportfolioHandler()->getColumnWidths();
        $leftWidth = round($allWidths['left_column_width'] * 12 / 100);
        $this->getPage()->setVar(array(
            'TEMPLATE_LEFT_COLUMN_WIDTH'  => $leftWidth,
            'TEMPLATE_RIGHT_COLUMN_WIDTH' => 12 - $leftWidth,
        ));
    }

    /**
	 * CSS classes for mime types
	 */
    private function renderAllowedFileTypes()
    {
        $allowedFiles = new AllowedFile();
        $allowedList = $allowedFiles->getAll();

        if (is_array($allowedList)) {

            // append link icon to results
            $allowedList[]['iconFile'] = "link.png";
            $last = '';

            foreach ($allowedList as $allowed) {
                if ($last != $allowed["iconFile"]) {
                    $this->getPage()->setVar(array(
                        "NAME" => $allowedFiles->getClass($allowed["iconFile"]),
                        "ICON" => $allowed["iconFile"],
                    ));

                    $this->getPage()->parse("allowed_files_block", "allowed_files", true);

                    $last = $allowed["iconFile"];
                }
            }
        } else {
            $this->getPage()->setVar('allowed_files_block', '');
        }
    }

    /**
	 * Render all permissions for a page
	 */
    private function renderPermissions()
    {
        $this->renderParentPermissions();
        $this->renderGroupPermissions();
        $this->renderIndividualPermissions();
    }

    /**
	 * Render parent permissions
	 */
    private function renderParentPermissions()
    {
        $selectedParents = \Eportfolio\PermissionMapper::getInstance()->findByColumnList(array(
            'page_id' => $this->getPageId(),
            'type' => 'parent',
            'value' => 1

        ));

        if ($selectedParents->count()) {
            $selected = 'checked="checked"';
        } else {
            $selected = '';
        }

        $this->getPage()->setVar(array(
            'PARENT_PERMISSION_SELECTED' => $selected
        ));
    }

    /**
	 * Render group permissions
	 */
    private function renderGroupPermissions()
    {
        $allGroups = $this->getGroupHandler()->involvedInCategoryList($this->getUserId(), false);

        $selectedGroups = \Eportfolio\PermissionMapper::getInstance()->findByColumnList(array(
            'page_id' => $this->getPageId(),
            'type' => 'group'
        ));

        $selectedGroupIds = array();
        foreach ($selectedGroups as $selectedGroup) {
            $selectedGroupIds[] = $selectedGroup->getValue();
        }

        if (is_array($allGroups) && count($allGroups)) {
            foreach ($allGroups as $currentGroup) {
                if (in_array($currentGroup['id'], $selectedGroupIds)) {
                    $selected = 'checked="checked"';
                } else {
                    $selected = '';
                }

                $this->getPage()->setVar(array(
                    'GROUP_PERMISSION_GROUP_ID' => $currentGroup['id'],
                    'GROUP_PERMISSION_NAME' => $currentGroup['name'],
                    'GROUP_PERMISSION_SELECTED' => $selected,
                ));

                $this->getPage()->parse('group_permission_block', 'group_permission', true);
            }

            $this->getPage()->parse('has_group_permissions_block', 'has_group_permissions');
        } else {
            $this->getPage()->setVar('has_group_permissions_block', '');
        }
    }

    /**
	 * Render individual permissions
	 */
    private function renderIndividualPermissions()
    {
        $this->getPage()->setVar(array(
            'STUDENT_GROUP_IDS' => implode(';', $this->getGroupHandler()->getStudentGroupTypeIds())
        ));

        /* Find all the guys who were allowed to see this page
		 */
        $selectedIndividuals = \Eportfolio\PermissionMapper::getInstance()->findByColumnList(array(
            'page_id' => $this->getPageId(),
            'type' => 'student'
        ));

        /* Just list their ids...
		 */
        $selectedIndividualIds = array();
        foreach ($selectedIndividuals as $selectedIndividual) {
            $selectedIndividualIds[] = $selectedIndividual->getValue();
        }

        /* ...and get their records
		 */
        $individualList = array();
        if (count($selectedIndividualIds)) {

            // Load each user and their role
            $userMapper = \Schoolbox\UserMapper::getInstance();
            $allIndividuals = $userMapper->findByColumn('id', $selectedIndividualIds);
            $userMapper->loadJoinedModel('role', $allIndividuals);

            foreach ($allIndividuals as $currentIndividual) {
                $person = $currentIndividual->getFullname().' - ';
                if (is_numeric($currentIndividual->getYear())) {
                    $person .= "Year {$currentIndividual->getYear()}, ";
                }
                $person .= $currentIndividual->getRole()->getName();
                $individualList[] = array(
                    'id'   => $currentIndividual->getId(),
                    'name' => $person,
                );
            }
        }
        $this->getPage()->setVar('USER_LIST', json_encode($individualList));
    }

    /* Getters/Setters
	 */

    /**
	 * Set colours
	 *
	 * @param null $colours
	 */
    private function setColours($colours)
    {
        $this->colours = $colours;
    }

    /**
	 * Get colours
	 *
	 * @return null
	 */
    private function getColours()
    {
        return $this->colours;
    }

    /**
	 * Set component collection
	 *
	 * @param null $componentCollection
	 */
    private function setComponentCollection($componentCollection)
    {
        $this->componentCollection = $componentCollection;
    }

    /**
	 * Get component collection
	 *
	 * @return null
	 */
    private function getComponentCollection()
    {
        return $this->componentCollection;
    }

    /**
	 * Set component factory
	 *
	 * @param Component_FactoryAbstract $componentFactory
	 */
    private function setComponentFactory($componentFactory)
    {
        $this->componentFactory = $componentFactory;
    }

    /**
	 * Get component factory
	 *
	 * @return Component_FactoryAbstract
	 */
    private function getComponentFactory()
    {
        return $this->componentFactory;
    }

    /**
	 * Set tab list
	 *
	 * @param array $tabList
	 */
    private function setTabList($tabList)
    {
        $this->tabList = $tabList;
    }

    /**
	 * Get tab list
	 *
	 * @return array
	 */
    private function getTabList()
    {
        return $this->tabList;
    }
}
