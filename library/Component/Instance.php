<?php

/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Collection;

class Component_Instance
{
    public const MODULE_HOMEPAGE = 'homepage';

    public const MODULE_HOMEPAGE_TEMPLATE = 'homepage_template';

    public const MODULE_DASHBOARD = 'dashboard';

    public const MODULE_EPORTFOLIO = 'eportfolio';

    public const MODULE_EPORTFOLIO_TEMPLATE = 'eportfolio_template';

    /** @var int $id */
    protected $id;

    /** @var int $definitionId */
    protected $definitionId;

    /** @var Component_Definition $definition */
    protected $definition;

    /** @var string $module */
    protected $module;

    /** @var int $identifier ID of the Folder where this component resides */
    protected $identifier;

    /** @var string $position */
    protected $position;

    /** @var int $sequence */
    protected $sequence;

    /** @var int $instanceCount An unmapped count of instances of this item. */
    protected $instanceCount = 0;

    /** @var Collection|Component_ConfigDefinition[] $config */
    protected $config;

    /**
     * Component_Instance constructor.
     */
    public function __construct()
    {
        $this->setConfig(new Collection());
    }

    /**
     * Convenience method to retrieve a specific config
     *
     * @param string $key
     * @return Component_ConfigDefinition
     */
    public function getConfigByKey($key)
    {
        foreach ($this->config as $config) {
            if ($config->getKey() == $key) {
                return $config;
            }
        }

        throw new DomainException("Config with key: '{$key}' not found");
    }


    /**
     * Increments the instance count for this definition.
     *
     * @return void
     */
    public function incrementInstanceCount()
    {
        $this->instanceCount++;
    }

    /**
     * Overridable function that determines if the component should be rendered in the component palette
     *
     * @param string $context The component context we are in (homepage, eportfolio, dashboard)
     * @param int $contextId The context ID we are current in (folderId, pageId, dashboardId)
     * @return bool
     */
    public function renderInPalette($context, $contextId)
    {
        return true;
    }

    /**
     * Converts a Collection of ConfigDefinitions into an array of $key => $val
     *
     * @param bool $compat
     * @return array
     */
    public function getConfigArray($compat = true)
    {
        // Dead index, this is actually not a config
        $dumb_index = $this->config->getOffset('');

        if (count($this->config) == 1 && isset($dumb_index)) {
            return [];
        }

        $output = [];

        /** @var Component_ConfigDefinition $opt */
        foreach ($this->config as $opt) {
            $key = $compat ? $this->compatMap($opt->getKey()) : $opt->getKey();
            $val = $opt->getValue();

            if (!isset($val)) {
                $val = $opt->getDefaultValue();
            }

            $output[$key] = [
                'label' => $opt->getLabel(),
                'value' => $val,
                'defaultValue' => $opt->getDefaultValue(),
            ];
        }

        return $output;
    }

    /**
     * Converts new keys to old keys to preserve integrity with old components
     *
     * @param string $key
     * @return mixed
     */
    protected function compatMap($key)
    {
        $map = [
            'limit' => 'size',
            'title' => 'componentname',
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        return $key;
    }


    /**
     * Is this component on a homepage?
     *
     * @return bool
     */
    public function isHomepage(): bool
    {
        return $this->module === self::MODULE_HOMEPAGE;
    }


    /**
     * Is this component on a homepage template?
     *
     * @return bool
     */
    public function isHomepageTemplate(): bool
    {
        return $this->module === self::MODULE_HOMEPAGE_TEMPLATE;
    }


    /**
     * Is this component on an eportfolio?
     *
     * @return bool
     */
    public function isEportfolio(): bool
    {
        return $this->module === self::MODULE_EPORTFOLIO;
    }


    /**
     * Is this component on an eportfolio template?
     *
     * @return bool
     */
    public function isEportfolioTemplate(): bool
    {
        return $this->module === self::MODULE_EPORTFOLIO_TEMPLATE;
    }


    /**
     * Is this component on a dashboard?
     *
     * @return bool
     */
    public function isDashboard(): bool
    {
        return $this->module === self::MODULE_DASHBOARD;
    }



    // Getters & Setters

    /**
     * @param Collection|Component_ConfigDefinition[] $config
     * @return self
     */
    public function setConfig($config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @return Component_ConfigDefinition[]|Collection
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param Component_Definition $definition
     * @return self
     */
    public function setDefinition($definition)
    {
        $this->definition = $definition;
        return $this;
    }

    /**
     * @return Component_Definition
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * @param int $definitionId
     * @return self
     */
    public function setDefinitionId($definitionId)
    {
        $this->definitionId = $definitionId;
        return $this;
    }

    /**
     * @return int
     */
    public function getDefinitionId()
    {
        return $this->definitionId;
    }

    /**
     * @param int $id
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $identifier
     * @return self
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
        return $this;
    }

    /**
     * @return int
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * @param string $module
     * @return self
     */
    public function setModule($module)
    {
        $this->module = $module;
        return $this;
    }

    /**
     * @return string
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * @param string $position
     * @return self
     */
    public function setPosition($position)
    {
        $this->position = $position;
        return $this;
    }

    /**
     * @return string
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @param int $sequence
     * @return self
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
        return $this;
    }

    /**
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
    }

    /**
     * @param int $instanceCount
     * @return self
     */
    public function setInstanceCount($instanceCount)
    {
        $this->instanceCount = $instanceCount;
        return $this;
    }

    /**
     * @return int
     */
    public function getInstanceCount()
    {
        return $this->instanceCount;
    }

    /**
     * Checks if this component actually belongs to the specified object (homepage, dashboard, etc), or its alt source destination
     *
     * @param int $sourceId (either the homepage this exists on, or its alt source destination)
     * @return bool
     */
    public function isSourceValid(int $sourceId): bool
    {
        // grab the object ID where this component exists
        $componentSource = $this->getIdentifier();
        // grab the object id of where this component is pointing (if it is alt sourced)
        if ('' !== (string)$this->getConfigByKey('src')->getValue()) {
            $componentSource = (int)$this->getConfigByKey('src')->getValue();
        }
        return $componentSource === $sourceId;
    }
}
