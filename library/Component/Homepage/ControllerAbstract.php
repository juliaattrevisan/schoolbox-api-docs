<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Resource\Folder;

abstract class Component_Homepage_ControllerAbstract extends Component_ControllerAbstract
{
    /**
     * @param Folder\Model $context If provided, the folder to render this component in
     * @param array        $config
     */
    public function setup(Folder\Model $context = null, array $config = array())
    {
        if (!$context instanceof Folder\Model) {
            $context = new Folder\Model();
        }
        parent::setup($context, $config);
        $g = new Group();
        // Content permission: based on alt source if it's there
        // Control permission: always based on current context
        if (!empty($config['src']['value'])) {
            $sourceId = $config['src']['value'];
        } else {
            $sourceId = $this->getContext()->getId();
        }
        $this->permission        = min($g->getPermission($sourceId), $this->getMaximumPermission());
        $this->controlPermission = min($g->getPermission($this->getContext()->getId()), $this->getMaximumPermission());
        $this->sitepage->setVar('ID', $this->getContext()->getId());

        $this->postConstruct();
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
        list ($hasWriteAccess, $processConfig) = $this->determineConfigPermissions($this->instance->getConfigArray());

        $this->setupConfigRendering($targetHandle);

        /* If you don't have write access, just render the collapse and go away
         */
        if (!$hasWriteAccess) {
            $this->renderConfigElements(false, $is_for_template);

            /* Detroit
             */
            $this->applyCollapsedState();

            $this->sitepage->parse('settings_collapse_block', 'settings_collapse');
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
                    $this->sitepage->parse('settings_collapse_block', 'settings_collapse');
                }
                $this->sitepage->parse('settings_link_block', 'settings_link');
                $this->sitepage->parse('settings_close_block', 'settings_close');
            }
        }

        $this->sitepage->parse($targetHandle, 'configTpl', false);

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
}
