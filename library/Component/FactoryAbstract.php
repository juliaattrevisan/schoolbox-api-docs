<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
abstract class Component_FactoryAbstract {
    /**
     * @var String
     */
    protected $module;

    /**
     * @var Integer
     */
    protected $identifier;

    /**
     * @var Array
     */
    protected $dependencyList;

    /**
     * @param User $user
     * @param SitePage $sitepage
     */
    public function __construct (User $user, SitePage $sitepage) {
        $this->dependencyList = array($user, $sitepage);
    }

    /**
     * @param $identifier
     * @return Schoolbox\Collection
     */
    public function buildComponentList ($identifier) {
        /* Get Component Instances */
        $instanceCollection = $this->getInstanceList($identifier);

        $componentCollection = new Schoolbox\Collection();

        if ( !empty($instanceCollection) ) {
            foreach ($instanceCollection as $instance) {
                $component = $this->buildComponent($instance);

                if ( !empty($component) ) {
                    $componentCollection->add($component);
                }
            }
        }

        return $componentCollection;
    }

    /**
     * Builds a component from a component instance or id
     * @param Component_Instance|Integer $instance
     * @return Component_ControllerAbstract|Bool
     */
    public function buildComponent ($instance) {
        if ( is_numeric($instance) ) {
            $instance = $this->getInstance($instance);
        }

        if ( class_exists($instance->definition->class, true) ) {
            $argumentList = array_merge( array($instance), $this->dependencyList );

            $dependencyList = $this->buildDependencyList($instance->definition->class, 3);

            if ( !empty($dependencyList) ) {
                $argumentList = array_merge( $argumentList, $dependencyList );
            }

            $controllerReflection = new ReflectionClass($instance->definition->class);

            $controller = $controllerReflection->newInstanceArgs($argumentList);

            return $controller;
        }
        else {
            return false;
        }
    }

    /**
     * @param $identifier
     * @return Collection
     */
    public function getInstanceList ($identifier) {
        return Component_InstanceMapper::getInstance()->findByContext($this->module, $identifier);
    }

    /**
     * @param $instanceId
     * @return Component_Instance
     */
    public function getInstance ($instanceId) {
        $instance = Component_InstanceMapper::getInstance()->findById($instanceId);
        $instance->definition = Component_DefinitionMapper::getInstance()->findById($instance->definitionId);

        return $instance;
    }

    /**
     * @return Collection
     */
    public function getDefinitionList($sortBy = '') {
        return Component_DefinitionMapper::getInstance()->findAllForModule($this->module, $sortBy);
    }


    /**
     * Builds dependencies from a component controller's constructor.
     *
     * @param String $class
     * @param Integer $offset
     * @return Array
     */
    protected function buildDependencyList ($class, $offset = 0) {
        $dependencyList = array();

        $classReflection = new ReflectionClass($class);

        $parameterList = $classReflection->getConstructor()->getParameters();

        if ( !empty($parameterList) ) {
            foreach ( $parameterList as $i => $parameter) {
                if ( $i >= $offset ) {

                    try {
                        /* Parses the export of the parameter for the class */
                        $dependencyClass = preg_replace('/.*?(\w+)\s+\$'.$parameter->name.'.*/', '\\1',  Reflection::export($parameter, true) );

                        if ( !empty($dependencyClass) ) {
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

}
