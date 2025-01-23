<?php

/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Sentry\Tracing\Tracer;
use Aura\Router\Generator;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

abstract class Component_ControllerAbstract
{
    // These constants pertain to the date range selector in the component config UI.
    private const RANGE_DEFAULT = 0;
    private const RANGE_WEEK = 4;
    private const RANGE_MONTH = 5;
    private const N_DAYS_IN_ONE_WEEK = 7;

    // Reasons for showing a component empty state

    const EMPTY_STATE_REASON_UNKNOWN = 0;           // Default
    const EMPTY_STATE_REASON_DENIED = 1;            // No read access to data source folder
    const EMPTY_STATE_REASON_DELETED = 2;           // Component's data source folder is deleted

    /** @var Component_Instance $instance */
    private $instance;

    /** @var User $user */
    private $user;

    /** @var SitePage $sitepage */
    private $sitepage;

    /** @var MapperFactory $mapperFactory */
    private $mapperFactory;

    /** @var Folder|Eportfolio_Page_Instance|null $context The folder|page the component is on */
    private $context;

    /** @var Folder|Eportfolio_Page_Instance|null $source The folder|page the component pulls data from */
    private $source;

    /** @var array $config */
    private $config;

    /** @var int Permission for component contents */
    private $contentPermission = UserAccess::ACCESS_DENY;

    /** @var string[] Available sort fields for this component */
    protected $sortFields = [];

    /** @var string $componentName Name of the component */
    private $componentName = '';

    /** @var string $componentHref Link that the component header points to */
    private $componentHref = '#';

    /** @var FolderMapper $folderMapper */
    private $folderMapper;

    /** @var ErrorMapper $errorMapper */
    private $errorMapper;

    /** @var IConfigLoader $configLoader */
    private $configLoader;

    /** @var int $maximumPermission The maximum permission allowed for this component */
    private $maximumPermission = UserAccess::ACCESS_FULL;

    /**
     * Permission for component controls, as opposed to the component content.
     *
     * These permissions are always based off of the component's context, as opposed to component content permissions
     * (@see $this->contentPermission), which may be based off of an alternate source.
     *
     * @var int $contextPermission
     */
    private $contextPermission = UserAccess::ACCESS_DENY;

    /**
     * Available filters for this component.
     *
     * @see Component_Config_Filter for how this data is passed in/out of the database
     * @var string[] $filterFields
     */
    protected $filterFields = [];

    /**
     * Configuration template to use.
     * Usually determined by `determineTemplate` but can be set with `setConfigurationTemplate()`
     *
     * @var string $configurationTemplate
     */
    private $configurationTemplate = '';

    /**
     * Constructor
     *
     * @param Component_Instance $instance
     * @param User $user
     * @param SitePage $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage)
    {
        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $sitepage->getDi()->get('mapperFactory');

        /** @var FolderMapper $folderMapper */
        $folderMapper = $mapperFactory->get(FolderMapper::class);

        /** @var ErrorMapper $errorMapper */
        $errorMapper = $mapperFactory->get(ErrorMapper::class);

        /** @var IConfigLoader $configLoader */
        $configLoader = $sitepage->getDi()->get('config');

