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
     * Returns the title to display in the component heading so you know
     * when something is collapsed; css class to hide an element; and
     * the data attribute for the toggler to function correctly
     *
     * @return string
     */
    protected function getDefaultCollapsedState() {
        if ($this->getConfig('collapse')) {
            return array(
                'title' => '(collapsed)',
                'class' => 'hide',
                'data' => 'collapsed'
            );
        }

        return array(
            'title' => '',
            'class' => '',
            'data' => 'uncollapsed'
        );
    }

    /**
     * Apply current collapsed state setting
     */
    protected function applyCollapsedState() {
        $state = $this->getDefaultCollapsedState();

        $this->setVar(array(
            'CONFIG_COLLAPSED_TITLE' => $state['title'],
            'CONFIG_COLLAPSED_CLASS' => $state['class'],
            'CONFIG_COLLAPSED_STATE' => $state['data'],
        ));
    }
}
