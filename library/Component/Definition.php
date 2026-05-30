<?php

/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Realtime\Message\NS;

class Component_Definition
{
    // Constants defining modules where components are allowed

    public const MODULE_DASHBOARD = 'dashboard';
    public const MODULE_HOMEPAGE   = 'homepage';
    public const MODULE_EPORTFOLIO = 'eportfolio';

    public const MODULE_HOMEPAGE_TEMPLATE = 'homepage_template';

    /**
     * Palette icon names keyed by the legacy masonry CSS class.
     *
     * A small number of homepage components still need dedicated sbx-icon glyphs,
     * so those entries use the closest existing icon for now.
     *
     * @var array<string, string>
     */
    private const PALETTE_ICON_NAME_MAP = [
        'Schoolbox_Blog_Homepage_Component_Controller' => 'comment',
        'Schoolbox_Calendar_Component_Homepage_Controller' => 'calendar',
        'Component_Homepage_CountdownController' => 'countdown',
        'Schoolbox_Comms_News_Component_Homepage_Controller' => 'news',
        'Schoolbox_Fixtures_Component_Homepage_Controller' => 'sport',
        'Schoolbox_Resource_Textbox_Component_Homepage_Controller' => 'textbox',
        'Schoolbox_Tile_Component_HomepageTileController' => 'tiles',
        'Component_Homepage_ChatController' => 'chat',
        'Component_Homepage_EtherpadController' => 'collab-docs',
        'Component_Homepage_ForumListController' => 'forum',
        'Schoolbox_Resource_GoogleFile_Component_Homepage_Controller' => 'google-drive',
        'Component_Homepage_ManagersController' => 'group-managers',
        'Component_Homepage_MembersController' => 'group',
        'Component_Homepage_PollController' => 'poll',
        'Component_Homepage_SocialStreamController' => 'social-stream',
        'Component_Homepage_SurveyController' => 'surveys',
        'Component_Homepage_WikiController' => 'wiki',
        'Component_Homepage_ClassListController' => 'class-list',
        'Component_Homepage_ManageClassController' => 'class-list',
        'Component_Homepage_TeachersController' => 'teacher',
        'Schoolbox_Learning_Assessment_Component_Homepage_Builder_Controller' => 'course-builder',
        'Schoolbox_Learning_Assessment_Component_Homepage_Outline_Controller' => 'course-outline',
        'Schoolbox_Curriculum_Component_CurriculumDetailsController' => 'curriculum-details',
        'Schoolbox_Curriculum_Component_CurriculumOverviewController' => 'curriculum',
        'Schoolbox_Learning_Assessment_DialogAgent_Component_Homepage_Controller' => 'dialog-agent',
        'Schoolbox_Learning_Assessment_DueWork_Component_Homepage_Controller' => 'due-work',
        'Schoolbox_Learning_Component_Homepage_LearningMomentsController' => 'share',
        'Component_Homepage_DropBoxController' => 'drop-box',
        'Schoolbox_Learning_Assessment_Quiz_Component_Homepage_Controller' => 'quiz',
        'Component_Homepage_RubricController' => 'criteria',
        'Schoolbox_Learning_Assessment_Task_Component_Homepage_Controller' => 'task',
        'Schoolbox_Learning_Component_Homepage_CurrentLessonPlanController' => 'lesson-plan',
        'Component_Homepage_AudioController' => 'audio',
        'Component_Homepage_ClickviewController' => 'click-view',
        'Schoolbox_LTI_Component_Homepage_Controller' => 'lti',
        'Schoolbox_Learning_Assessment_Lti_Component_Homepage_Controller' => 'lti',
        'Schoolbox_Geosnapshot_Component_Homepage_Controller' => 'geosnapshot',
        'Component_Homepage_ImageController' => 'image',
        'Component_Homepage_ImageGalleryController' => 'image-gallery',
        'Component_Homepage_SlideshowController' => 'image-slideshow',
        'Component_Homepage_RandImageController' => 'random-image',
        'Component_Homepage_VideoController' => 'video',
        'Component_Homepage_BreadcrumbController' => 'breadcrumbs',
        'Component_Homepage_FileListController' => 'files',
        'Component_Homepage_FolderListController' => 'folder',
        'Component_Homepage_ManageFolderController' => 'folder',
        'Component_Homepage_LinkListController' => 'link',
        'Component_Homepage_FeedController' => 'homepagefeed',
        'Component_Homepage_PinterestController' => 'pinterest',
        'Component_Homepage_PodcastController' => 'podcast',
        'Component_Homepage_RSSController' => 'rss',
        'Schoolbox_Twitter_Component_Homepage_Controller' => 'twitter',
        'Component_Homepage_WolframController' => 'wolfram-alpha',
        'Schoolbox_Intellischool_Component_Homepage_Controller' => 'intellischool',
    ];

