<?php

use Alaress\Web\Request\Values;
use Eportfolio\PageMapper;
use Eportfolio\Template;
use Eportfolio\TemplateMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\UserMapper;

/**
 * Eportfolio
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Eportfolio
{
    /**
     * The current user id
     *
     * @var int
     */
    private $userId = -1;

    /**
     * PageId of the current page.
     *
     * @var int
     */
    private $pageId = -1;

    /**
     * An instance of Eportfolio_Page
     * This is the page currently being viewed
     *
     * @var \Eportfolio\Page
     */
    private $pageInstance = null;

    /**
     * This is the prefix used for all the cloned sql queries
     *
     * @var string
     */
    private $context = 'eportfolio';

    /**
     * User mapper
     */
    private UserMapper $userMapper;

    /**
     * Eportfolio page mapper
     */
    private PageMapper $pageMapper;

    /**
     * Eportfolio template mapper
     */
    private TemplateMapper $templateMapper;

    /**
     * @var GenericCollection|Template[]
     */
    private GenericCollection $allTemplates;

    /**
     * Constructor
     *
     * @param int $userId
     * @param int $pageId
     */
    public function __construct($userId, $pageId)
    {
        $this->setUserId($userId);
        $this->setPageId($pageId);

        // TODO inject these mappers for testability
        $this->userMapper     = UserMapper::getInstance();
        $this->pageMapper     = PageMapper::getInstance();
        $this->templateMapper = TemplateMapper::getInstance();

        /* Preload settings for user-built pages
         *
         * Otherwise they are hardcoded pages and we don't need
         * this extra information
         */
        if (is_numeric($pageId)) {
            $pageInstance = $this->pageMapper->findById($pageId);

            $this->setPageInstance($pageInstance);
        } else {
            /* Spawn a stub one to make the interfaces happy
             * (was easier, but dirtier, to use an empty array)
             */
            $user = $this->userMapper->createModel($userId);
            $page = $this->pageMapper->createModel($pageId);
            $page->setOwner($user);
            $this->pageInstance = $page;
        }
    }

    /**
     * Delegate method
     *
     * Get the page title
     *
     * @return bool
     */
    public function getSettingsPageTitle()
    {
        return $this->getPageInstance()->getPageTitle();
    }

    /**
     * Delegate method
     *
     * Get the page id
     *
     * @return bool
     */
    public function getSettingsPageId()
    {
        return $this->getPageInstance()->getPageId();
    }

    /**
     * Delegate method
     *
     * Returns the current template id
     *
     * @return mixed
     */
    public function getSettingsTemplate()
    {
        return $this->getPageInstance()->getTemplate();
    }

    /**
     * Delegate method
     *
     * Returns the owner id of a given page
     *
     * @return int
     */
    public function getSettingsPageOwner()
    {
        return $this->getPageInstance()->getOwner()->getId();
    }

    /**
     * Update a page title
     *
     * @param string $value
     */
    public function updatePageTitle($value)
    {
        $this->getPageInstance()->setPageTitle($value);

        $this->pageMapper->save($this->getPageInstance());
    }

    /**
     * Softdelete a page
     */
    public function softdelete()
    {
        $this->getPageInstance()->setDeletedAt(new Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

        $this->pageMapper->save($this->getPageInstance());
    }

    /**
     * Get all eportfolio_template records
     *
     * @return GenericCollection|Template[]
     */
    public function fetchAllTemplates()
    {
        return $this->templateMapper->findAll();
    }

    /**
     * Get a template name by its id, from the entire set
     *
     * @param int $id
     * @return string
     */
    public function getTemplateNameById($id): string
    {
        if (!isset($this->allTemplates)) {
            $this->allTemplates = $this->fetchAllTemplates();
        }
        return $this->allTemplates->getBy('id', $id)->getName();
    }

    /**
     * Gets the column widths for a particular homepage
     *
     * @return array
     */
    public function getColumnWidths()
    {
        $result = [
            'left_column_width'  => $this->getPageInstance()->getLeftColumnWidth(),
            'right_column_width' => $this->getPageInstance()->getRightColumnWidth(),
        ];
        // Defaults: 70/30
        if (!isset($result['left_column_width'])) {
            $result['left_column_width'] = 70;
        }
        if (!isset($result['right_column_width'])) {
            $result['right_column_width'] = 30;
        }
        return $result;
    }

    /**
     * Update column widths
     *
     * @param  float $left
     * @param  float $right
     * @return void
     */
    public function updateColumnWidths($left, $right)
    {
        $this->getPageInstance()->setLeftColumnWidth($left);
        $this->getPageInstance()->setRightColumnWidth($right);

        $this->pageMapper->save($this->getPageInstance());
    }

    /**
     * Updates left and right columns widths based on the proportion (out of 12)
     * the left column takes up.
     *
     * @param int $leftProportion min: 3, max: 9
     */
    public function updateGrid($leftProportion)
    {
        $leftProportion = min(9, max(3, $leftProportion));
        $leftPercent    = round($leftProportion * (100 / 12)) - .5;
        $this->updateColumnWidths($leftPercent, 99 - $leftPercent);
    }

    /**
     * Spawn a list of default colours
     *
     * @return array
     */
    public function getDefaultColorList()
    {
        $colourSet = [];
        $defaultHeadingColours = $this->getDefaultHeadingColours();

        foreach (['top', 'left', 'right'] as $position) {
            $colourSet = array_merge($colourSet, [
                $position . '_head_font'  => $defaultHeadingColours['head_font'],
                $position . '_head_bg'    => $defaultHeadingColours['head_bg'],
                $position . '_font_primary' => Config::get(ConfigMapper::NAME_HOMEPAGE_COLOUR_FONT),
                $position . '_bg_primary' => Config::get(ConfigMapper::NAME_HOMEPAGE_COLOUR_BG_PRIMARY),
            ]);
        }

        return $colourSet;
    }

    /**
     * Gets the default heading colours for a homepage, which are either the skin component heading colours
     * (if advanced UI is enabled) or the homepage heading colours (if not).
     *
     * @return array
     */
    private function getDefaultHeadingColours(): array
    {
        if ($this->isAdvancedUiEnabled()) {
            return [
                'head_font' => Config::get(ConfigMapper::NAME_SKIN_COLOUR_COMPONENT_HEADING_TEXT),
                'head_bg'   => Config::get(ConfigMapper::NAME_SKIN_COLOUR_COMPONENT_HEADING_BACKGROUND),
            ];
        }

        return [
            'head_font' => Config::get(ConfigMapper::NAME_HOMEPAGE_COLOUR_HEAD_FONT),
            'head_bg'   => Config::get(ConfigMapper::NAME_HOMEPAGE_COLOUR_HEAD_BG),
        ];
    }

    private function isAdvancedUiEnabled(): bool
    {
        $configValues = new Values([
            ConfigMapper::NAME_SONAR_UI_TOGGLE => Config::get(ConfigMapper::NAME_SONAR_UI_TOGGLE),
        ]);

        return $configValues->getBool(ConfigMapper::NAME_SONAR_UI_TOGGLE, false) ?? false;
    }

    /**
     * Get a list of colours for a particular page
     *
     * @return array|bool
     */
    public function getColorList()
    {
        $colourList = [
            'left_head_font'  => $this->getPageInstance()->getLeftHeadFont(),
            'left_head_bg'    => $this->getPageInstance()->getLeftHeadBg(),
            'left_font_primary' => $this->getPageInstance()->getLeftFont(),
            'left_bg_primary' => $this->getPageInstance()->getLeftBgPrimary(),

            'right_head_font'  => $this->getPageInstance()->getRightHeadFont(),
            'right_head_bg'    => $this->getPageInstance()->getRightHeadBg(),
            'right_font_primary' => $this->getPageInstance()->getRightFont(),
            'right_bg_primary' => $this->getPageInstance()->getRightBgPrimary(),

            'top_head_font'  => $this->getPageInstance()->getTopHeadFont(),
            'top_head_bg'    => $this->getPageInstance()->getTopHeadBg(),
            'top_font_primary' => $this->getPageInstance()->getTopFont(),
            'top_bg_primary' => $this->getPageInstance()->getTopBgPrimary(),
        ];

        return $colourList;
    }

    /**
     * Create a new ePortfolio page with title and template
     *
     * "This simply copies a bunch of rows, changing 'eportfolio_template' to 'eportfolio'...quite simple, really."
     *
     * @param string $pageTitle
     * @param int $templateID
     */
    public function createEportfolioFromTemplate($pageTitle, $templateID)
    {
        $userId = $this->getUserId();

        /* Retrieve template data
         */
        $template = $this->templateMapper->findById($templateID);

        /* Compile an eportfolioConfig record
         */
        $page = $this->pageMapper->createModel();
        $page->setOwner($this->userMapper->createModel($userId));
        $page->setPageTitle($pageTitle);
        $page->setTemplate($templateID);
        $page->setLeftColumnWidth($template->getLeftColumnWidth());
        $page->setRightColumnWidth($template->getRightColumnWidth());
        $defaultHeadingColours = $this->getDefaultHeadingColours();
        $page->setTopHeadFont($defaultHeadingColours['head_font']);
        $page->setTopHeadBg($defaultHeadingColours['head_bg']);
        $page->setTopFont(Config::get('homepage_colour_font'));
        $page->setTopBgPrimary(Config::get('homepage_colour_bg_primary'));
        $page->setLeftHeadFont($defaultHeadingColours['head_font']);
        $page->setLeftHeadBg($defaultHeadingColours['head_bg']);
        $page->setLeftFont(Config::get('homepage_colour_font'));
        $page->setLeftBgPrimary(Config::get('homepage_colour_bg_primary'));
        $page->setRightHeadFont($defaultHeadingColours['head_font']);
        $page->setRightHeadBg($defaultHeadingColours['head_bg']);
        $page->setRightFont(Config::get('homepage_colour_font'));
        $page->setRightBgPrimary(Config::get('homepage_colour_bg_primary'));
        $this->pageMapper->save($page);

        /* Remember the page that was just created, because we'll apply
         * permissions to it after it gets created
         */
        $this->setPageId($page->getPageId());

        // retrieve, rearrange and reinsert the componentry
        $cim       = Component_InstanceMapper::getInstance();
        $ccm       = Component_ConfigMapper::getInstance();
        $instances = $cim->findByContext($this->getContext() . '_template', $templateID);

        if (count($instances)) {
            foreach ($instances as $inst) {
                /* @var $inst Component_Instance */
                // preserve the config
                $configs = $inst->getConfig();

                /* unset($inst->id);
                 * This throws notices in provisioning because $inst->id is expected to exist
                 * but here, has been unset.
                 */
                $inst->setId(null);
                $inst->getConfig();

                $inst->setModule($this->getContext());
                $inst->setIdentifier($this->getPageId());
                $ids = $cim->save($inst);
                $id  = $ids[0];

                if (count($configs)) {
                    foreach ($configs as $c) {
                        /* @var $c Component_Config */
                        if ($c->getId()) {
                            $conf = new Component_Config();
                            $conf
                                ->setInstanceId($id)
                                ->setValue($c->getValue())
                                ->setConfigDefinitionId($c->getId());
                            $ccm->save($conf);
                        }
                    }
                }
            }
        }
    }

    /**
     * Delete an eportfolio
     *
     * Contents storted in other tables are retained
     */
    public function delete()
    {
        $allComponents = Component_InstanceMapper::getInstance()->findByColumnList([
            'module'     => 'eportfolio',
            'identifier' => $this->getPageId(),
        ]);

        /* Remove all the components
         */
        $allInstanceIds = [];

        if ($allComponents->count()) {
            foreach ($allComponents as $currentComponent) {
                /* @var $currentComponent Component_Instance */
                /* Remember this id so we can get their configs again later
                 */
                $allInstanceIds[] = $currentComponent->getId();

                Component_InstanceMapper::getInstance()->delete($currentComponent);
            }

            /* Remove all the components' configs
             */
            if (count($allInstanceIds)) {
                $allConfigs = Component_ConfigMapper::getInstance()->findIn('component_instance_id', $allInstanceIds);

                if ($allConfigs->count()) {
                    foreach ($allConfigs as $currentConfig) {
                        Component_ConfigMapper::getInstance()->delete($currentConfig);
                    }
                }
            }
        }

        /* Remove the page's permissions
         */
        $allPermissions = Eportfolio\PermissionMapper::getInstance()->findByColumnList([
            'page_id' => $this->getPageId(),
        ]);

        if ($allPermissions->count()) {
            foreach ($allPermissions as $currentPermission) {
                Eportfolio\PermissionMapper::getInstance()->delete($currentPermission);
            }
        }

        /* Remove the page itself
         */
        $pageInstance = $this->pageMapper->findById($this->getPageId());
        $this->pageMapper->delete($pageInstance);

        /* Redirect back to the profile
         */
        SitePage::localRedir('/eportfolio/' . $this->getUserId() . '/profile');
    }

    /**
     * Save colours for individual pages
     *
     * @param array $listColor
     * @return void
     */
    public function saveDisplayProperties($listColor)
    {
        // merge the provided colour list with the existing colour list, so that any missing values are not overwritten as null
        $listColor = array_replace($this->getColorList(), $listColor);

        $page = $this->pageMapper->findById($this->getPageId());
        $page->setTopHeadFont($listColor['top_head_font']);
        $page->setTopHeadBg($listColor['top_head_bg']);
        $page->setTopFont($listColor['top_font_primary']);
        $page->setTopBgPrimary($listColor['top_bg_primary']);
        $page->setLeftHeadFont($listColor['left_head_font']);
        $page->setLeftHeadBg($listColor['left_head_bg']);
        $page->setLeftFont($listColor['left_font_primary']);
        $page->setLeftBgPrimary($listColor['left_bg_primary']);
        $page->setRightHeadFont($listColor['right_head_font']);
        $page->setRightHeadBg($listColor['right_head_bg']);
        $page->setRightFont($listColor['right_font_primary']);
        $page->setRightBgPrimary($listColor['right_bg_primary']);
        $this->pageMapper->save($page);
    }

    /* Getters/Setters
     */

    /**
     * Set user id
     *
     * @param int $userId
     */
    private function setUserId($userId)
    {
        $this->userId = $userId;
    }

    /**
     * Get user id
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * Get context
     *
     * @return string
     */
    private function getContext()
    {
        return $this->context;
    }

    /**
     * Set page id
     *
     * @param int $pageId
     */
    private function setPageId($pageId)
    {
        $this->pageId = $pageId;
    }

    /**
     * Get page id
     *
     * @return int
     */
    public function getPageId()
    {
        return $this->pageId;
    }

    /**
     * Set page instance
     *
     * @param \Eportfolio\Page $pageInstance
     */
    private function setPageInstance($pageInstance)
    {
        $this->pageInstance = $pageInstance;
    }

    /**
     * Get page instance
     *
     * @return \Eportfolio\Page
     */
    public function getPageInstance()
    {
        return $this->pageInstance;
    }
}
