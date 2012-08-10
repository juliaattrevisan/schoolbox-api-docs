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
     * @var int
     */
    protected $permission = 0;

    /**
     * @var array
     */
    protected $sortFields = null;

    /**
     * @var array
     */
    protected $filterFields = null;

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
    public function parseConfig() {

        $page = $this->sitepage;

        $page->setVar('CONFIGURATION', '');

        $config = $this->instance->getConfigArray();

        // DETERMINE WHAT TO DISPLAY

        // there is configuration
        if(is_array($config)) {
            // have write access
            if($this->permission > READ_ACCESS) {
                // display full configuration block
                list($hasWriteAccess, $processConfig, $displayConfig) = array(true, true, true);
            }
            // do not have write access
            else {
                // parse configuration display variables for readonly
                list($hasWriteAccess, $processConfig, $displayConfig) = array(false, true, false);
            }
        }
        // there is no configuration
        else {
            // have write access
            if($this->permission > READ_ACCESS) {
                // display delete option
                list($hasWriteAccess, $processConfig, $displayConfig) = array(true, false, false);
            }
            // do not have write access
            else {
                // no config, no write access: do nothing
                list($hasWriteAccess, $processConfig, $displayConfig) = array(false, false, false);
            }
        }

        // if all 3 above vars are false, bug out
        if(!($hasWriteAccess || $processConfig || $displayConfig)) {
            return false;
        }

        $templateFiles = array(
            'homepage'            => 'component/config/_wrapper.html',
            'eportfolio'          => 'component/config/_wrapper.html',
            'dashboard'           => 'component/config/_templatewrapper.html',
            'homepage_template'   => 'component/config/_templatewrapper.html',
            'eportfolio_template' => 'component/config/_templatewrapper.html',
        );

        if(isset($templateFiles[$this->instance->module])) {
            $template = $templateFiles[$this->instance->module];
        }
        // fallback
        else {
            $template = 'component/config/_wrapper.html';
        }

        $page->setFile('configTpl', $template);

        $page->setBlock('configTpl', 'config_element', 'config_element_block');
        $page->setVar('config_element_block','');

        if($processConfig) {
            // every config key has a corresponding controller and template to be called
            foreach($config as $key => $params) {

                $page->setVar('CONFIG_ELEMENT_LABEL', $params['label']);

                // attempt to build a configuration instance from the key provided

                $fields = array();
                if($this->sortFields) {
                    $fields['sort'] = $this->sortFields;
                }
                if($this->filterFields) {
                    $fields['filter'] = $this->filterFields;
                }

                if($conf = Component_Config_Factory::build($key, $this->context, $fields)) {
                    $templateFile = "component/config/$key.html";
                    $page->setFile($key, $templateFile);
                    $conf->parse($page, $key, $key, $params['value']);
                    $page->parse('CONFIG_ELEMENT_CONTENT', $key);
                    $page->parse('config_element_block', 'config_element', true);
                }

                $page->setVar('CONFIG_ELEMENT_CONTENT', '');
            }

        }
        else {

        }

        // now clear things we don't want seen
        if(!$displayConfig) {
            $page->setVar('settings_link', '');
            $page->setVar('settings_panel', '');
        }

        if(!$hasWriteAccess) {
            $page->setVar('config_element_block', '');
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

        $return = array();

        foreach($this->config as $item) {
            $return[$item['label']] = $item['value'];
        }

        return $return;
    }

    public function getPageTemplate() {
        return $this->sitepage->template;
    }

}
