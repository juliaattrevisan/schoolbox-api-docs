<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Permission\Access\Access;

/**
 * @method Folder|null getContext()
 * @method Folder|null getSource()
 */
abstract class Component_Homepage_ControllerAbstract extends Component_ControllerAbstract
{
    /** @var Access */
    private $access;

    /**
     * @param Component_Instance $instance
     * @param \Schoolbox\User $user
     * @param SitePage $sitepage
     */
    public function __construct(Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);
        $this->access = \Schoolbox\Permission\Access\Access::getInstance();
    }

    /**
     * Permission setup
     *
     * @param Folder|null $context null when called from admin homepage config screen
     */
    public function setup($context = null)
    {
        parent::setup($context);

        $contextId = $this->getContext() ? $this->getContext()->getId() : 0;

        /**
         * on the occasion that getSource is set/valid, but getSource()->getId() is null.
         * getsource has been deleted in the database, but is still being referred to. so also set the source as 0
         * we deal with this in `library/Component/ControllerAbstract.php` when we run `isSourceRenderable`
         * */
        //if getSource->GetId is null (database deleted, but still referred to) also show as deleted.
        if ($this->getSource() && $this->getSource()->getId() != null) {
            $sourceId = $this->getSource()->getId();
        } else {
            $sourceId = 0;
        }

        try {
            // Control permission: always based on current context
            $userAccessLevel = (
                // TODO: Instead of checking permissions using this ternary expression, create an 'isSatisfiedBy()' function
                //       in a new class similar to Comms/News/ValidHasReadMore.php.
                $this->getUser()->hasServicePermission(UserAccess::SERVICE_CMS, UserAccess::ACCESS_FULL)
                ? UserAccess::ACCESS_FULL
                : $this->access->getFolderAccess($this->getUser(), $contextId)
            );
            $this->setContextPermission(min($userAccessLevel, $this->getMaximumPermission()));
            // Content permission: based on alt source if it's there
            $this->setContentPermission(min($this->access->getFolderAccess($this->getUser(), $sourceId), $this->getMaximumPermission()));
        } catch (\Schoolbox\Mapper\Exception $e) {
            /**
             * If the alt source has been deleted, catch that here
             * So that it doesn't escalate to the kernel and 404 the whole page
             */
            $this->setContentPermission(UserAccess::ACCESS_DENY);
        }
    }

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        // Always render (possibly emptyState) if WRITE+ to this page or READ+ to extant content
        return $this->hasContextWriteAccess() || $this->isSourceRenderable();
    }

    /**
     * Parse configuration and settings panels for each component
     *
     * Facade method
     *
     * @param string $targetHandle
     * @param bool $is_for_template
     * @return bool
     */
    public function parseConfig($targetHandle = 'CONFIGURATION', $is_for_template = false)
    {
        // DETERMINE WHAT TO DISPLAY
        // there is configuration
        [$hasWriteAccess, $processConfig] = $this->determineConfigPermissions($this->getInstance()->getConfigArray());

        $this->setupConfigRendering($targetHandle);

        // Write users will have 3 actions, and also will have their actions hidden by default due to the homepage lock feature
        // Read users will have 1 action and no lock feature
        $this->getSitePage()->setBlock('configTpl', 'titlebar_write', 'titlebar_write_block');
        $this->getSitePage()->setBlock('configTpl', 'titlebar_read', 'titlebar_read_block');

        if ($this->hasContextWriteAccess()) {
            $this->setVar([
                'COMPONENT_ACTION_COUNT' => 3,
                'titlebar_read_block' => '',

                // Add data attribute for write users
                'COMPONENT_SETTINGS_DATA' => 'data-component-settings',

                // Note: purposefully not defining the HIDE_CONTROLS_ON_WRITE var in this file,
                //       if we set to nothing here, then component actions will be shown on page load.
            ]);
            $this->getSitePage()->parse('titlebar_write_block', 'titlebar_write');
        } else {
            $this->setVar([
                'COMPONENT_ACTION_COUNT' => 1,
                'titlebar_write_block' => '',

                // Don't apply data attribute for read users, so that their collapse button doesn't get hidden by JS
                'COMPONENT_SETTINGS_DATA' => '',
            ]);
            $this->getSitePage()->parse('titlebar_read_block', 'titlebar_read');
        }

        $this->getSitePage()->setBlock('configTpl', 'drag_handle', 'drag_handle_block');

        /* If you don't have write access, just render the collapse and go away
         */
        if (!$hasWriteAccess) {
            $this->renderConfigElements(false, $is_for_template);

            /* Detroit
             */
            $this->applyCollapsedState();
            $this->getSitePage()->parse('settings_collapse_block', 'settings_collapse');
            $this->getSitePage()->setVar('drag_handle_block', '');
        } else {
            /* If you want to process the config, then do so and display it
             * Also provide a triangle so you can alter them through the ui
             *
             * Otherwise do nothing
             */
            if ($processConfig) {
                $this->renderConfigElements($processConfig, $is_for_template);

                /* Detroit
                 */
                $this->applyCollapsedState();

                if (!$is_for_template) {
                    // We don't show the collapse option when building templates
                    $this->getSitePage()->parse('settings_collapse_block', 'settings_collapse');
                }
                $this->getSitePage()->parse('settings_link_block', 'settings_link');
                $this->getSitePage()->parse('settings_close_block', 'settings_close');
                $this->getSitePage()->parse('drag_handle_block', 'drag_handle');
            }
        }

        $this->getSitePage()->parse($targetHandle, 'configTpl', false);

        /* Decide whether to show Component Title using !showComponentTitle
         * Inverted from "Hide headings from readonly users" via compactMode
         */
        if ($this->getContextPermission() < UserAccess::ACCESS_WRITE && !$this->getConfig('showComponentTitle')) {
            $this->setVar('CONFIGURATION', '');
            $this->setVar('header_block', '');
        }

        return true;
    }

    /**
     * @param string $templateName
     * @param string $templatePath
     * @return string
     */
    protected function getTemplate($templateName = '', $templatePath = '')
    {
        if (empty($templatePath)) {
            $templatePath = "component/homepage";
        }

        return parent::getTemplate($templateName, $templatePath);
    }

    /**
     * The default collapsed state of the content, upon component load
     *
     * Returns the title to display in the component heading so you know
     * when something is collapsed; css class to hide an element; and
     * the data attribute for the toggler to function correctly
     *
     * @return array
     */
    protected function getDefaultCollapsedState()
    {
        if ($this->getConfig('collapse') == 1) {
            return array(
                'icon'  => 'icon-down-arrow',
                'class' => 'hide',
                'data'  => 'collapsed'
            );
        }

        return array(
            'icon'  => 'icon-up-arrow',
            'class' => '',
            'data'  => 'uncollapsed'
        );
    }

    /**
     * Apply current collapsed state setting
     */
    protected function applyCollapsedState()
    {
        $state = $this->getDefaultCollapsedState();

        $this->setVar(
            array(
                'CONFIG_COLLAPSED_ICON'  => $state['icon'],
                'CONFIG_COLLAPSED_CLASS' => $state['class'],
                'CONFIG_COLLAPSED_STATE' => $state['data'],
            )
        );
    }

    /* Getters / Setters
     */

    /**
     * @return Access
     */
    protected function getAccess()
    {
        return $this->access;
    }

    /**
     * @param Folder|null $context null when used on admin homepage config screen
     * @return static
     */
    protected function setContext($context = null)
    {
        parent::setContext($context);
        return $this;
    }

    /**
     * @param Folder|null $source
     * @return static
     */
    public function setSource($source = null)
    {
        parent::setSource($source);
        return $this;
    }
}
