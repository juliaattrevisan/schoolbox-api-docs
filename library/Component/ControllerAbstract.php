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
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage) {
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
    protected function loadTemplate($templateHandle, $templateName = '', $templatePath = '') {
        $templateFile = $this->getTemplate($templateName, $templatePath);

        if (!$this->sitepage->setFile($templateHandle, $templateFile)) {
            return false;
        }

        $output = array(
            'COMPONENT_INSTANCE_ID' => $this->instance->getId(),
            'COMPONENT_DEFINITION_ID' => $this->instance->getDefinitionId(),
            'COMPONENT_HANDLE' => $templateHandle . '_' . $this->instance->getId()
        );

        $this->setVar($output);

        return true;
    }

    protected function getTemplate($templateName = '', $templatePath = '') {
        if (empty($templateName)) {
            /* Break apart class name by underscores, get only last segment, remove last 10 characters then send it to lowercase */
            $templateName = ucfirst(substr(array_pop(explode('_', $this->definition->class)), 0, -10));
        }

        if (empty($templatePath)) {
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
    public function renderTemplate($templateHandle) {

        if (!$this->loadTemplate($templateHandle)) {
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
//    public function __call($method, $argumentList) {
//        echo "USING BACKPORTING: " . get_called_class() . "->$method()</br>";
//        if(method_exists($this, $method)) {
//            $object = $this;
//        }
//        elseif(method_exists($this->sitepage, $method)) {
//            $object = $this->sitepage;
//        }
//        if(!empty($object)) {
//            $rm = new ReflectionMethod($object, $method);
//            return $rm->invokeArgs($object, $argumentList);
//        }
//        throw new Exception("Method `$method` not found.");
//    }


    // ENCAPSULATION FOR SITEPAGE METHODS: __CALL() IS SLOW AND CAUSES PARSING ERRORS

    /**
     * @param $varname
     * @return mixed
     */
    public function getVar($varname) {
        return $this->sitepage->getVar($varname);
    }

    /**
     * @param $var
     * @param string $value
     */
    public function setVar($var, $value = '') {
        $this->sitepage->setVar($var, $value);
    }

    /**
     * @param $handle
     * @param $file
     */
    public function setFile($handle, $file) {
        $this->sitepage->setFile($handle, $file);
    }

    /**
     * @param $parent
     * @param $handle
     * @param string $name
     */
    public function setBlock($parent, $handle, $name = '') {
        $this->sitepage->setBlock($parent, $handle, $name);
    }

    /**
     * @param $target
     * @param $handle
     * @param bool $append
     */
    public function parse($target, $handle, $append = false) {
        $this->sitepage->parse($target, $handle, $append);
    }

    public function setJavascript($file) {
        $this->sitepage->setJavascript($file);
    }


    public function __get($property) {
        if (isset($this->instance->$property)) {
            return $this->instance->$property;
        } else {
            return $this->$property;
        }
    }

    /**
     * Parses configuration for display when rendering a template. Some config
     * options don't show on templates, but do when an instance of the template
     * gets shown.
     *
     * @param string $targetHandle
     *
     * @return bool
     */
    public function parseConfigForTemplate($targetHandle = 'CONFIGURATION') {
        return $this->parseConfig($targetHandle, true);
    }

    /**
     * @return bool
     */
    public function parseConfig($targetHandle = 'CONFIGURATION', $is_for_template = false) {

        $page = $this->sitepage;

        $page->setVar($targetHandle, '');

        $config = $this->instance->getConfigArray();

        // DETERMINE WHAT TO DISPLAY

        // there is configuration
        if (is_array($config) && count($config)) {
            // have write access
            if ($this->permission > READ_ACCESS) {
                // display full configuration block
                list($hasWriteAccess, $processConfig, $displayConfig) = array(true, true, true);
            } // do not have write access
            else {
                // parse configuration display variables for readonly
                list($hasWriteAccess, $processConfig, $displayConfig) = array(false, true, false);
            }
        } // there is no configuration
        else {
            // have write access
            if ($this->permission > READ_ACCESS) {
                // display delete option
                list($hasWriteAccess, $processConfig, $displayConfig) = array(true, false, false);
            } // do not have write access
            else {
                // no config, no write access: do nothing
                list($hasWriteAccess, $processConfig, $displayConfig) = array(false, false, false);
            }
        }

        // if all 3 above vars are false, bug out
        if (!($hasWriteAccess || $processConfig || $displayConfig)) {
            return false;
        }

        // if we're building in the context of the admin system, use a different template (_templatewrapper.html),
        // otherwise use the regular one (_wrapper.html)
        if (in_array($this->instance->getModule(), array('dashboard', 'homepage_template', 'eportfolio_template'))) {
            $template = 'component/config/_templatewrapper.html';
        }
		else if (in_array($this->instance->getModule(), array('homepage', 'eportfolio'))) {

			/* eportfolio uses a special version of le wrapier
			 */
			$template = 'component/config/' . $this->instance->getModule() . '_wrapper.html';
		}
		else {
			$template = 'component/config/_wrapper.html';
        }
        $page->setFile('configTpl', $template);

        $page->setBlock('configTpl', 'settings_link', 'settings_link_block');
        $page->setBlock('configTpl', 'settings_panel', 'settings_panel_block');
        $page->setBlock('settings_panel', 'config_element', 'config_element_block');
        $page->setVar('config_element_block', '');

        if ($processConfig) {
            // every config key has a corresponding controller and template to be called
            foreach ($config as $key => $params) {

                $page->setVar(
                    array(
                        'CONFIG_ELEMENT_LABEL' => $params['label'],
                        'CONFIG_ELEMENT_CLASS' => str_replace(' ', '', strtolower($params['label']))
                    )
                );

                $fields = array();
                if ($this->sortFields) {
                    $fields['sort'] = $this->sortFields;
                }
                if ($this->filterFields) {
                    $fields['filter'] = $this->filterFields;
                }

                // attempt to build a configuration instance from the key provided
                if ($conf = Component_Config_Factory::build($key, $this->context, $fields)) {
                    if (!($is_for_template && !$conf->isShownOnTemplate())) {
					    $this->parseConfigInstance($conf, $key, $page, $params);
                    }
                }
                $page->setVar('CONFIG_ELEMENT_CONTENT', '');
            }
            $page->parse('settings_panel_block', 'settings_panel', false);
        } // don't process the config
        else {

        }

        // now clear things we don't want seen
        if (!$displayConfig) {
            $page->setVar('settings_link_block', '');
            $page->setVar('settings_panel_block', '');
        } else {
            $page->parse('settings_link_block', 'settings_link');
        }

        if (!$hasWriteAccess) {
            return false;
        }

        $page->parse($targetHandle, 'configTpl', false);

        return true;
    }

	/**
	 * Split this out so subclasses can determine which config elements get rendered
	 *
	 * @param $conf
	 * @param $key
	 * @param $page
	 * @param $params
	 */
	protected function parseConfigInstance($conf, $key, $page, $params) {
		$templateFile = "component/config/$key.html";

		$page->setFile($key, $templateFile);
		$conf->parse($page, $key, $key, Scrub::text($params['value']));
		$page->parse('CONFIG_ELEMENT_CONTENT', $key);
		$page->setVar('CONFIG_DISPLAY', $params['label'] ? '' : ' style="display:none;"');
		$page->parse('config_element_block', 'config_element', true);
    }

    /**
     * @param string $key
     * @return mixed
     */
    protected function getContext($key = '') {
        if ($key) {
            return isset($this->context[$key]) ? $this->context[$key] : false;
        }
        return $this->context;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getConfig($key = '', $attr = 'value') {
        if ($key) {
            return isset($this->config[$key][$attr]) ? $this->config[$key][$attr] : false;
        }

        $return = array();

        foreach ($this->config as $key => $item) {
            $return[$key] = $item['value'];
        }

        return $return;
    }

    /**
     * @param $key
     * @return bool
     */
    public function hasConfig($key) {
        return isset($this->config[$key]) ? true : false;
    }

    public function getSortFields() {
        return $this->sortFields;
    }

    public function getFilterFields() {
        return $this->filterFields;
    }

    public function getPageTemplate() {
        return $this->sitepage->template;
    }


    /**
     * Public accessor for $this->instance - use this instead of magic method
     * obscuring a protected variable. (wat)
     *
     * @param \Component_Instance $instance
     */
    public function setInstance($instance) {
        $this->instance = $instance;
    }

    /**
     * @return \Component_Instance
     */
    public function getInstance() {
        return $this->instance;
    }

}