        $this
            ->setInstance($instance)
            ->setUser($user)
            ->setSitePage($sitepage)
            ->setConfig($instance->getConfigArray())
            ->setMapperFactory($mapperFactory)
            ->setFolderMapper($folderMapper)
            ->setErrorMapper($errorMapper)
            ->setConfigLoader($configLoader);
    }


    /**
     * @return Alaress\Authorizer\Authorizer|object
     * @throws RuntimeException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getAuthorizer(): Alaress\Authorizer\Authorizer
    {
        return $this->getSitePage()->getDi()->get('authorizer');
    }


    /**
     * @return Generator
     * @throws RuntimeException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getRouteGenerator(): Generator
    {
        return $this->getSitePage()->getDi()->get('router.generator');
    }


    /**
     * Setup operations common to all component types.
     *
     * Responsible for permission setup
     *
     * Note: Cannot inject $context in constructor because in many scenarios it is irrelevant (e.g. admin config) and
     * causes un-necessary processing.
     *
     * @param Folder|Eportfolio_Page_Instance|null $context
     */
    public function setup($context = null)
    {
        $this->setContext($context);

        if ($this->isUsingAltSource()) {
            // Remove any non-numeric values from the source config (legacy support)
            $folderId = preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src'));

            // Source may be deleted
            $source = $this->getFolderMapper()->findByIdWithFallback($folderId);

            if (!($source instanceof Folder)) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
            }

            $this->setSource($source);
        } else {
            $this->setSource($context);
        }
    }

    /**
     * Prepares data for the render function. Business logic.
     * All permission checks, data retrieval & object creation should happen here.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        return true;
    }

    /**
     * Executes this component within a performance tracing span.
     *
     * @return bool
     */
    public function executeWithinSpan(): bool
    {
        return Tracer::getDefaultTrace()->runWithinSpan(
            $this->execute(...),
            'component:' . get_class($this),
            'app.component.execute',
            'auto.http.component.execute',
            [
                'component.id' => $this->getInstance()->getId(),
            ],
        );
    }

    /**
     * @param string $templateHandle
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

        $output = [
            'COMPONENT_INSTANCE_ID'   => $this->instance->getId(),
            'COMPONENT_DEFINITION_ID' => $this->instance->getDefinitionId(),
            'COMPONENT_HANDLE'        => $templateHandle
        ];

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
            // Break apart class name by underscores

            $templateNameArray = explode('_', $this->getInstance()->getDefinition()->getClass());

            // Get only last segment

            $templateName = array_pop($templateNameArray);

            // Remove last 10 characters

            $templateName = substr($templateName, 0, -10);

            // And lowercase it

            $templateName = ucfirst($templateName);
        }

        if (empty($templatePath)) {
            $templatePath = "component";
        }

        return "{$templatePath}/{$templateName}.html";
    }

    /**
     * Renders view using Component_AbstractController::$templateObject
     * Responsible just for rendering objects already retrieved during execute()
     * Should never be called if some sort of rendering is not to take place
     *
     * @param string $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        return $this->loadTemplate($templateHandle);
    }

    /**
     * @param string $varname
     * @return mixed
     */
    public function getVar($varname)
    {
        return $this->sitepage->getVar($varname);
    }

    /**
     * @param string|array $var
     * @param string|int $value
     */
    public function setVar($var, $value = '')
    {
        $this->sitepage->setVar($var, $value);
    }

    /**
     * @param mixed $handle
     * @param string $file
     */
    public function setFile($handle, $file)
    {
        $this->sitepage->setFile($handle, $file);
    }

    /**
     * @param string $parent
     * @param string $handle
     * @param string $name
     */
    public function setBlock($parent, $handle, $name = '')
    {
        $this->sitepage->setBlock($parent, $handle, $name);
    }

    /**
     * @param string $target
     * @param string $handle
     * @param bool $append
     */
    public function parse($target, $handle, $append = false)
    {
        $this->sitepage->parse($target, $handle, $append);
    }

    /**
     * Component Config facade ingredients
     *
     * This should really have been a separate class, but I'll probably do that another time
     */

    /**
     * Parses configuration for display when rendering a template. Some config options don't show on templates, but do
     * when an instance of the template gets shown.
     *
     * @param string $targetHandle
     * @return bool
     */
    public function parseConfigForTemplate($targetHandle = 'CONFIGURATION')
    {
        return $this->parseConfig($targetHandle, true);
    }

    /**
     * Determine the permissions for this particular config
     * Ingredient of parseConfig() facade
     *
     * @param array|null $config
     * @return array
     */
    protected function determineConfigPermissions($config)
    {
        if (is_array($config) && count($config)) {
            if ($this->contextPermission > UserAccess::ACCESS_READ) {
                // Have write access, display full configuration block

                return [
                    true,
                    true,
                    true
                ];
            } else {
                // No write access, display readonly variables

                return [
                    false,
                    true,
                    false
                ];
            }
        } else {
            // There is no configuration block to display

            if ($this->contextPermission > UserAccess::ACCESS_READ) {
                // Display delete option if they have write access

                return [
                    true,
                    false,
                    false
                ];
            } else {
                // No config, no write access, do nothing

                return [
                    false,
                    false,
                    false
                ];
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
     * Determine which config template to use, if it hasn't been set with `setConfigurationTemplate()`
     * Ingredient of parseConfig() facade
     *
     * @return string
     */
    protected function determineTemplate()
    {
        if ($configTemplate = $this->getConfigurationTemplate()) {
            return $configTemplate;
        } elseif (in_array($this->instance->getModule(), ['homepage_template', 'eportfolio_template'])) {
            // Admin area used when configuring dashboard/homepage/eportfolio layouts
            return 'component/config/_templatewrapper.html';
        } elseif (in_array($this->instance->getModule(), ['dashboard', 'homepage', 'eportfolio'])) {
            // Public-facing pages for dashboard, homepage, eportfolio
            return 'component/config/' . $this->instance->getModule() . '_wrapper.html';
        }

        throw new DomainException('Could not determine template file.');
    }

    /**
     * Set up statue of phpliberty
     * Ingredient of parseConfig() facade
     *
     * @param string $targetHandle
     */
    protected function setupConfigRendering($targetHandle)
    {
        // Explicitly clear configTpl - repeated assignations won't workand will erroneously use _wrapper.html

        $this->sitepage->setVar([
            'configTpl' => ''
        ]);

        // if we're building in the context of the admin system, use a different template (_templatewrapper.html),
        // otherwise use the regular one (_wrapper.html)

        $this->sitepage->setFile('configTpl', $this->determineTemplate());
        $this->sitepage->setBlock('configTpl', 'settings_link', 'settings_link_block');
        $this->sitepage->setBlock('configTpl', 'settings_panel', 'settings_panel_block');
        $this->sitepage->setBlock('configTpl', 'settings_collapse', 'settings_collapse_block');
        $this->sitepage->setBlock('configTpl', 'settings_close', 'settings_close_block');
        $this->sitepage->setBlock('settings_panel', 'config_element', 'config_element_block');

        $this->sitepage->setVar([
            $targetHandle          => '',
            'config_element_block' => '',

            // The close button which removes components

            'settings_close_block' => '',

            // The little triangle which drops down the config options contained in settings_panel_block

            'settings_link_block'  => '',
            'settings_panel_block' => '',

            // Collapsable control button

            'settings_collapse_block' => '',

            // Component Name

            'COMPONENT_NAME'  => $this->getComponentName(),
            'COMPONENT_HREF'  => $this->getComponentHref(),
            'COMPONENT_CLASS' => $this->getInstance()->getDefinition()->getCssClass(),
        ]);
    }

    /**
     * Render each config element into the statue
     *
     * @param bool $processConfig
     * @param bool $is_for_template
     */
    protected function renderConfigElements($processConfig, $is_for_template)
    {
        // It's possible hiding configs will hide all; check for this and hide the config dropdown if need be

        $configCount = 0;

        // Conditionally decide whether to render each config ingredient
        // If you didn't need to display it, don't iterate through every element and set it up, to save some processing
        // every config key has a corresponding controller and template to be called

        foreach ($this->getInstance()->getConfigArray() as $key => $params) {
            // Special case for componentname default value when empty

            if ($key == 'componentname') {
                if (!strlen($params['value'])) {
                    $componentname = $this->getComponentName();
                } else {
                    $componentname = Scrub::text($params['value']);
                }

                $this->sitepage->setVar([
                    'COMPONENT_NAME' => $componentname,
                    'COMPONENT_HREF' => $this->getComponentHref(),
                ]);
            }

            $this->sitepage->setVar([
                'CONFIG_ELEMENT_LABEL' => $params['label'],
                'CONFIG_ELEMENT_CLASS' => str_replace(' ', '', strtolower($params['label']))
            ]);

            $fields = [];

            if ($this->getSortFields()) {
                $fields['sort'] = $this->sortFields;
            }

            if ($this->getFilterFields()) {
                $fields['filter'] = $this->filterFields;
            }

            // Attempt to build a configuration instance from the key provided

            if ($conf = Component_Config_Factory::build($key, $this->context, $fields)) {
                if (!($is_for_template && !$conf->isShownOnTemplate())) {
                    $this->parseConfigInstance($conf, $key, $this->sitepage, $params);
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
     * Facade method
     *
     * @param string $targetHandle
     * @param bool $is_for_template
     * @return bool
     */
    public function parseConfig($targetHandle = 'CONFIGURATION', $is_for_template = false)
    {
        // Determine what to display

        [$hasWriteAccess, $processConfig] = $this->determineConfigPermissions($this->instance->getConfigArray());

        // If all 3 above vars are false, there is no configuration to display

        if (!($hasWriteAccess || $processConfig)) {
            return false;
        }

        $this->setupConfigRendering($targetHandle);

        if (!$hasWriteAccess) {
            // If you don't have write access, just leave before dealing with any config element rendering and stuff

            $this->renderConfigElements(false, $is_for_template);
            $this->sitepage->parse($targetHandle, 'configTpl', false);
            return false;
        } else {
            // If you want to process the config, then do so and display it. Also provide a triangle so you can alter
            // them through the UI Otherwise do nothing

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
     */
    protected function parseConfigInstance($conf, $key, $page, $params)
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
     * @param string $templateHandle Some PHPLib BS which I don't understand. Just pass $templateHandle from
     *                               $this->renderTemplate($templateHandle).
     * @param string $twigTemplate   The path to a Twig template.
     * @param array  $twigData       Data to pass into the Twig template.
     * @param bool   $renderHeader   Set to false if you don't want the PHPLib controlled header to show.
     * @param bool   $renderConfig   Set to false if you don't want the PHPLib controlled configuration to show.
     *                               (Dashboard components show the collapse controls if this is true.)
     * @param bool   $contentRaw     This is a flag that lets you dump the twig raw rather than using standard header block
     * @param bool   $renderInIsland Set to false if you need to render directly into ocean
     * @return bool Was the template renderable?
     * @throws Twig\Error\LoaderError  When the template cannot be found
     * @throws Twig\Error\SyntaxError  When an error occurred during compilation
     * @throws Twig\Error\RuntimeError When an error occurred during rendering
     * @throws RuntimeException   When Twig renderer not found in this Sitepage object's dependency injector.
     */
    protected function renderTwigTemplate(
        $templateHandle,
        $twigTemplate,
        $twigData = [],
        $renderHeader = true,
        $renderConfig = true,
        $contentRaw = false,
        $renderInIsland = true
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
        $this->setBlock($templateHandle, 'component_island', 'component_island_block');
        $this->setBlock($templateHandle, 'component_ocean', 'component_ocean_block');
        $this->setBlock('component_island', 'dashboard_header', 'dashboard_header_block');

        $this->setVar('component_island_block', '');
        $this->setVar('component_ocean_block', '');
        $this->setVar('header_block', '');
        $this->setVar('dashboard_header_block', '');

        if ($renderHeader) {
            if ($this->getInstance()->getModule() === 'dashboard') {
                $this->parse('dashboard_header_block', 'dashboard_header');
            } else {
                $this->parse('header_block', 'header');
            }
        }

        // Render the twig template
        $this->sitepage->getTwig()->addGlobal('USER', $this->getUser());
        $content = $this->sitepage->getTwig()->render($twigTemplate, $twigData);

        if ($renderInIsland) {
            // If we are rending raw we want to dump the TWIG content into the header rather than COMPONENT_CONTENT block
            if ($contentRaw) {
                if ($this->getInstance()->getModule() === 'dashboard') {
                    $this->setVar('dashboard_header_block', $content);
                } else {
                    $this->setVar('header_block', $content);
                }
                $this->setVar('COMPONENT_CONTENT', '');
            } else {
                $this->setVar('COMPONENT_CONTENT', $content);
            }
            $this->parse('component_island_block', 'component_island');
        } else {
            // We don't want any island at all for full control (eg dashboard timetable)
            $this->setVar('COMPONENT_CONTENT', $content);
            $this->parse('component_ocean_block', 'component_ocean');
        }

        return true;
    }

    /**
     * If current user has write access to current context, render an empty state, else render nothing.
     *
     * Used by phpLib-based components.
     *
     * @return bool
     */
    protected function renderSourceUnavailable()
    {
        if ($this->hasContextWriteAccess()) {
            if (!$this->hasReadAccess()) {
                $this->renderAltSourceDenied();
            } else {
                if ($this->getSource()->isDeleted()) {
                    $this->renderAltSourceDeleted();
                } else {
                    $this->renderEmptyState();
                }
            }
            return true;
        }

        return false;
    }


    /**
     * If current user has write access to current context, render an empty state, else render nothing.
     *
     * Used by twig-based components.
     *
     * @param string $templateHandle
     * @return bool
     */
    protected function renderSourceUnavailableTwig($templateHandle)
    {
        if ($this->hasContextWriteAccess()) {
            if (!$this->hasReadAccess()) {
                return $this->renderTwigAltSourceDenied($templateHandle);
            } else {
                if ($this->getSource()->isDeleted()) {
                    return $this->renderTwigAltSourceDeleted($templateHandle);
                } else {
                    return $this->renderTwigEmptyState($templateHandle);
                }
            }
        }

        return false;
    }

    /**
     * Convenience method for twig-based Components to render the 'AltSourceDeleted' empty state
     *
     * @param string $templateHandle
     * @return bool
     */
    protected function renderTwigAltSourceDeleted($templateHandle)
    {
        return $this->renderTwigEmptyState($templateHandle, self::EMPTY_STATE_REASON_DELETED);
    }

    /**
     * Convenience method for twig-based Components to render the 'AltSourceDenied' empty state
     *
     * @param string $templateHandle
     * @return bool
     */
    protected function renderTwigAltSourceDenied($templateHandle)
    {
        return $this->renderTwigEmptyState($templateHandle, self::EMPTY_STATE_REASON_DENIED);
    }

    /**
     * Renders empty state into twig-based components
     *
     * Private to prevent direct calls. Instead use convenience methods which define a consistent message.
     *
     * @param string $templateHandle
     * @param int $reason Why we are unable to display this component's content.
     * @return bool
     */
    private function renderTwigEmptyState($templateHandle, $reason = self::EMPTY_STATE_REASON_UNKNOWN)
    {
        $isAltSourceDenied  = self::EMPTY_STATE_REASON_DENIED === $reason;
        $isAltSourceDeleted = self::EMPTY_STATE_REASON_DELETED === $reason;

        return $this->renderTwigTemplate(
            $templateHandle,
            'sub/componentSourceUnavailable.twig',
            [
                'componentInstanceId' => $this->getInstance()->getId(),
                'isAltSourceDenied'   => $isAltSourceDenied,
                'isAltSourceDeleted'  => $isAltSourceDeleted,
            ]
        );
    }

    /**
     * Convenience method for PHPLib-based Components to render the 'AltSourceDenied' empty state
     *
     * @return bool
     */
    protected function renderAltSourceDenied()
    {
        return $this->renderEmptyState(self::EMPTY_STATE_REASON_DENIED);
    }

    /**
     * Convenience method for PHPLib-based Components to render the 'AltSourceDeleted' empty state
     *
     * @return bool
     */
    protected function renderAltSourceDeleted()
    {
        return $this->renderEmptyState(self::EMPTY_STATE_REASON_DELETED);
    }

    /**
     * Renders empty state into PHPLib-based Components
     *
     * Private to prevent direct calls. Instead use convenience methods which define a consistent message.
     *
     * @param int $reason Why we are unable to display this component's content.
     * @return true
     */
    private function renderEmptyState($reason = self::EMPTY_STATE_REASON_UNKNOWN)
    {
        $isAltSourceDenied  = self::EMPTY_STATE_REASON_DENIED === $reason;
        $isAltSourceDeleted = self::EMPTY_STATE_REASON_DELETED === $reason;

        $twigTemplate = 'sub/componentSourceUnavailable.twig';
        $twigData     = [
            'componentInstanceId' => $this->getInstance()->getId(),
            'isAltSourceDenied'   => $isAltSourceDenied,
            'isAltSourceDeleted'  => $isAltSourceDeleted,
        ];
        $this->setVar('SOURCE_FOLDER_UNAVAILABLE', $this->sitepage->getTwig()->render($twigTemplate, $twigData));
        $this->parse('source_folder_deleted_block', 'source_folder_deleted');
        $this->setVar('source_folder_exists_block', '');

        return true;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasConfig($key)
    {
        return isset($this->config[$key]) ? true : false;
    }

    /**
     * @return Template
     */
    public function getPageTemplate()
    {
        return $this->getSitePage()->template;
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
     * Return whether we are able to render this component's content for current user.
     * False if lacks read permission on content, or because content is deleted.
     * if getSource()->getId() is null (database deleted, but still referred to) also show as deleted.
     *
     * @return bool
     */
    public function isSourceRenderable()
    {
        /** @phpstan-ignore-next-line */
        if (!$this->hasReadAccess() || ($this->isUsingAltSource() && ($this->getSource()->isDeleted() || $this->getSource()->getId() === null))) {
            return false;
        }

        // Dashboard components that pull data from a homepage source will be null until a homepage is selected

        return null != $this->getSource();
    }

    // Getters & Setters

    /**
     * @return MapperFactory
     */
    protected function getMapperFactory()
    {
        return $this->mapperFactory;
    }

    /**
     * @param MapperFactory $mapperFactory
     * @return static
     */
    protected function setMapperFactory(MapperFactory $mapperFactory)
    {
        $this->mapperFactory = $mapperFactory;
        return $this;
    }

    /**
     * @return Folder|Eportfolio_Page_Instance|null
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param Folder|Eportfolio_Page_Instance|null $context
     * @return static
     */
    protected function setContext($context = null)
    {
        $this->context = $context;
        return $this;
    }

    /**
     * @return User
     */
    protected function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return static
     */
    public function setUser(User $user)
    {
        $this->user = $user;
        return $this;
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

        $return = [];

        foreach ($this->config as $k => $v) {
            $return[$k] = $v['value'];
        }

        return $return;
    }

    /**
     * Override a single configuration variable on the component
     *
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function setConfigValue($key, $value)
    {
        $this->config[$key]['value'] = $value;
        return $this;
    }

    /**
     * @param array $config
     * @return static
     */
    protected function setConfig(array $config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @return array
     */
    public function getSortFields()
    {
        return $this->sortFields;
    }

    /**
     * @return array
     */
    public function getFilterFields()
    {
        return $this->filterFields;
    }

    /**
     * @return Component_Instance
     */
    public function getInstance()
    {
        return $this->instance;
    }

    /**
     * @param Component_Instance $instance
     * @return static
     */
    public function setInstance(Component_Instance $instance)
    {
        $this->instance = $instance;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaximumPermission()
    {
        return $this->maximumPermission;
    }

    /**
     * @param int $maximumPermission
     * @return static
     */
    public function setMaximumPermission($maximumPermission)
    {
        $this->maximumPermission = $maximumPermission;
        return $this;
    }

    /**
     * Gets the folder (if any) this component draws its data from
     *
     * @return Folder|Eportfolio_Page_Instance|null
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Sets the folder (if any) this component draws its data from
     *
     * @param Folder|Eportfolio_Page_Instance|null $source
     * @return static
     */
    public function setSource($source = null)
    {
        $this->source = $source;
        return $this;
    }

    /**
     * Get component name default
     *
     * @return string
     */
    protected function getComponentName()
    {
        // Special case for Manage Folders and any other arbitrarily-constructed components

        if (!$this->instance->getId()) {
            return '';
        }

        // Return db version of the name if class var is empty or not set

        if (!strlen($this->componentName)) {
            return $this->instance->getDefinition()->getName();
        }

        // Otherwise allow a per-controller hardcoded version

        return $this->componentName;
    }

    /**
     * Assign component name default
     *
     * @param string $componentName
     * @return string
     * @return static
     */
    protected function setComponentName($componentName)
    {
        $this->componentName = $componentName;
        return $this;
    }

    /**
     * @return FolderMapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param FolderMapper $folderMapper
     * @return static
     */
    public function setFolderMapper(FolderMapper $folderMapper)
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
     * @param ErrorMapper $errorMapper
     * @return static
     */
    public function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * @return string
     */
    protected function getComponentHref()
    {
        if (!strlen(trim($this->componentHref))) {
            return '#';
        }

        return $this->componentHref;
    }

    /**
     * @param string $componentHref
     * @return static
     */
    protected function setComponentHref($componentHref)
    {
        $this->componentHref = $componentHref;
        return $this;
    }

    /**
     * @return SitePage
     */
    protected function getSitePage()
    {
        return $this->sitepage;
    }

    /**
     * @param SitePage $sitePage
     * @return static
     */
    protected function setSitePage(SitePage $sitePage)
    {
        $this->sitepage = $sitePage;
        return $this;
    }

    /**
     * @return int
     */
    protected function getContextPermission()
    {
        return $this->contextPermission;
    }

    /**
     * @param int $contextPermission
     * @return static
     */
    protected function setContextPermission($contextPermission)
    {
        $this->contextPermission = $contextPermission;
        return $this;
    }

    /**
     * @return int
     */
    protected function getContentPermission()
    {
        return $this->contentPermission;
    }

    /**
     * @param int $contentPermission
     * @return static
     */
    protected function setContentPermission($contentPermission)
    {
        $this->contentPermission = $contentPermission;
        return $this;
    }

    /**
     * @return IConfigLoader
     */
    public function getConfigLoader(): IConfigLoader
    {
        return $this->configLoader;
    }

    /**
     * @param IConfigLoader $configLoader
     * @return static
     */
    public function setConfigLoader(IConfigLoader $configLoader): self
    {
        $this->configLoader = $configLoader;
        return $this;
    }

    /**
     * @param int $range
     * @return array
     */
    protected function getDateStartAndEndFromRangeOptionValue(int $range): array
    {
        $range = is_numeric($this->getConfig('range')) ? (int)$this->getConfig('range') : self::RANGE_DEFAULT;

        switch ($range) {
            case self::RANGE_WEEK:
                // The date range is defined as within the current week (with start day defined as config->calendar_start_day).
                $weekStartDayOfWeek = $this->getConfigLoader()->get('calendar_start_day');
                $dateStart = (
                    (\Alaress_Date_Format::now()->getWeekDayNumber() === $weekStartDayOfWeek)
                    ? \Alaress_Date_Format::now()->startOfDay()
                    : \Alaress_Date_Format::now()->previous((int)$weekStartDayOfWeek)
                );
                $dateEnd = new \Alaress_Date_Format($dateStart->getDatabaseDate(), \Alaress_Date_Format::DATABASE_DATE_FORMAT);
                $dateEnd->addDays(self::N_DAYS_IN_ONE_WEEK - 1);
                break;
            case self::RANGE_MONTH:
                // The date range is defined as within the 2 weeks either side of the current date inclusive.
                $dateStart = \Alaress_Date_Format::now()->startOfDay()->addWeeks(-2);
                $dateEnd = \Alaress_Date_Format::now()->endOfDay()->addWeeks(2);
                break;
            default:
                // The date range is defined as $this->range days either side of the current date inclusive.
                $dateStart = \Alaress_Date_Format::now()->startOfDay()->addDays(-$range);
                $dateEnd = \Alaress_Date_Format::now()->endOfDay()->addDays($range);
        }

        return [$range, $dateStart->startOfDay(), $dateEnd->endOfDay()];
    }
}
