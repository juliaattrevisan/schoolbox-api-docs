<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Module;


use Alaress\Async\Notification\Instance\Builder;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Schoolbox\Collection;
use Schoolbox\Di\IDiConfigurable;
use Schoolbox\IConfigLoader;
use Schoolbox\IMapper;
use Schoolbox\Mapper;
use Schoolbox\MapperFactory;
use Schoolbox\Realtime\Message\NS;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Message\Source;
use Schoolbox\Realtime\Message\SourceMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\User;

abstract class AbstractModuleNotifier implements ModuleNotifierInterface, IDiConfigurable
{
    /**
     * Generic deleted trigger, available to all notifiers
     */
    protected const TRIGGER_DELETED = 'deleted';

    /** @var callable[] */
    protected $closures = array();

    /** @var IConfigLoader */
    protected $conf;

    /** @var LoggerInterface */
    protected $log;

    /** @var Mapper */
    protected $mapper;

    /** @var NS */
    protected $namespace;

    /** @var NSMapper */
    protected $nsMapper;

    /** @var SourceMapper */
    protected $srcMapper;

    /** @var int */
    protected $iconSize = 'square64';

    /**
     * Default behaviour is to allow propagation of sub-events
     * @var bool
     */
    private $propagation = true;


    /**
     * Returns the $di->params[static:class] for this class
     *
     * @param \Aura\Di\ContainerInterface $di
     * @return array
     */
    public static function getParams(\Aura\Di\ContainerInterface $di): array
    {
        return [
            'configLoader' => $di->lazyGet('config'),
        ];
    }


    /**
     * Returns the $di->setters[static:class] for this class
     *
     * @param \Aura\Di\ContainerInterface $di
     * @return array
     */
    public static function getSetters(\Aura\Di\ContainerInterface $di): ?array
    {
        /** @var MapperFactory $mf */
        $mf = $di->get('mapperFactory');

        return [
            'setNsMapper'  => $mf->getLazy(NSMapper::class),
            'setSrcMapper' => $mf->getLazy(SourceMapper::class),
        ];
    }


    /**
     * @param IConfigLoader $configLoader
     * @param Mapper[]      $dependencies
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
    public function setMapper($mapper = null)
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
    public function setNsMapper($nsMapper)
    {
        $this->nsMapper = $nsMapper;
    }


    /**
     * @param SourceMapper $srcMapper
     */
    public function setSrcMapper($srcMapper)
    {
        $this->srcMapper = $srcMapper;
    }


    /**
     * Reconstructs and returns what the model looked like prior to the
     * transaction by applying the diff to a clone of the current model.
     *
     * @param mixed      $current
     * @param array|null $diff
     * @param int        $previousKey The key of the previous values in the diff
     * @return mixed|null
     * @todo Add IModel interface so we can declare the type of $current and $previous
     */
    protected function buildPrevious($current, ?array $diff, int $previousKey = 1)
    {
        $previous = null;
        if (null !== $diff) {
            $previous = clone $current;
            foreach ($diff as $property => $values) {
                $this->mapper->setModelProperty($previous, $property, $values[$previousKey]);
            }
        }
        return $previous;
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
     * @throws Mapper\Exception
     * @throws ScheduleException
     * @return callable[]
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

        // Always associate notifySystem{$label} if it exists,
        // regardless of source collection
        $systemMethod = sprintf("notifySystem%s", ucfirst(strtolower($label)));
        if (isset($availableMethods[$systemMethod])) {
            $callbacks[] = [$this, $systemMethod];
        }

        // All other notifiers: notify{$roleType}{$key}
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


    /**
     * @param LoggerInterface $log
     * @param bool            $allowPropagation
     * @return void
     */
    public function enableLogging(LoggerInterface $log, $allowPropagation)
    {
        $this->log         = $log;
        $this->propagation = $allowPropagation;
    }


    /**
     * @param User       $effector
     * @param string     $action
     * @param object     $model
     * @param Mapper     $mapper
     * @param array|null $diff
     * @return void
     */
    protected function raiseNotification(User $effector, $action, $model, Mapper $mapper, array $diff = null)
    {
        if ($this->propagation) {
            $mapper->getEventPropagator()->propagate($effector, $action, $model, $mapper, $diff);
        }
    }


    /**
     * Retracts all the messages related to the given object, only if the model has getId method.
     * @param Envelope $envelope
     * @param object $model
     * @return Envelope
     */
    protected function retractEventObject(Envelope $envelope, $model) : Envelope
    {
        $bob = new Builder(false);

        $getIdMethod = 'getId';
        if ($this->mapper->hasIdentifier()) {
            $getIdMethod = $this->mapper->getGetIdentifierMethodName();
        }
        if (!method_exists($model, $getIdMethod)) {
            return $envelope;
        }
        $bob->addTopic("{$this->getSlug()}.{$model->$getIdMethod()}");
        $envelope->retractAllLike($bob->build());
        return $envelope;
    }

    /**
     * Generates url string for user icon
     * @param User $user
     * @return string
     */
    protected function getUserIcon(User $user): string
    {
        return "/portrait.php?id={$user->getId()}&size={$this->iconSize}";
    }

}
