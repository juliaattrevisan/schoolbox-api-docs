<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Module;


use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
use Schoolbox\IMapper;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Message\NS;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Message\Source;
use Schoolbox\Realtime\Message\SourceMapper;
use Schoolbox\Realtime\Schedule\ScheduleException;

abstract class AbstractModuleNotifier implements ModuleNotifierInterface
{

    /** @var callable[] */
    protected $closures = array();

    /** @var IConfigLoader */
    protected $conf;

    /** @var IMapper */
    protected $mapper;

    /** @var NS */
    protected $namespace;

    /** @var Mapper */
    protected $nsMapper;

    /** @var Mapper */
    protected $srcMapper;

    /** @var int */
    protected $iconSize = 'square64';


    /**
     * @param IConfigLoader $configLoader
     * @param Mapper[]      $dependencies
     * @throws ScheduleException
     */
    public function __construct($configLoader, $dependencies = array())
    {
        $this->conf = $configLoader;
        $this->loadDependencies($dependencies);
        if ($this->nsMapper instanceof NSMapper) {
            $this->namespace = $this->nsMapper->findOneBySlug($this->getSlug());
        }
    }


    /**
     * @param Mapper[] $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (!is_array($dependencies)) {
            $dependencies = array();
        }

        if (empty($dependencies['mapper'])) {
            $dependencies['mapper'] = null;
        }

        if (empty($dependencies['nsMapper'])) {
            $dependencies['nsMapper'] = NSMapper::getInstance();
        }

        if (empty($dependencies['srcMapper'])) {
            $dependencies['srcMapper'] = SourceMapper::getInstance();
        }

        // rudimentary dependency injection to allow DI of mappers for testability
        foreach ($dependencies as $prop => $dep) {
            $method = 'set' . ucfirst($prop);

            if (method_exists($this, $method)) {
                call_user_func(array($this, $method), $dep);
            }
        }
    }


    /**
     * Helper method to figure out which Mapper we apply to, and instantiate it
     *
     * @param Mapper $mapper
     * @throws ScheduleException
     */
    protected function setMapper($mapper = null)
    {
        if ($mapper instanceof IMapper) {
            $this->mapper = $mapper;
            return;
        }

        // do some black magic to find out what the mapper class should be
        $mapperClass = str_replace('Notifier', 'Mapper', get_called_class());

        if (!class_exists($mapperClass)) {
            throw new ScheduleException("Could not find class '$mapperClass'");
        }
        if (method_exists($mapperClass, 'getInstance')) {
            $mapper = $mapperClass::getInstance();
        }
        if (!$mapper instanceof IMapper) {
            throw new ScheduleException("Class '$mapperClass' must be an instance of IMapper");
        }

        $this->mapper = $mapper;
    }


    /**
     * @param NSMapper $nsMapper
     */
    protected function setNsMapper($nsMapper)
    {
        $this->nsMapper = $nsMapper;
    }


    /**
     * @param SourceMapper $srcMapper
     */
    protected function setSrcMapper($srcMapper)
    {
        $this->srcMapper = $srcMapper;
    }


    /**
     * Conditionally hydrates the model given, depending on whether or not it's already sufficiently hydrated
     *
     * @param object $model
     * @return object
     */
    protected function hydrate($model)
    {
        // this will actually exist most of the time
        if (method_exists($this->mapper, 'getGetIdentifierMethodName') && !$this->isHydrated($model)) {
            $getId = $this->mapper->getGetIdentifierMethodName();
            $model = $this->mapper->findById($model->$getId());
        }

        return $model;
    }


    /**
     * Test whether or not the given model is sufficiently hydrated, and return a boolean indicating such
     *
     * @param object $model
     * @return bool
     */
    protected function isHydrated($model)
    {
        // simply return true here for most cases, implementing classes to override
        return true;
    }

    /**
     * Return the list of callbacks that are associated with this trigger.
     * Uses SQL lookup to determine code paths. Dirty and awful, but at the moment the best way forward.
     *
     * @param string $label
     * @throws ScheduleException
     * @return array
     */
    public function retrieveCallbacks($label)
    {
        if (!$this->namespace instanceof NS) {
            throw new ScheduleException("Malformed namespace in " . get_called_class());
        }

        /** @var Collection|Source[] $sourceCollection */
        $sourceCollection = $this->srcMapper->findByColumnList(array(
            'namespace' => $this->namespace->getId(),
            'key'       => $label
        ));

        $availableMethods = array();

        // turns out we have to use reflection for this
        $rthis = new ReflectionClass($this);
        // get all public, non-static methods
        foreach ($rthis->getMethods(ReflectionMethod::IS_PUBLIC & ~ReflectionMethod::IS_STATIC) as $m) {
            if (strpos($m->name, 'notify') === 0) {
                $availableMethods[$m->name] = true;
            }
        }

        $callbacks = array();

        foreach ($sourceCollection as $source) {
            $method = sprintf("notify%s%s",
                ucfirst(strtolower($source->getRoleType())),
                ucfirst(strtolower($source->getKey()))
            );
            if (isset($availableMethods[$method])) {
                $callbacks[] = array($this, $method);
            }
        }
        return $callbacks;
    }


    /**
     * String representation of object
     *
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize(array(
            get_class($this->conf),
            $this->namespace
        ));
    }


    /**
     * Constructs the object
     *
     * @param string $serialized the string representation of the object.
     * @return void
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);

        if (!is_array($data)) {
            throw new RuntimeException("Could not unserialize notifier instance");
        }
        $conf            = $data[0];
        $this->namespace = $data[1];
        // the only thing we serialize is the name of the config class
        $this->__construct(method_exists($conf, 'getInstance') ? $conf::getInstance() : new $conf());
    }


    /**
     * Return the message namespace this module conforms to.
     *
     * @return NS
     */
    public function getNamespace()
    {
        return $this->namespace;
    }


    /**
     * @return string
     */
    protected abstract function getSlug();


    /**
     * @return Mapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }

}
