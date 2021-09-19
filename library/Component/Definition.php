<?php

/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Realtime\Message\NS;

class Component_Definition
{
    // Constants defining modules where components are allowed

    const MODULE_DASHBOARD = 'dashboard';
    const MODULE_HOMEPAGE = 'homepage';
    const MODULE_EPORTFOLIO = 'eportfolio';

    /** @var int $id */
    protected $id;

    /** @var string $name */
    protected $name;

    /** @var string $class */
    protected $class;

    /** @var string $description */
    protected $description;

    /** @var string $module */
    protected $module;

    /** @var bool $enabled */
    protected $enabled;

    /** @var bool $visible */
    protected $visible;

    /** @var string $multiple */
    protected $multiple;

    /** @var string $componentCategory */
    protected $componentCategory;

    /** @var NS|null $namespace Message namespace this component resides in (may be null) */
    protected $namespace;

    /**
     * Suffix of message namespace this component resides in (may be null)
     * Used for more direct sourcing of messages, when forwarding from a folder
     * to another folder which uses this one as an alt-source.
     *
     * @var string|null
     */
    protected $namespaceSuffix;

    /** @var string $componentCategoryName The name of this component's category (as opposed to its ID) */
    protected $componentCategoryName;

    /** @var int $instanceCount An unmapped count of instances of this item */
    protected $instanceCount = 0;

    /** @var string $configCopy CSV list of configurations to copy from template during creation */
    protected $configCopy;

    /**
     * Overridable function to hide component from palette
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
     * @return bool
     */
    public function isHomepageModule()
    {
        return $this->getModule() == self::MODULE_HOMEPAGE;
    }

    /**
     * @return bool
     */
    public function isDashboardModule()
    {
        return $this->getModule() == self::MODULE_DASHBOARD;
    }

    /**
     * @return bool
     */
    public function isEportfolioModule()
    {
        return $this->getModule() == self::MODULE_EPORTFOLIO;
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
     * Gets this component's class, usable as a CSS class.
     *
     * @return string
     */
    public function getCssClass()
    {
        return str_replace('\\', '_', $this->getClass());
    }

    // Getters & Setters

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param string $class
     * @return self
     */
    public function setClass($class)
    {
        $this->class = $class;
        return $this;
    }

    /**
     * @return string
     */
    public function getComponentCategory()
    {
        return $this->componentCategory;
    }

    /**
     * @param string $componentCategory
     * @return self
     */
    public function setComponentCategory($componentCategory)
    {
        $this->componentCategory = $componentCategory;
        return $this;
    }

    /**
     * @return string
     */
    public function getComponentCategoryName()
    {
        return $this->componentCategoryName;
    }

    /**
     * @param string $componentCategoryName
     * @return self
     */
    public function setComponentCategoryName($componentCategoryName)
    {
        $this->componentCategoryName = $componentCategoryName;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return self
     */
    public function setDescription($description)
    {
        $this->description = $description;
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
     * @param int $id
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getConfigCopy()
    {
        return $this->configCopy;
    }

    /**
     * @param string $configCopy
     * @return self
     */
    public function setConfigCopy($configCopy)
    {
        $this->configCopy = $configCopy;
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
    public function getMultiple()
    {
        return $this->multiple;
    }

    /**
     * @param string $multiple
     * @return self
     */
    public function setMultiple($multiple)
    {
        $this->multiple = $multiple;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     * @return self
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    /**
     * @return bool
     */
    public function getVisible()
    {
        return $this->visible;
    }

    /**
     * @param bool $visible
     * @return self
     */
    public function setVisible($visible)
    {
        $this->visible = $visible;
        return $this;
    }

    /**
     * @return NS|null
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param NS|null $namespace
     * @return self
     */
    public function setNamespace(NS $namespace = null)
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getNamespaceSuffix(): ?string
    {
        return $this->namespaceSuffix;
    }

    /**
     * @param null|string $namespaceSuffix
     * @return static
     */
    public function setNamespaceSuffix(?string $namespaceSuffix): self
    {
        $this->namespaceSuffix = $namespaceSuffix;
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
     * @param int $instanceCount
     * @return self
     */
    public function setInstanceCount($instanceCount)
    {
        $this->instanceCount = $instanceCount;
        return $this;
    }
}
