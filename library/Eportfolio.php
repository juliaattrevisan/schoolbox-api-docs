<?php

use Eportfolio\PageMapper;
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

    /* Used to remove homepage components and reset template
     */
    const REMOVE_EPORTFOLIO_TEMPLATE_ID = -1;
    const KEEP_EXISTING_EPORTFOLIO      = 0;

    /**
     * An instance of dbManager
     *
     * @var DeprecatedDatabase|null
     */
    private $db = null;

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
     * All eportfolio_template records
     *
     * @var array
     */
    private $allTemplates = [];

    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * @var PageMapper
     */
    private $pageMapper;

    /**
     * Constructor
     *
     * @param $userId
     * @param $pageId
     */
    public function __construct($userId, $pageId)
    {
        $this->setDb(DeprecatedDatabase::getInstance());
        $this->setUserId($userId);
        $this->setPageId($pageId);

        // TODO inject these mappers for testability
        $this->userMapper = UserMapper::getInstance();
        $this->pageMapper = PageMapper::getInstance();

        /* Preload settings for user-built pages
         *
         * Otherwise they are hardcoded pages and we don't need
         * this extra information
         */
        if (is_numeric($pageId)) {
            $pageInstance = $this->pageMapper->findById($pageId);

            $this->setPageInstance($pageInstance);
            $this->setAllTemplates($this->fetchAllTemplates());
        } /* Spawn a stub one to make the interfaces happy
		 * (was easier, but dirtier, to use an empty array)
		 */
        else {
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
     * @param $value
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
     * @return array
     */
    public function fetchAllTemplates()
    {
        $allTemplates = [];

        $query = "
			SELECT id, name
			FROM {$this->getContext()}Template
			ORDER BY id
		";

        $result = $this->getDb()->getAll($query);

        /* Do some transpositionary reflex
         */
        if (is_array($result) && count($result)) {
            foreach ($result as $currentTemplate) {
                $allTemplates[$currentTemplate['id']] = $currentTemplate['name'];
            }
        }

        return $allTemplates;
    }

    /**
     * Get a template name by its id, from the entire set
     *
     * @param $id
     */
    public function getTemplateNameById($id)
    {
        return $this->allTemplates[$id];
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
     * @param  $left
     * @param  $right
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

        foreach (['top', 'left', 'right'] as $position) {
            $colourSet = array_merge($colourSet, [
                $position . '_head_font'  => Config::get('homepage_colour_head_font'),
                $position . '_head_bg'    => Config::get('homepage_colour_head_bg'),
                $position . '_font'       => Config::get('homepage_colour_font'),
                $position . '_bg_primary' => Config::get('homepage_colour_bg_primary'),
            ]);
        }

        return $colourSet;
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
            'left_font'       => $this->getPageInstance()->getLeftFont(),
            'left_bg_primary' => $this->getPageInstance()->getLeftBgPrimary(),

            'right_head_font'  => $this->getPageInstance()->getRightHeadFont(),
            'right_head_bg'    => $this->getPageInstance()->getRightHeadBg(),
            'right_font'       => $this->getPageInstance()->getRightFont(),
            'right_bg_primary' => $this->getPageInstance()->getRightBgPrimary(),

            'top_head_font'  => $this->getPageInstance()->getTopHeadFont(),
            'top_head_bg'    => $this->getPageInstance()->getTopHeadBg(),
            'top_font'       => $this->getPageInstance()->getTopFont(),
            'top_bg_primary' => $this->getPageInstance()->getTopBgPrimary(),
        ];

        return $colourList;
    }

    /**
     * Create a new ePortfolio page with title and template
     *
     * "This simply copies a bunch of rows, changing 'eportfolio_template' to 'eportfolio'...quite simple, really."
     *
     * @param $pageTitle
     * @param $templateID
     */
    public function createEportfolioFromTemplate($pageTitle, $templateID)
    {
        $userId     = $this->db->quote($this->getUserId());
        $templateID = $this->db->quote($templateID);

        /* Retrieve template data
         */
        $query = "
			SELECT id AS template, left_column_width, right_column_width
			FROM {$this->getContext()}Template
			WHERE id = '{$templateID}'
		";

        $tpl = $this->db->getRow($query);

        /* Compile an eportfolioConfig record
         */
        $eportfolioConfig = [
            'uid'                => $userId,
            'page_title'         => $pageTitle,
            'template'           => $templateID,
            'left_column_width'  => $tpl['left_column_width'],
            'right_column_width' => $tpl['right_column_width'],
            'created_at'         => 'NOW()',
            'updated_at'         => 'NOW()',
        ];

        foreach (['top', 'left', 'right'] as $position) {
            $eportfolioConfig = array_merge($eportfolioConfig, [
                $position . '_head_font'  => Config::get('homepage_colour_head_font'),
                $position . '_head_bg'    => Config::get('homepage_colour_head_bg'),
                $position . '_font'       => Config::get('homepage_colour_font'),
                $position . '_bg_primary' => Config::get('homepage_colour_bg_primary'),
            ]);
        }

        /* Insert it
         */
        $this->db->insert($this->getContext() . '_page', $eportfolioConfig);

        /* Remember the page that was just created, because we'll apply
         * permissions to it after it gets created
         */
        $this->setPageId($this->db->insertID());

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
     * @param $listColor
     * @return resource
     */
    public function saveDisplayProperties($listColor)
    {

        $query = "
			UPDATE {$this->getContext()}_page
			SET
		";

        $stack = [];

        foreach ($listColor as $key => $color) {
            $field = $this->db->quote($key);
            $value = $this->db->quote($color);

            $stack[] = "`{$field}` = '{$value}'";
        }

        $query .= implode(',', $stack);

        $query .= "
			WHERE page_id = {$this->getPageId()}
		";

        return $this->db->query($query);
    }

    /**
     * Create/Update a page template
     *
     * @param $template
     */
    public function savePageTemplate($template)
    {
        $query = "
			SELECT uid
			FROM {$this->getContext()}_page
			WHERE uid = '{$this->getUserId()}'
		";

        $this->db->query($query);

        if ($row = $this->db->getNextArray()) {
            $query = "UPDATE {$this->getContext()}_page SET template = '$template' WHERE uid = '{$this->getUserId()}'";
        } else {
            $query = "INSERT INTO {$this->getContext()}_page (uid, template) VALUES ('{$this->getUserID()}', '$template')";
        }

        $this->db->query($query);
    }

    /* Getters/Setters
     */

    /**
     * @param \DeprecatedDatabase|null $db
     */
    private function setDb($db)
    {
        $this->db = $db;
    }

    /**
     * @return \DeprecatedDatabase|null
     */
    private function getDb()
    {
        return $this->db;
    }

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

    /**
     * @param array $allTemplates
     */
    private function setAllTemplates($allTemplates)
    {
        $this->allTemplates = $allTemplates;
    }

    /**
     * @return array
     */
    private function getAllTemplates()
    {
        return $this->allTemplates;
    }
}
