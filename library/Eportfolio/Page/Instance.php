<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
use Alaress\Color\ColorFactory;
use Eportfolio\Permission;
use Eportfolio\PermissionMapper as EportfolioPermissionMapper;
use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;

class Eportfolio_Page_Instance extends Eportfolio_Page_Abstract
{
    /** @var Component_FactoryAbstract $componentFactory An instance of Component_FactoryAbstract */
    private $componentFactory;

    /** @var string $templateFile The template file to use */
    protected $templateFile = 'eportfolio/instance.html';

    /** @var ColorFactory $colorFactory An object which builds color objects from color strings */
    private $colorFactory;

    /** @var Collection|null $componentCollection An instance of Collection */
    private $componentCollection;

    /** @var null $colours A list of colours to use */
    private $colours;

    /** @var array $tabList A list of component tab categories */
    private $tabList = [];

    /**
     * Set up classes and collections beyond the basic norms
     */
    protected function initialise()
    {
        $this->validatePageTemplate();

        // Assemble list of components

        $this->setComponentFactory(new Component_Eportfolio_Factory($this->getUserHandler(), $this->getPage()));
        $this->setComponentCollection($this->getComponentFactory()->buildComponentList($this->getPageId()));

        // Page colours

        $this->setColorFactory(new ColorFactory());
        $this->setColours($this->getEportfolioHandler()->getColorList());
    }

    /**
     * Set up blocks and stuff
     */
    protected function setup()
    {
        $this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
        $this->getPage()->setTab('ePortfolio');

        // Set up blocks and stuff

        $this->getPage()->setTitle($this->getEportfolioHandler()->getSettingsPageTitle());
        $this->getPage()->setBlock('custom_head', 'modify_layout', 'modify_layout_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'edit_page', 'edit_page_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'customise_page', 'customise_page_block');
        $this->getPage()->setBlock('modify_layout', 'colour_list', 'colour_list_block');
        $this->getPage()->setBlock('customise_page', 'eportfolio_component_tab', 'eportfolio_component_tab_block');
        $this->getPage()->setBlock('customise_page', 'eportfolio_component_page', 'eportfolio_component_page_block');
        $this->getPage()->setBlock('eportfolio_component_page', 'eportfolio_component', 'eportfolio_component_block');
        $this->getPage()->setBlock('customise_page', 'no_eportfolio_components', 'no_eportfolio_components_block');

        // Permissions list for groups

        $this->getPage()->setBlock('customise_page', 'has_group_permissions', 'has_group_permissions_block');
        $this->getPage()->setBlock('has_group_permissions', 'group_permission', 'group_permission_block');

        // Set meta data

        $this->getPage()->setVar([
            'HEADING'    => Scrub::text($this->getUserName()),
            'ID'         => $this->getUserId(),
            'USER_TOKEN' => $_SESSION['key'],
            'PAGE_TITLE' => Scrub::text($this->getEportfolioHandler()->getSettingsPageTitle()),
            'PAGE_ID'    => $this->getEportfolioHandler()->getSettingsPageId(),
        ]);

        $this->getPage()->setVar([
            'edit_page_block'      => '',
            'modify_layout_block'  => '',
            'customise_page_block' => '',
            'colour_list_block'    => '',
        ]);
    }