    /** @var int $id */
    protected $id;

    /** @var string $name */
    protected $name;

    /** @var string $class */
    protected $class;

    /** @var string $description */
    protected $description;

    /** @var string $module */
    protected $module;

    /** @var bool $enabled */
    protected $enabled;

    /** @var bool $visible */
    protected $visible;

    /** @var bool $multiple */
    protected $multiple;

    /** @var string $componentCategory */
    protected $componentCategory;

    /** @var NS|null $namespace Message namespace this component resides in (may be null) */
    protected $namespace;

    /**
     * Suffix of message namespace this component resides in (may be null)
     * Used for more direct sourcing of messages, when forwarding from a folder
     * to another folder which uses this one as an alt-source.
     *
     * @var string|null
     */
    protected $namespaceSuffix;

    /** @var string $componentCategoryName The name of this component's category (as opposed to its ID) */
    protected $componentCategoryName;

    /** @var int $instanceCount An unmapped count of instances of this item */
    protected $instanceCount = 0;

    /** @var string $configCopy CSV list of configurations to copy from template during creation */
    protected $configCopy;

    /**
     * Overridable function to hide component from palette
     *
     * @param string $context The component context we are in (homepage, eportfolio, dashboard)
     * @param int $contextId The context ID we are current in (folderId, pageId, dashboardId)
     * @return bool
     */
    public function renderInPalette($context, $contextId)
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isHomepageModule(): bool
    {
        return $this->getModule() === self::MODULE_HOMEPAGE;
    }

    /**
     * @return bool
     */
    public function isDashboardModule(): bool
    {
        return $this->getModule() === self::MODULE_DASHBOARD;
    }

    /**
     * @return bool
     */
    public function isEportfolioModule(): bool
    {
        return $this->getModule() === self::MODULE_EPORTFOLIO;
    }

    /**
     * Increments the instance count for this definition.
     *
     * @return void
     */
    public function incrementInstanceCount()
    {
        $this->instanceCount++;
    }

    /**
     * Gets this component's class, usable as a CSS class.
     *
     * @return string
     */
    public function getCssClass(): string
    {
        return str_replace('\\', '_', $this->getClass());
    }

    /**
     * Gets the sbx-icon name for palette rendering.
     */
    public function getPaletteIconName(): string
    {
        return self::PALETTE_ICON_NAME_MAP[$this->getCssClass()] ?? 'components';
    }

    // Getters & Setters

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param string $class
     * @return self
     */
    public function setClass($class)
    {
        $this->class = $class;
        return $this;
    }

    /**
     * @return string
     */
    public function getComponentCategory()
    {
        return $this->componentCategory;
    }

    /**
     * @param string $componentCategory
     * @return self
     */
    public function setComponentCategory($componentCategory)
    {
        $this->componentCategory = $componentCategory;
        return $this;
    }

    /**
     * @return string
     */
    public function getComponentCategoryName()
    {
        return $this->componentCategoryName;
    }

    /**
     * @param string $componentCategoryName
     * @return self
     */
    public function setComponentCategoryName($componentCategoryName)
    {
        $this->componentCategoryName = $componentCategoryName;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return self
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getConfigCopy()
    {
        return $this->configCopy;
    }

    /**
     * @param string $configCopy
     * @return self
     */
    public function setConfigCopy($configCopy)
    {
        $this->configCopy = $configCopy;
        return $this;
    }

    /**
     * @return string
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * @param string $module
     * @return self
     */
    public function setModule($module)
    {
        $this->module = $module;
        return $this;
    }

    /**
     * @return bool
     */
    public function getMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * @param bool $multiple
     * @return self
     */
    public function setMultiple(bool $multiple): self
    {
        $this->multiple = $multiple;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     * @return self
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function getVisible(): bool
    {
        return $this->visible;
    }

    /**
     * @param bool $visible
     * @return self
     */
    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;
        return $this;
    }

    /**
     * @return NS|null
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param NS|null $namespace
     * @return self
     */
    public function setNamespace(?NS $namespace = null)
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getNamespaceSuffix(): ?string
    {
        return $this->namespaceSuffix;
    }

    /**
     * @param null|string $namespaceSuffix
     * @return static
     */
    public function setNamespaceSuffix(?string $namespaceSuffix): self
    {
        $this->namespaceSuffix = $namespaceSuffix;
        return $this;
    }

    /**
     * @return int
     */
    public function getInstanceCount()
    {
        return $this->instanceCount;
    }

    /**
     * @param int $instanceCount
     * @return self
     */
    public function setInstanceCount($instanceCount)
    {
        $this->instanceCount = $instanceCount;
        return $this;
    }
}
