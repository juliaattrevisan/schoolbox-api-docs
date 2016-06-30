<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use \Schoolbox\Permission\Access\UserAccess;

abstract class Component_ControllerAbstract
{
    /* Reasons for showing a component empty state */
    /** @const int EMPTY_STATE_REASON_UNKNOWN. Default. */
    const EMPTY_STATE_REASON_UNKNOWN = 0;

    /** @const int EMPTY_STATE_REASON_DENIED. No READ+ access to data source folder */
    const EMPTY_STATE_REASON_DENIED  = 1;

    /** @const int EMPTY_STATE_REASON_DELETED. Component's data source folder is deleted */
    const EMPTY_STATE_REASON_DELETED = 2;


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
     * The category the component is on, if any.
     *
     * @var \Schoolbox\Resource\Folder\Model
     */
    protected $context;

    /**
     * The folder the component pulls data from, if any.
     *
     * @var \Schoolbox\Resource\Folder\Model
     */
    protected $source;

    /**
     * @var array
     */
    protected $config;

    /**
     * Permission for component contents.
     *
     * @var int
     */
    protected $contentPermission = DENY_ACCESS;

    /**
     * Permission for component controls, as opposed to the component content.
     *
     * These permissions are always based off of the component's context, as
     * opposed to component content permissions (@see $this->contentPermission), which
     * may be based off of an alternate source.
     *
     * @var int
     */
    protected $contextPermission = DENY_ACCESS;

    /**
     * The maximum permission allowed for this component.
     *
     * Set to READ_ACCESS to force a read-only view of this component.
     *
     * @var int
     */
    protected $maximumPermission = FULL_ACCESS;

    /**
     * @var array
     */
    protected $sortFields = null;

    /**
     * @var array
     */
    protected $filterFields = null;

    /**
     * Name of the component
     *
     * @var string
     */
    protected $componentName = '';

    /**
     * Link that the component header points to. Usually nowhere?
     *
     * @var string
     */
    protected $componentHref = '#';

    /**
     * Configuration template to use.
     *
     * Usually determined by `$this->determineTemplate()`, but can be set with
     * `$this->setConfigurationTemplate()`.
     */
    protected $configurationTemplate = '';

    /** @var FolderMapper $folderMapper */
    protected $folderMapper;

    /** @var ErrorMapper $errorMapper */
    protected $errorMapper;

