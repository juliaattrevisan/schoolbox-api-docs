<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Component_Homepage_ControllerAbstract extends Component_ControllerAbstract
{

    public function setup($context, array $config)
    {
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
     * Returns the css class to hide an element
     *
     * @return string
     */
    protected function getDefaultCollapsedState() {
        if ($this->getConfig('collapse')) {
            return 'hide';
        }

        return '';
    }

    /**
     * Apply current collapsed state setting
     */
    protected function applyCollapsedState() {
        $this->setVar(array(
            'CONFIG_COLLAPSED_CLASS' => $this->getDefaultCollapsedState()
        ));
    }
}
