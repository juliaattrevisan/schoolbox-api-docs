<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Resource\Folder\Model as Folder;

abstract class Component_Homepage_ControllerAbstract extends Component_ControllerAbstract
{
    /**
     * Permission setup
     *
     * @param Folder|null $context null when called from admin homepage config screen
     * @param array|null  $config
     */
    public function setup(Folder $context = null, array $config = array())
    {
        parent::setup($context, $config);

        $accessor = \Schoolbox\Permission\Access\Access::getInstance();
        // Content permission: based on alt source if it's there
        // Control permission: always based on current context
        $this->setContentPermission(min($accessor->getFolderAccess($this->getUser(), [$this->getSource()->getId()]), $this->getMaximumPermission()));
        $this->setContextPermission(min($accessor->getFolderAccess($this->getUser(), [$this->getContext()->getId()]), $this->getMaximumPermission()));
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
        list ($hasWriteAccess, $processConfig) = $this->determineConfigPermissions($this->getInstance()->getConfigArray());

        $this->setupConfigRendering($targetHandle);

        /* If you don't have write access, just render the collapse and go away
         */
        if (!$hasWriteAccess) {
            $this->renderConfigElements(false, $is_for_template);

            /* Detroit
             */
            $this->applyCollapsedState();

            $this->getSitePage()->parse('settings_collapse_block', 'settings_collapse');
        }
        else {
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

                if(!$is_for_template) {
                    // We don't show the collapse option when building templates
                    $this->getSitePage()->parse('settings_collapse_block', 'settings_collapse');
                }
                $this->getSitePage()->parse('settings_link_block', 'settings_link');
                $this->getSitePage()->parse('settings_close_block', 'settings_close');
            }
        }

        $this->getSitePage()->parse($targetHandle, 'configTpl', false);

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
     * @return string
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
                'CONFIG_COLLAPSED_ICON' => $state['icon'],
                'CONFIG_COLLAPSED_CLASS' => $state['class'],
                'CONFIG_COLLAPSED_STATE' => $state['data'],
            )
        );
    }

    /* Getters / Setters
     */

    /**
     * @return Folder
     */
    public function getContext()
    {
        return parent::getContext();
    }

    /**
     * @param Folder|null $context null when used on admin homepage config screen
     * @return static
     */
    protected function setContext(Folder $context = null)
    {
        parent::setContext($context);
        return $this;
    }

    /**
     * @return Folder|null
     */
    public function getSource()
    {
        return parent::getSource();
    }

    /**
     * @param Folder|null $source
     * @return static
     */
    public function setSource(Folder $source = null)
    {
        parent::setSource($source);
        return $this;
    }
}
