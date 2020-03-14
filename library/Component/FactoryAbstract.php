<?php
/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\GenericCollection;
use Schoolbox\User;

abstract class Component_FactoryAbstract
{
    /** @var string $module */
    private $module;

    /** @var int $identifier */
    protected $identifier;

    /** @var array $dependencyList */
    protected $dependencyList;

    /**
     * The maximum permission given to components built with this factory.
     * Used for building read-only views of component lists.
     *
     * @var int
     */
    protected $maximumPermission = UserAccess::ACCESS_FULL;

    /**
     * Constructor
     *
     * @param User $user
     * @param SitePage $sitepage
     */
    public function __construct(User $user, SitePage $sitepage)
    {
        $this->dependencyList = array($user, $sitepage);
    }

    /**
     * @param $identifier
     * @return Collection|Component_ControllerAbstract[]
     */
    public function buildComponentList($identifier)
    {
        /* Get Component Instances */
        $instanceCollection = $this->getInstanceList($identifier);

        $componentCollection = new Collection();

        if (!empty($instanceCollection)) {
            foreach ($instanceCollection as $instance) {
                $component = $this->buildComponent($instance);

                if (!empty($component)) {
                    $componentCollection->add($component);
                }
            }
        }

        return $componentCollection;
    }

    /**
     * Builds a component from a component instance or ID
     *
     * @param Component_Instance|Integer $instance
     * @return Component_ControllerAbstract|Bool
     */
    public function buildComponent($instance)
    {
        if (is_numeric($instance)) {
            $instance = $this->getInstance($instance);
        }

        if(!$instance instanceof Component_Instance) {
            return false;
        }

        /** @var Component_Instance $instance */
        $class = $instance->getDefinition()->getClass();

        if (class_exists($class, true)) {
            $argumentList = array_merge(array($instance), $this->dependencyList);

            $dependencyList = $this->buildDependencyList($class, 3);

            if (!empty($dependencyList)) {
                $argumentList = array_merge($argumentList, $dependencyList);
            }

            $controllerReflection = new ReflectionClass($class);

            /** @var Component_ControllerAbstract $controller */
            $controller = $controllerReflection->newInstanceArgs($argumentList);
            $controller->setMaximumPermission($this->getMaximumPermission());

            return $controller;
        }

        return false;
    }

    /**
     * @param $identifier
     * @return Component_Instance[]|GenericCollection
     */
    public function getInstanceList($identifier)
    {
        return Component_InstanceMapper::getInstance()->findByContext($this->module, $identifier);
    }

    /**
     * Return a collection of components used in any eportfolio template setup
     *
     * @param string $module
     * @return Component_Instance[]|\Schoolbox\GenericCollection
     */
    public function getModuleInstanceList($module)
    {
        return Component_InstanceMapper::getInstance()->findByModule($module);
    }

    /**
     * @param $instanceId
     * @return Component_Instance
     */
    public function getInstance($instanceId)
    {
        /* @var $instance Component_Instance */
        $instance = Component_InstanceMapper::getInstance()->findById($instanceId);

        /** @var Component_Definition $componentDefinition */
        $componentDefinition = Component_DefinitionMapper::getInstance()->findById($instance->getDefinitionId());

        $instance->setDefinition($componentDefinition);

        return $instance;
    }

    /**
     * @param string $sortBy
     * @return Collection
     */
    public function getDefinitionList($sortBy = '')
    {
        return Component_DefinitionMapper::getInstance()->findAllForModule($this->module, $sortBy);
    }

    /**
     * Builds dependencies from a component controller's constructor.
     *
     * @param String $class
     * @param Integer $offset
     * @return array
     */
    protected function buildDependencyList($class, $offset = 0)
    {
        $dependencyList  = [];
        $classReflection = new ReflectionClass($class);
        $parameterList   = $classReflection->getConstructor()->getParameters();

        if (!empty($parameterList)) {
            foreach ($parameterList as $i => $parameter) {
                if ($i >= $offset) {

                    try {
                        ///Parses the export of the parameter for the class
                        //$parameterString = (string) new ReflectionClass($parameter);
                        $dependencyClass = preg_replace('/.*?(\w+)\s+\$' . $parameter->name . '.*/', '\\1', (string) $parameter);

                        if (!empty($dependencyClass)) {
                            $dependencyList[] = new $dependencyClass;
                        }

                    } catch (Exception $exception) {
                        return $dependencyList;
                    }

                }
            }
        }

        return $dependencyList;
    }

    // Getters & Setters

    /**
     * @return int
     */
    public function getMaximumPermission()
    {
        return $this->maximumPermission;
    }

    /**
     * @param int $maximumPermission
     * @return self
     */
    public function setMaximumPermission($maximumPermission)
    {
        $this->maximumPermission = $maximumPermission;
        return $this;
    }

    /**
     * @return string
     */
    protected function getModule()
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
}
