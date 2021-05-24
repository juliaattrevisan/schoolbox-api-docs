<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;

/**
 * @method Eportfolio_Page_Instance|null getContext()
 * @method Eportfolio_Page_Instance|null getSource()
 */
abstract class Component_Eportfolio_ControllerAbstract extends Component_ControllerAbstract
{
    /**
     * A mapper instance, for polymorphism
     *
     * @var \Schoolbox\Mapper
     */
    private $mapper;

    /**
     * Apply permissions here
     *
     * @param Eportfolio_Page_Instance|null $context Null when used in admin ePortfolio screen
     */
    public function setup($context = null)
    {
        parent::setup($context);

        if ($this->getUser()->isSuperuser()) {
            $this->setContentPermission(UserAccess::ACCESS_FULL);
        } elseif (!isset($context)) {
            $this->setContentPermission(UserAccess::ACCESS_DENY);
        } else {
            $this->setContentPermission($context->getPermission());
        }
        $this->setContextPermission($this->getContentPermission());

        /* Check primary key is being used correctly!
         */
        $this->getSitePage()->setVar('ID', isset($context) ? $context->getUserId() : -1);
    }

    /**
     * Get template
     *
     * @param string $templateName
     * @param string $templatePath
     * @return string
     */
    protected function getTemplate($templateName = '', $templatePath = '')
    {
        if (empty($templatePath)) {
            $templatePath = "component/eportfolio";
        }

        return parent::getTemplate($templateName, $templatePath);
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
        parent::parseConfig($targetHandle, $is_for_template);

        //Show this many actions in nav bar if user has write access
        $this->setVar('COMPONENT_ACTION_COUNT', $this->hasWriteAccess() ? 2 : 1);

        $this->getSitePage()->setBlock($targetHandle, 'drag_handle', 'drag_handle_block');

        if ($this->hasContextWriteAccess()) {
            $this->getSitePage()->parse('drag_handle_block', 'drag_handle');
        } else {
            $this->getSitePage()->setVar('drag_handle_block', '');
        }

        return true;
    }

    /**
     * Split this out so in ePortfolio you can determine which config elements get rendered
     *
     * @param Component_Config_Abstract $conf
     * @param string $key
     * @param SitePage $page
     * @param array $params
     */
    protected function parseConfigInstance($conf, $key, $page, $params)
    {

        /* Hide Purpose when not in admin
         */
        if (preg_match('#^/adminv2/#', $_SERVER['REQUEST_URI'])) {
            $exclusions = array();
        } else {
            $exclusions = array('description');
        }

        /* Render config instance
         */
        if (!in_array($key, $exclusions)) {
            $templateFile = "component/config/$key.html";
            $page->setFile($key, $templateFile);
            $conf->parse($page, $key, $key, Scrub::text($params['value']), $this);

            if ($key == 'componentname') {
                /* Get the config so we have the name
                 */
                $configInstance = Component_ConfigMapper::getInstance()->findByColumnList(
                    array(
                        'component_instance_id'          => $this->getInstance()->getId(),
                        'component_config_definition_id' => $this->getInstance()->getDefinition()->getId()
                    )
                );

                if ($configInstance->count()) {
                    $this->setVar(
                        array(
                            'COMPONENT_NAME'        => $configInstance->first()->getValue(),
                            'COMPONENT_CONFIG_NAME' => $configInstance->first()->getValue(),
                        )
                    );
                }
            }

            $page->parse('CONFIG_ELEMENT_CONTENT', $key);
            $page->setVar('CONFIG_DISPLAY', $params['label'] ? '' : ' style="display:none;"');
            $page->parse('config_element_block', 'config_element', true);
        }
    }


    /**
     * For now, Eportfolio Components are unable to use Alt Data Sources
     *
     * @throws DomainException
     */
    protected function renderAltSourceDenied()
    {
        throw new DomainException('Eportfolio components do not currently allow alternative data sources');
    }

    /**
     * For now, Eportfolio Components are unable to use Alt Data Sources
     *
     * @throws DomainException
     */
    protected function renderAltSourceDeleted()
    {
        throw new DomainException('Eportfolio components do not currently allow alternative data sources');
    }

    /* Getters/Setters
     */

    /**
     * @param \Schoolbox\Mapper $mapper
     */
    protected function setMapper($mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * @return \Schoolbox\Mapper
     */
    protected function getMapper()
    {
        return $this->mapper;
    }

    /**
     * @param Eportfolio_Page_Instance|null $context
     * @return static
     */
    protected function setContext($context = null)
    {
        parent::setContext($context);
        return $this;
    }

    /**
     * Sets the Eportfolio_Page_Instance this component draws its data from
     *
     * @param Eportfolio_Page_Instance|null $source
     * @return static
     */
    public function setSource($source = null)
    {
        parent::setSource($source);
        return $this;
    }
}