    /**
     * Render everything
     */
    protected function render()
    {
        $this->renderComponents();

        if ($this->getPermission() > UserAccess::ACCESS_WRITE) {
            $this->renderTabList();
        }

        $this->renderCurrentColours();
        $this->renderColours();
        $this->renderColumnWidths();
        $this->renderPermissions();

        // Container blocks must parse after their inner organs

        if ($this->getPermission() > UserAccess::ACCESS_WRITE) {
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
        // Some kind of change to the FactoryAbstract's getDefinitionList() signature now causes it to behave
        // differently, using the Module context that was set when the factory was instantiated. In this case we need
        // to change that context

        $this->getComponentFactory()->setModule('eportfolio_template');

        $instanceCollection = $this->getComponentFactory()->getModuleInstanceList('eportfolio_template');

        /** @var Component_Instance $instance */
        foreach ($instanceCollection as $instance) {
            /** @var Component_Eportfolio_ControllerAbstract $component */
            foreach ($this->getComponentCollection() as $component) {
                if ($component->getInstance()->getDefinitionId() == $instance->getDefinitionId()) {
                    $instance->incrementInstanceCount();
                }
            }
        }

        $tabNumber = 0;

        if ($total = $instanceCollection->count()) {
            $i        = 0;
            $previous = new Component_Instance();

            /** @var Component_Instance $component */
            foreach ($instanceCollection as $component) {
                // Make sure the component is allowed to be rendered in the palette

                if (!$component->renderInPalette($component->getDefinition()->getModule(), $this->getPageId())) {
                    continue;
                }

                ++$i;
                $class         = $component->getDefinition()->getCssClass();
                $multipleClass = '';

                if ($component->getDefinition()->getMultiple() == 'false') {
                    $multipleClass .= ' one-only';

                    if ($component->getDefinition()->getMultiple() == 'false' && $component->getInstanceCount() > 0) {
                        $multipleClass .= ' disabled';
                    }
                }

                $class = trim($class);

                // Draw the name and description from the config, instead of the definition

                if (count($component->getConfig())) {
                    $componentName        = '';
                    $componentDescription = '';
                    foreach ($component->getConfig() as $currentConfig) {
                        /** @var Component_ConfigDefinition $currentConfig */
                        if ($currentConfig->getKey() == 'componentname') {
                            $componentName = $currentConfig->getValue();
                        } elseif ($currentConfig->getKey() == 'description') {
                            $componentDescription = $currentConfig->getValue();
                        }
                    }

                    // If a component exists but hasn't had its name/purpose customised use the defaults

                    if (!strlen($componentName)) {
                        $componentName = $component->getDefinition()->getName();
                    }

                    if (!strlen($componentDescription)) {
                        $componentDescription = $component->getDefinition()->getDescription();
                    }
                } else {
                    // If there aren't any configs, use a default pair from definition

                    $componentName        = $component->getDefinition()->getName();
                    $componentDescription = $component->getDefinition()->getDescription();
                }

                $this->getPage()->setVar([
                    'EPORTFOLIO_COMPONENT_NAME'           => Scrub::text($componentName),
                    'EPORTFOLIO_COMPONENT_DESCRIPTION'    => Scrub::text($componentDescription),
                    'EPORTFOLIO_COMPONENT_ID'             => Scrub::text($component->getId()),
                    'EPORTFOLIO_COMPONENT_DEFINITION_ID'  => Scrub::text($component->getDefinitionId()),
                    'EPORTFOLIO_COMPONENT_COUNT'          => $component->getInstanceCount(),
                    'EPORTFOLIO_COMPONENT_CLASS'          => $class,
                    'EPORTFOLIO_COMPONENT_CATEGORY_ID'    => $previous->getIdentifier(),
                    'EPORTFOLIO_COMPONENT_MULTIPLE_STATE' => $multipleClass,
                    'EPORTFOLIO_COMPONENT_CATEGORY_CLASS' => $tabNumber === 0 ? 'active' : '',
                ]);

                $this_category = $component->getIdentifier();
                $last_category = null;

                if ($previous->getDefinition() instanceof Component_Definition) {
                    $last_category = $previous->getIdentifier();
                }

                if ($this_category != $last_category && $i > 1) {
                    $this->getPage()->parse('eportfolio_component_page_block', 'eportfolio_component_page', true);
                    $this->getPage()->setVar('eportfolio_component_block', '');
                    $this->getPage()->parse('eportfolio_component_block', 'eportfolio_component', true);
                    $tabNumber++;

                    // Last rotation
                    if ($total == $i) {
                        $this->getPage()->setVar('EPORTFOLIO_COMPONENT_CATEGORY_ID', $this_category);
                        $this->getPage()->parse('eportfolio_component_page_block', 'eportfolio_component_page', true);
                    }
                } else {
                    // Last rotation
                    if ($total == $i) {
                        $this->getPage()->parse('eportfolio_component_block', 'eportfolio_component', true);
                        $this->getPage()->setVar('EPORTFOLIO_COMPONENT_CATEGORY_ID', $this_category);
                        $this->getPage()->parse('eportfolio_component_page_block', 'eportfolio_component_page', true);
                    } else {
                        $this->getPage()->parse('eportfolio_component_block', 'eportfolio_component', true);
                    }
                }

                $previous = $component;

                $this->tabList[$this_category]['name'] = Scrub::text($this->getEportfolioHandler()->getTemplateNameById($component->getIdentifier()));
                $this->tabList[$this_category]['count']++;
            }

            $this->getPage()->setVar([
                'no_eportfolio_components_block' => ''
            ]);
        } else {
            $this->getPage()->parse('no_eportfolio_components_block', 'no_eportfolio_components');

            $this->getPage()->setVar([
                'eportfolio_component_block'      => '',
                'eportfolio_component_page_block' => '',
                'eportfolio_component_tab_block'  => '',
            ]);
        }

        $occupiedPositionList = [];

        /** @var Component_ControllerAbstract $component */
        foreach ($this->getComponentCollection() as $component) {
            try {
                $templateTarget = "component_position_{$component->getInstance()->getPosition()}";
                $templateHandle = "component_{$component->getInstance()->getId()}";

                $component->setup($this);
                if ($component->execute() !== false) {
                    if ($component->renderTemplate($templateHandle) !== false) {
                        if (!in_array($component->getInstance()->getPosition(), $occupiedPositionList)) {
                            $occupiedPositionList[] = $component->getInstance()->getPosition();
                        }

                        $this->getPage()->parse($templateTarget, $templateHandle, true);
                    }
                }
            } catch (\Throwable $e) {
                // if a component explodes for any reason, we only want it to explode the page if we're in debug mode
                if (Config::get('debug_mode')) {
                    throw $e;
                }

                if ($this->getPage()->getDi()->has('sentry')) {
                    /** @var Raven_Client $sentry */
                    $sentry = $this->getPage()->getDi()->get('sentry');
                    $sentry->captureException($e);
                }
            }
        }

        $emptyPositionList = array_diff(['top', 'left', 'right'], $occupiedPositionList);

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
                $this->getPage()->setVar([
                    'COMPONENT_CATEGORY_ID'    => $id,
                    'COMPONENT_CATEGORY_NAME'  => $tab['name'],
                    'COMPONENT_CATEGORY_COUNT' => $tab['count'],
                    'COMPONENT_CATEGORY_CLASS' => $tabNumber === 0 ? 'active' : ''
                ]);

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
    private function renderColourSet($set, $key)
    {
        foreach ($set as $field => $colour) {
            try {
                $colorObject = $this->getColorFactory()->build($colour);
            } catch (InvalidArgumentException $e) {
                $colorObject = $this->getColorFactory()->build('000000');
            }
            $vars = [
                $field => (string)$colorObject
            ];

            $this->getPage()->setVar($vars);
        }

        $this->getPage()->setVar([
            'COLOUR_ARRAY' => $key
        ]);

        $this->getPage()->parse('colour_list_block', 'colour_list', true);
    }

    /**
     * Render the current colour set (these are unblocked phplibbery)
     */
    private function renderCurrentColours()
    {
        foreach ($this->getColours() as $field => $colour) {
            $vars['current_' . $field] = $colour;
        }

        if (isset($vars)) {
            $this->getPage()->setVar($vars);
        }
    }

    /**
     * Render customised colours for:
     *
     *      - the pickers' boxes,
     *      - the currently-chosen set of colours on the components themselves,
     *      - and the values to which pickers' boxes reset themselves
     */
    private function renderColours()
    {
        // Pickers colour set (when you choose new colours for components)

        $this->renderColourSet($this->getColours(), 'pickers');

        // Default colour set (reset)

        $this->renderColourSet($this->getEportfolioHandler()->getDefaultColorList(), 'defaults');

        // Current colour set (currently chosen and saved in db)

        $this->renderColourSet($this->getColours(), 'original');
    }

    /**
     * Figure out column widths
     */
    private function renderColumnWidths()
    {
        $allWidths = $this->getEportfolioHandler()->getColumnWidths();
        $leftWidth = round($allWidths['left_column_width'] * 12 / 100);

        $this->getPage()->setVar([
            'TEMPLATE_LEFT_COLUMN_WIDTH'  => $leftWidth,
            'TEMPLATE_RIGHT_COLUMN_WIDTH' => 12 - $leftWidth,
        ]);
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
        $selectedParents = EportfolioPermissionMapper::getInstance()->findByColumnList([
            'page_id' => $this->getPageId(),
            'type'    => Permission::TYPE_PARENT,
            'value'   => 1
        ]);

        if ($selectedParents->count()) {
            $selected = 'checked="checked"';
        } else {
            $selected = '';
        }

        $this->getPage()->setVar([
            'PARENT_PERMISSION_SELECTED' => $selected
        ]);
    }

    /**
     * Render group permissions
     */
    private function renderGroupPermissions()
    {
        $allGroups = $this->getGroupHandler()->involvedInCategoryList($this->getUserId(), false);

        $selectedGroups = EportfolioPermissionMapper::getInstance()->findByColumnList([
            'page_id' => $this->getPageId(),
            'type'    => 'group'
        ]);

        $selectedGroupIds = [];
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

                $this->getPage()->setVar([
                    'GROUP_PERMISSION_GROUP_ID' => $currentGroup['id'],
                    'GROUP_PERMISSION_NAME'     => $currentGroup['name'],
                    'GROUP_PERMISSION_SELECTED' => $selected,
                ]);

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
        $this->getPage()->setVar([
            'STUDENT_GROUP_IDS' => implode(';', $this->getGroupHandler()->getStudentGroupTypeIds())
        ]);

        // Find all the guys who were allowed to see this page

        $selectedIndividuals = EportfolioPermissionMapper::getInstance()->findByColumnList([
            'page_id' => $this->getPageId(),
            'type'    => 'student'
        ]);

        // Just list their ids...

        $selectedIndividualIds = [];

        foreach ($selectedIndividuals as $selectedIndividual) {
            $selectedIndividualIds[] = $selectedIndividual->getValue();
        }

        // ...and get their records

        $individualList = [];
        if (count($selectedIndividualIds)) {
            // Load each user and their role

            $userMapper     = UserMapper::getInstance();
            $allIndividuals = $userMapper->findByColumn('id', $selectedIndividualIds);
            $userMapper->loadJoinedModel('role', $allIndividuals);

            foreach ($allIndividuals as $currentIndividual) {
                $person = $currentIndividual->getFullname() . ' - ';

                if (is_numeric($currentIndividual->getYear())) {
                    $person .= "Year {$currentIndividual->getYear()}, ";
                }

                $person           .= $currentIndividual->getRole()->getName();
                $individualList[] = [
                    'id'   => $currentIndividual->getId(),
                    'name' => $person
                ];
            }
        }

        $this->getPage()->setVar('USER_LIST', json_encode($individualList));
    }

    // Getters & Setters

    /**
     * @param null $colours
     */
    private function setColours($colours)
    {
        $this->colours = $colours;
    }

    /**
     * @return null
     */
    private function getColours()
    {
        return $this->colours;
    }

    /**
     * @param Collection $componentCollection
     * @return self
     */
    private function setComponentCollection($componentCollection)
    {
        $this->componentCollection = $componentCollection;
        return $this;
    }

    /**
     * @return Collection
     */
    private function getComponentCollection()
    {
        return $this->componentCollection;
    }

    /**
     * @param Component_FactoryAbstract $componentFactory
     * @return self
     */
    private function setComponentFactory($componentFactory)
    {
        $this->componentFactory = $componentFactory;
        return $this;
    }

    /**
     * @return Component_FactoryAbstract
     */
    private function getComponentFactory()
    {
        return $this->componentFactory;
    }

    /**
     * @return array
     */
    private function getTabList()
    {
        return $this->tabList;
    }

    /**
     * @return ColorFactory
     */
    private function getColorFactory()
    {
        return $this->colorFactory;
    }

    /**
     * @param ColorFactory $colorFactory
     * @return self
     */
    private function setColorFactory($colorFactory)
    {
        $this->colorFactory = $colorFactory;
        return $this;
    }
}