    /**
    * Constructor
    *
    * @param Component_Instance $instance
    * @param \Schoolbox\User $user
    * @param SitePage $sitepage
    */
    public function __construct(Component_Instance $instance, \Schoolbox\User $user, SitePage $sitepage)
    {
        $this->instance = $instance;
        $this->user     = $user;
        $this->sitepage = $sitepage;
        $this->config   = $instance->getConfigArray();
        $this->context  = new FolderModel();
        $this->source   = new FolderModel();
        $this->setFolderMapper(FolderMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * Provide global config details to the component in preparation for business logic.
     *
     * May perform setup operations common to all component types.
     *
     * @param mixed $context Type is clarified in subclasses
     * @param array $config
     * @return void
     */
    public function setup($context = null, array $config = array())
    {
        $this->context = $context;
        $this->config  = $config;

        if($this->isUsingAltSource()){
            // remove any non-numeric values from the source config (legacy support)
            $folderId = preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src'));

            // source may be deleted
            try {
                $source = $this->getFolderMapper()->findById($folderId);
            } catch(\Schoolbox\Mapper\Exception\Deleted $e){
                $source = $e->getDeletedObject();
            }
            if(!($source instanceof FolderModel)){
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
            }
            $this->setSource($source);
        } else {
            $this->setSource($context);
        }

        $this->postConstruct();
    }

    /**
     * Hook method for components to perform their own dynamic setup() amendments
     */
    protected function postConstruct()
    {
    }

    /**
     * Prepares data for the render function. Business logic.
     *
     * All permission checks, data retrieval & object creation should happen here.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute(){
        // Default to no action
        return true;
    }

    /**
     * @param $templateHandle
     * @param string $templateName
     * @param string $templatePath
     * @return bool
     */
    protected function loadTemplate($templateHandle, $templateName = '', $templatePath = '')
    {
        $templateFile = $this->getTemplate($templateName, $templatePath);

        if (!$this->sitepage->setFile($templateHandle, $templateFile)) {
            return false;
        }

        $output = array(
            'COMPONENT_INSTANCE_ID'   => $this->instance->getId(),
            'COMPONENT_DEFINITION_ID' => $this->instance->getDefinitionId(),
            'COMPONENT_HANDLE'        => $templateHandle . '_' . $this->instance->getId()
        );

        $this->setVar($output);

        return true;
    }

    /**
     * Gets a template's file name relative to system template folder
     *
     * @param string $templateName
     * @param string $templatePath
     * @return string
     */
    protected function getTemplate($templateName = '', $templatePath = '')
    {
        if (empty($templateName)) {
            /* Break apart class name by underscores, get only last segment, remove last 10 characters then send it to lowercase */
            $templateName = ucfirst(substr(array_pop(explode('_', $this->getInstance()->getDefinition()->getClass())), 0, -10));
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
     * Responsible just for rendering objects already retrieved during execute()
     *
     * Should never be called if some sort of rendering is not to take place.
     *
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!$this->loadTemplate($templateHandle)) {
            return false;
        }
        return true;
    }

    /**
     * @param $varname
     * @return mixed
     */
    public function getVar($varname)
    {
        return $this->sitepage->getVar($varname);
    }

    /**
     * @param $var
     * @param string $value
     */
    public function setVar($var, $value = '')
    {
        $this->sitepage->setVar($var, $value);
    }

    /**
     * @param $handle
     * @param $file
     */
    public function setFile($handle, $file)
    {
        $this->sitepage->setFile($handle, $file);
    }

    /**
     * @param $parent
     * @param $handle
     * @param string $name
     */
    public function setBlock($parent, $handle, $name = '')
    {
        $this->sitepage->setBlock($parent, $handle, $name);
    }

    /**
     * @param $target
     * @param $handle
     * @param bool $append
     */
    public function parse($target, $handle, $append = false)
    {
        $this->sitepage->parse($target, $handle, $append);
    }

    public function setJavascript($file)
    {
        $this->sitepage->setJavascript($file);
    }

    /**
     * Component Config facade ingredients
     *
     * This should really have been a separate class, but I'll probably do that
     * another time
     */

    /**
     * Parses configuration for display when rendering a template. Some config
     * options don't show on templates, but do when an instance of the template
     * gets shown.
     *
     * @param string $targetHandle
     *
     * @return bool
     */
    public function parseConfigForTemplate($targetHandle = 'CONFIGURATION')
    {
        return $this->parseConfig($targetHandle, true);
    }

    /**
     * Determine the permissions for this particular config
     *
     * Ingredient of parseConfig() facade
     *
     * @param $config
     * @return array
     */
    protected function determineConfigPermissions($config) {
        if (is_array($config) && count($config)) {
            // have write access
            if ($this->contextPermission > READ_ACCESS) {
                // display full configuration block
                return array(true, true, true);
            } // do not have write access
            else {
                // parse configuration display variables for readonly

                /* One of the schools will tell us what this meant
                 */
                return array(false, true, false);
            }
        } // there is no configuration
        else {
            // have write access
            if ($this->contextPermission > READ_ACCESS) {
                // display delete option
                return array(true, false, false);
            } // do not have write access
            else {
                // no config, no write access: do nothing
                return array(false, false, false);
            }
        }
    }

    /**
     * Sets the configuration template for this component.
     *
     * @param string $template
     */
    public function setConfigurationTemplate($template)
    {
        $this->configurationTemplate = $template;
    }

    /**
     * Gets the configuration template for this component.
     *
     * @return string
     */
    protected function getConfigurationTemplate()
    {
        return $this->configurationTemplate;
    }

    /**
     * Determine which config template to use, if it hasn't been set with
     * `$this->setConfigurationTemplate()`.
     *
     * Ingredient of parseConfig() facade
     *
     * @return string
     */
    protected function determineTemplate() {
        if ($configTemplate = $this->getConfigurationTemplate()) {
            return $configTemplate;
        }
        /* Admin area; used when configuring dashboard/homepage/eportfolio layouts
         */
        else if (in_array($this->instance->getModule(), array('homepage_template', 'eportfolio_template'))) {
            return 'component/config/_templatewrapper.html';
        }
        /* Public-facing pages for dashboard, homepage, eportfolio
         */
        else if (in_array($this->instance->getModule(), array('dashboard', 'homepage', 'eportfolio'))) {
            return 'component/config/' . $this->instance->getModule() . '_wrapper.html';
        }
    }

    /**
     * Set up statue of phpliberty
     *
     * Ingredient of parseConfig() facade
     *
     * @param $targetHandle
     */
    protected function setupConfigRendering($targetHandle) {

        /* Explicitly clear configTpl - repeated assignations won't work
         * and will erroneously use _wrapper.html
         */
        $this->sitepage->setVar(array(
            'configTpl' => ''
        ));

        // if we're building in the context of the admin system, use a different template (_templatewrapper.html),
        // otherwise use the regular one (_wrapper.html)
        $this->sitepage->setFile('configTpl', $this->determineTemplate());
        $this->sitepage->setBlock('configTpl', 'settings_link', 'settings_link_block');
        $this->sitepage->setBlock('configTpl', 'settings_panel', 'settings_panel_block');
        $this->sitepage->setBlock('configTpl', 'settings_collapse', 'settings_collapse_block');
        $this->sitepage->setBlock('configTpl', 'settings_close', 'settings_close_block');
        $this->sitepage->setBlock('settings_panel', 'config_element', 'config_element_block');
        $this->sitepage->setVar(array(
            $targetHandle => '',
            'config_element_block' => '',

            /* The close button which removes components
             */
            'settings_close_block' => '',

            /* The little triangle which drops down the config options
             * contained in settings_panel_block
             */
            'settings_link_block' => '',
            'settings_panel_block' => '',

            /* Collapsable control button
             */
            'settings_collapse_block' => '',

            /* Component Name
             */
            'COMPONENT_NAME' => $this->getComponentName(),
            'COMPONENT_HREF' => $this->getComponentHref(),
            'COMPONENT_CLASS' => $this->getInstance()->getDefinition()->getCssClass(),
        ));
    }

    /**
     * Render each config element into the statue
     *
     * @param $processConfig
     * @param $is_for_template
     * @return int
     */
    protected function renderConfigElements($processConfig, $is_for_template) {
        // it's possible hiding configs will hide all; check for this and hide the config dropdown if need be
        $configCount = 0;

        /* Conditionally decide whether to render each config ingredient
         *
         * If you didn't need to display it, don't iterate through every
         * element and set it up, to save some processing
         */
        // every config key has a corresponding controller and template to be called
        foreach ($this->instance->getConfigArray() as $key => $params) {

            /* Special case for componentname default value when empty
             */
            if ($key == 'componentname') {
                if (!strlen($params['value'])) {
                    $componentname = $this->getComponentName();
                }
                else {
                    $componentname = $params['value'];
                }

                $this->sitepage->setVar(array(
                    'COMPONENT_NAME' => $componentname,
                    'COMPONENT_HREF' => $this->getComponentHref(),
                ));
            }

            $this->sitepage->setVar(
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
                    $this->parseConfigInstance($conf, $key, $this->sitepage, $params, $this->instance->getConfigArray());
                    ++$configCount;
                }
            }

            $this->sitepage->setVar('CONFIG_ELEMENT_CONTENT', '');
        }

        if ($processConfig) {
            $this->sitepage->parse('settings_panel_block', 'settings_panel', false);
        }
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

        // if all 3 above vars are false, bug out
        if (!($hasWriteAccess || $processConfig)) {
            return false;
        }

        $this->setupConfigRendering($targetHandle);

        /* If you don't have write access, just leave before dealing with any
         * config element rendering and stuff
         */
        if (!$hasWriteAccess) {
            $this->renderConfigElements(false, $is_for_template);
            $this->sitepage->parse($targetHandle, 'configTpl', false);
            return false;
        }
        else {
            /* If you want to process the config, then do so and display it
             * Also provide a triangle so you can alter them through the ui
             *
             * Otherwise do nothing
             */
            if ($processConfig) {
                $this->renderConfigElements($processConfig, $is_for_template);

                $this->sitepage->parse('settings_link_block', 'settings_link');
            }

            $this->sitepage->parse('settings_close_block', 'settings_close');
            $this->sitepage->parse($targetHandle, 'configTpl', false);
        }

        return true;
    }

    /**
     * Split this out so subclasses can determine which config elements get rendered
     *
     * @param Component_Config_Abstract $conf
     * @param string $key
     * @param SitePage $page
     * @param array $params
     * @param array $allConfigs All configurations for this component
     */
    protected function parseConfigInstance($conf, $key, $page, $params, $allConfigs = array())
    {
        $templateFile = "component/config/$key.html";

        $page->setFile($key, $templateFile);
        $conf->parse($page, $key, $key, Scrub::text($params['value']), $this);

        $page->parse('CONFIG_ELEMENT_CONTENT', $key);
        $page->setVar('CONFIG_DISPLAY', $params['label'] ? '' : ' style="display:none;"');
        $page->parse('config_element_block', 'config_element', true);
    }

    /**
     * Renders a Twig component.
     *
     * Call from $this->renderTemplate($templateHandle)
     *
     * @param string $templateHandle Some PHPLib BS which I don't understand.
     *                               Just pass $templateHandle from
     *                               $this->renderTemplate($templateHandle).
     * @param string $twigTemplate   The path to a Twig template.
     * @param array  $twigData       Data to pass into the Twig template.
     * @param bool   $renderHeader   Set to false if you don't want the PHPLib
     *                               controlled header to show.
     * @param bool   $renderConfig   Set to false if you don't want the PHPLib
     *                               controlled configuration to show.
     *                               (Dashboard components show the collapse
     *                                controls if this is true.)
     *
     * @return bool Was the template renderable?
     */
    protected function renderTwigTemplate(
        $templateHandle,
        $twigTemplate,
        $twigData = [],
        $renderHeader = true,
        $renderConfig = true
    ) {
        if (!$this->loadTemplate($templateHandle, 'twig', 'component/')) {
            return false;
        }
        $this->parseConfig();
        if (!$renderConfig) {
            $this->setVar('CONFIGURATION', '');
        }

        // Residual blockage
        $this->setBlock($templateHandle, 'header', 'header_block');
        $this->setBlock($templateHandle, 'dashboard_header', 'dashboard_header_block');
        $this->setVar('header_block', '');
        $this->setVar('dashboard_header_block', '');
        if ($renderHeader) {
            if ($this->getInstance()->getModule() == 'dashboard') {
                $this->parse('dashboard_header_block', 'dashboard_header');
            } else {
                $this->parse('header_block', 'header');
            }
        }

        // Render the twig template
        try {
            $content = $this->sitepage->getTwig()->render($twigTemplate, $twigData);
        } catch (\Exception $e) {
            return false;
        }

        $this->setVar('COMPONENT_CONTENT', $content);
        return true;
    }

    /**
     * Convenience method for twig-based Components to render the 'AltSourceDeleted' empty state
     * @param string $templateHandle
     * @return string
     */
    protected function renderTwigAltSourceDeleted($templateHandle){
        $this->renderTwigEmptyState($templateHandle, self::EMPTY_STATE_REASON_DELETED);
    }

    /**
     * Convenience method for twig-based Components to render the 'AltSourceDenied' empty state
     * @param string $templateHandle
     * @return string
     */
    protected function renderTwigAltSourceDenied($templateHandle){
        $this->renderTwigEmptyState($templateHandle, self::EMPTY_STATE_REASON_DENIED);
    }

    /**
     * Renders empty state into twig-based Components
     *
     * Normally, this should not be called directly, instead use convenience methods which
     * define a consistent message.
     *
     * @param string $templateHandle
     * @param int $reason Why we are unable to display this component's content.
     * @return string
     */
    protected function renderTwigEmptyState($templateHandle, $reason = self::EMPTY_STATE_REASON_UNKNOWN){

        $isAltSourceDenied  = self::EMPTY_STATE_REASON_DENIED  === $reason;
        $isAltSourceDeleted = self::EMPTY_STATE_REASON_DELETED === $reason;

        return $this->renderTwigTemplate(
            $templateHandle,
            'sub/componentSourceUnavailable.twig',
            array(
                'componentInstanceId' => $this->getInstance()->getId(),
                'isAltSourceDenied'   => $isAltSourceDenied,
                'isAltSourceDeleted'  => $isAltSourceDeleted,
            )
        );
    }

    /**
     * Convenience method for PHPLib-based Components to render the 'AltSourceDenied' empty state
     * @return bool
     */
    protected function renderAltSourceDenied(){
        return $this->renderEmptyState(self::EMPTY_STATE_REASON_DENIED);
    }

    /**
     * Convenience method for PHPLib-based Components to render the 'AltSourceDeleted' empty state
     * @return bool
     */
    protected function renderAltSourceDeleted(){
        return $this->renderEmptyState(self::EMPTY_STATE_REASON_DELETED);
    }

    /**
     * Renders empty state into PHPLib-based Components
     *
     * Normally, this should not be called directly, instead use convenience methods which
     * define a consistent message.
     *
     * @param int $reason Why we are unable to display this component's content.
     * @return true
     */
    protected function renderEmptyState($reason = self::EMPTY_STATE_REASON_UNKNOWN){

        $isAltSourceDenied  = self::EMPTY_STATE_REASON_DENIED  === $reason;
        $isAltSourceDeleted = self::EMPTY_STATE_REASON_DELETED === $reason;

        $twigTemplate = 'sub/componentSourceUnavailable.twig';
        $twigData = array(
            'componentInstanceId' => $this->getInstance()->getId(),
            'isAltSourceDenied'   => $isAltSourceDenied,
            'isAltSourceDeleted'  => $isAltSourceDeleted,
        );
        $this->setVar('SOURCE_FOLDER_UNAVAILABLE', $this->sitepage->getTwig()->render($twigTemplate, $twigData));
        $this->parse('source_folder_deleted_block','source_folder_deleted');
        $this->setVar('source_folder_exists_block', '');
        return true;
    }

 
    /**
     * @return \Schoolbox\Resource\Folder\Model
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param string $key
     * @param string $attr
     * @return mixed
     */
    public function getConfig($key = '', $attr = 'value')
    {
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
    public function hasConfig($key)
    {
        return isset($this->config[$key]) ? true : false;
    }

    public function getSortFields()
    {
        return $this->sortFields;
    }

    public function getFilterFields()
    {
        return $this->filterFields;
    }

    public function getPageTemplate()
    {
        return $this->sitepage->template;
    }


    /**
     * Public accessor for $this->instance - use this instead of magic method
     * obscuring a protected variable. (wat)
     *
     * @param \Component_Instance $instance
     */
    public function setInstance($instance)
    {
        $this->instance = $instance;
    }

    /**
     * @return \Component_Instance
     */
    public function getInstance()
    {
        return $this->instance;
    }

    /**
     * @return int
     */
    public function getMaximumPermission()
    {
        return $this->maximumPermission;
    }

    /**
     * @param  int $maximumPermission
     * @return $this
     */
    public function setMaximumPermission($maximumPermission)
    {
        $this->maximumPermission = $maximumPermission;
        return $this;
    }

    /**
     * Checks if the user's permission on this component is at least read level.
     *
     * @return bool
     */
    public function hasReadAccess()
    {
        return $this->contentPermission >= UserAccess::ACCESS_READ;
    }

    /**
     * Checks if the user's permission on this component is at least write level.
     *
     * @return bool
     */
    public function hasWriteAccess()
    {
        return $this->contentPermission >= UserAccess::ACCESS_WRITE;
    }

    /**
     * Checks if the user's permission on this component is admin level.
     *
     * @return bool
     */
    public function hasFullAccess()
    {
        return $this->contentPermission >= UserAccess::ACCESS_FULL;
    }

    /**
     * Checks if the user's permission on this component's context is at least read level.
     *
     * @return bool
     */
    public function hasContextReadAccess()
    {
        return $this->contextPermission >= UserAccess::ACCESS_READ;
    }

    /**
     * Checks if the user's permission on this component's context is at least write level.
     *
     * @return bool
     */
    public function hasContextWriteAccess()
    {
        return $this->contextPermission >= UserAccess::ACCESS_WRITE;
    }

    /**
     * Checks if the user's permission on this component's context is admin level.
     *
     * @return bool
     */
    public function hasContextFullAccess()
    {
        return $this->contextPermission >= UserAccess::ACCESS_FULL;
    }

    /**
     * Returns whether this component is using an Alternative Data Source.
     *
     * @return bool
     */
    public function isUsingAltSource()
    {
        return intval($this->getConfig("src")) > 0;
    }

    /**
     * Gets the Folder, if any, this component draws its data from
     *
     * @return Schoolbox\Resource\Folder\Model
     */
    public function getSource(){
        return $this->source;
    }

    /**
     * Sets the Folder, if any, this component draws its data from
     *
     * @param Schoolbox\Resource\Folder\Model $source
     */
    public function setSource($source){
        $this->source = $source;
    }

    /**
     * Return whether we are unable to render this component's content.
     * Either due to lack of permission, or because content is deleted.
     * @return bool
     */
    public function isSourceRenderable(){
        if(!$this->hasReadAccess() || ($this->isUsingAltSource() && $this->getSource()->isDeleted())){
            return false;
        }
        return true;
    }

    /**
     * @return Schoolbox\Resource\Folder\Mapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param  Schoolbox\Resource\Folder\Mapper $folderMapper
     * @return $this
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }

    /**
     * @return ErrorMapper
     */
    public function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param  ErrorMapper $errorMapper
     * @return $this
     */
    public function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * Assign component name default
     *
     * @param $componentName
     * @return string
     */
    protected function setComponentName($componentName)
    {
        $this->componentName = $componentName;
    }
    /**
     * Get component name default
     *
     * @return string
     */
    protected function getComponentName()
    {
        /* Special case for Manage Folders and any other
         * arbitrarily-constructed components
         */
        if (!$this->instance->getId()) {
            return '';
        }

        /* Return db version of the name if class var is empty
        * or not set
        */
        if (!strlen($this->componentName)) {
            return $this->instance->getDefinition()->getName();
        }

        /* Otherwise allow a per-controller hardcoded version
         */
        return $this->componentName;
    }

    /**
     * Assign component href default
     *
     * @param $componentHref
     */
    protected function setComponentHref($componentHref)
    {
        $this->componentHref = $componentHref;
    }

    /**
     * Get component href
     *
     * @return string
     */
    protected function getComponentHref()
    {
        if (!strlen(trim($this->componentHref))) {
            return '#';
        }
        return $this->componentHref;
    }
}
