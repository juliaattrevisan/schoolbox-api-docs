<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\User;
use \Schoolbox\Permission\Access\UserAccess;

abstract class Component_Eportfolio_ControllerAbstract extends Component_ControllerAbstract
{
    /**
     * A mapper instance, for polymorphism
     *
     * @var \Schoolbox\Mapper
     */
    protected $mapperClass = null;

    /**
     * Apply permissions here
     *
     * @param Eportfolio_Page_Instance|null $context Null when used in admin ePortfolio screen
      */
    public function setup(Eportfolio_Page_Instance $context = null)
    {
        parent::setup($context);

        /** @var Schoolbox\User $_SESSION['user'] */
        if ($_SESSION['user']->isSuperuser()) {
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
     * Split this out so in ePortfolio you can determine which config elements get rendered
     *
     * @param $conf
     * @param $key
     * @param $page
     * @param $params
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
    protected function renderAltSourceDenied(){
        throw new DomainException('Eportfolio components do not currently allow alternative data sources');
    }

    /**
     * For now, Eportfolio Components are unable to use Alt Data Sources
     *
     * @throws DomainException
     */
    protected function renderAltSourceDeleted(){
        throw new DomainException('Eportfolio components do not currently allow alternative data sources');
    }

    /* Getters/Setters
     */

    /**
     * @param \Schoolbox\Mapper $mapperClass
     */
    protected function setMapperClass($mapperClass)
    {
        $this->mapperClass = $mapperClass;
    }

    /**
     * @return \Schoolbox\Mapper
     */
    protected function getMapperClass()
    {
        return $this->mapperClass;
    }

    /**
     * @return Eportfolio_Page_Instance|null
     */
    public function getContext()
    {
        return parent::getContext();
    }

    /**
     * @param Eportfolio_Page_Instance|null $context
     * @return static
     */
    protected function setContext(Eportfolio_Page_Instance $context = null)
    {
        parent::setContext($context);
        return $this;
    }

    /**
     * Gets the Eportfolio_Page_Instance this component draws its data from
     *
     * @return Eportfolio_Page_Instance
     */
    public function getSource(){
        return parent::getSource();
    }

    /**
     * Sets the Eportfolio_Page_Instance this component draws its data from
     *
     * @param Eportfolio_Page_Instance $source
     * @return static
     */
    public function setSource($source) {
        parent::setSource($source);
        return $this;
    }
}
