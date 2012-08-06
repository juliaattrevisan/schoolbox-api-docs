<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
abstract class Component_ControllerAbstract {

    /**
     * @var Component_Instance
     */
    protected $instance;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var SitePage
     */
    protected $sitepage;

    /**
     * @var array
     */
    protected $context;

    /**
     * @var array
     */
    protected $config;

    /**
     * @param Component_Instance $instance
     * @param User $user
     * @param SitePage $sitepage
     */
    public function __construct (Component_Instance $instance, User $user, SitePage $sitepage) {
        $this->instance = $instance;
        $this->user = $user;
        $this->sitepage = $sitepage;
        $this->config = $instance->getConfigArray();
    }


    /**
     * Method to provide global config details to the component
     * @param array $config
     */
    public function setup(array $context, array $config) {
        $this->context = $context;
        $this->config = $config;
    }


    /**
     * Prepares data for the render function. Business logic.
     *
     * @abstract
     * @return void
     */
    abstract function execute();

    /**
     * @param $templateHandle
     * @param string $templateName
     * @param string $templatePath
     * @return bool
     */
    protected function loadTemplate ($templateHandle, $templateName = '', $templatePath = '') {
        $templateFile = $this->getTemplate($templateName, $templatePath);
        if (!$this->sitepage->setFile($templateHandle, $templateFile)) {
            return false;
        }

        $output = array(
            'COMPONENT_INSTANCE_ID' => $this->instance->id,
            'COMPONENT_HANDLE'      => $templateHandle . '_' . $this->instance->id
        );

        $this->setVar($output);

        return true;
    }

    protected function getTemplate ($templateName = '', $templatePath = '') {
        if ( empty($templateName) ) {
            /* Break apart class name by underscores, get only last segment, remove last 10 characters then send it to lowercase */
            $templateName = ucfirst( substr( array_pop( explode('_', $this->definition->class) ), 0, -10 ) );
        }

        if ( empty($templatePath) ) {
            $templatePath = "component";
        }

        $templateFile = "$templatePath/$templateName.html";

        return $templateFile;
    }

    /**
     * Renders view using Component_AbstractController::$templateObject.
     *
     * @param String $templateHandle
     * @return boolean
     */
    protected function renderTemplate ($templateHandle) {

        if ( !$this->loadTemplate($templateHandle) ) {
            return false;
        }
        return true;
    }

    /**
     * Allows calls to the sitepage object in this style:
     * $this->setBlock();
     * Exists to ease porting of existing components.
     *
     * @param String $method
     * @param Array $argumentList
     * @return mixed
     */
    public function __call($method, $argumentList) {

        if ( method_exists($this, $method) ) {
            $object = $this;
        }
        elseif ( method_exists($this->sitepage, $method) ) {
            $object = $this->sitepage;
        }

        if ( !empty($object) ) {
            $rm = new ReflectionMethod($object, $method);

            return $rm->invokeArgs($object, $argumentList);
        } else {
            return $this->$method();
        }

    }

    public function __get ($property) {
        if ( isset($this->instance->$property) ) {
            return $this->instance->$property;
        }
        else {
            return $this->$property;
        }
    }


    /**
     * @return bool
     */
    protected function parseConfig() {

        $page = $this->sitepage;

        $page->setVar('CONFIGURATION', '');

        $config = $this->instance->getConfigArray();

        // if there is no configuration, or you're not allowed to see it, hide and run
        if(!is_array($config) || $this->permission < WRITE_ACCESS) {
            return false;
        }

        $page->setFile('configTpl', 'component/config/_wrapper.html');
        $page->setBlock('configTpl', 'config_element', 'config_element_block');
        $this->setVar('config_element_block','');

        // every config key has a corresponding controller and template to be called
        foreach($config as $key => $params) {

            $page->setVar('CONFIG_ELEMENT_LABEL', $params['label']);

            // attempt to build a configuration instance from the key provided
            if($conf = Component_Config_Factory::build($key)) {
                $templateFile = "component/config/$key.html";
                $page->setFile($key, $templateFile);
                $conf->parse($page, $key, $key, $params['value']);
                $page->parse('CONFIG_ELEMENT_CONTENT', $key);
                $page->parse('config_element_block', 'config_element', true);
            }

            $page->setVar('CONFIG_ELEMENT_CONTENT', '');
        }

        $page->parse('CONFIGURATION', 'configTpl', false);

        return true;
    }

    /**
     * @param string $key
     * @return mixed
     */
    protected function getContext($key = '') {
        if($key) {
            return isset($this->context[$key]) ? $this->context[$key] : false;
        }
        return $this->context;
    }

    /**
     * @param string $key
     * @return mixed
     */
    protected function getConfig($key = '', $attr = 'value') {
        if($key) {
            return isset($this->config[$key][$attr]) ? $this->config[$key][$attr] : false;
        }
        return $this->config;
    }

    public function getPageTemplate() {
        return $this->sitepage->template;
    }

}
