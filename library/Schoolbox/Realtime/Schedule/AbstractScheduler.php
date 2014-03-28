<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule;



use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\DispatcherInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Exception;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\Realtime\Schedule\Trigger\CancelTrigger;
use Schoolbox\Realtime\Schedule\Trigger\TriggerInterface;
use Schoolbox\Realtime\Verboser;
use SplObjectStorage;


abstract class AbstractScheduler implements SchedulingInterface, TaskResponseHandlerInterface {

	/** @var LoopInterface */
	protected $loop;

	/** @var Verboser */
	public $verboser;

	/** @var array */
	protected $timers;

	/** @var Notif */
	protected $notif;

	/** @var DispatcherInterface */
	protected $dispatcher;

	/** @var SplObjectStorage */
	protected $resolvers;

	/** @var \Schoolbox\IConfigLoader */
	protected $conf;

	/**
	 * @param LoopInterface $loop
	 * @param Verboser $verboser
	 * @param IConfigLoader $configLoader
	 * @param bool $initialise
	 */
	public function __construct($loop, $verboser, $configLoader = null, $initialise = false) {
		$this->loop = $loop;
		$this->verboser = $verboser;
		$this->conf = $configLoader ?: new ConfigMapper();
		$this->notif = new Notif();
		$this->dispatcher = new Dispatcher($loop, $this, $verboser, new SchedulerDispatchStrategy());
		$this->resolvers = new SplObjectStorage();

		if($initialise) {
			$this->initialise();
		}
	}


	/**
	 * Return the method that Workers will use to process this task
	 *
	 * @return string
	 */
	protected abstract function getMethod();


	protected abstract function initialise();


	/**
	 * @return DispatcherInterface
	 */
	public function getDispatcher() {
		return $this->dispatcher;
	}


	/**
	 * Convenience method for scheduling notifications for multiple triggers.
	 *
	 * @param TriggerInterface[] $triggers
	 * @param AckResolver $ackResolver
	 */
	public function scheduleNotifications($triggers, $ackResolver = null) {
		foreach($triggers as $trigger) {
			$this->scheduleNotification($trigger, $ackResolver);
		}
	}


	/**
	 * Schedule a notification based on the given trigger.
	 * Idempotent method.
	 *
	 * @param TriggerInterface $trigger
	 * @param AckResolver $ackResolver
	 */
	public function scheduleNotification($trigger, $ackResolver = null) {

		$model = $trigger->getModel();
		$moduleNotifierClass = $trigger->getModuleNotifierClass();
		$triggerLabel = $trigger->getLabel();

		// fail-fast for cancelling a scheduled notification
		if($trigger instanceof CancelTrigger) {
			if(!$this->unschedule($trigger)) {
				$this->verboser->v("Couldn't unschedule '$triggerLabel' - was it set?", '*');
				if(!$this->checkResolver($ackResolver)) {
					$ackResolver->ack();
					$this->verboser->v("No triggers attached to resolver, ACKnowledging", '-');
				}
			}
			return;
		}

		if($ackResolver instanceof AckResolver) {
			$this->attachResolverTrigger($ackResolver, $trigger);
		}

		$interval = $trigger->getTimestamp() - time();

		// publish and return immediately if the event is now or in the past
		if($interval <= ModuleNotifierInterface::IMMEDIATELY) {
			$this->verboser->v("Immediately firing trigger '$moduleNotifierClass::$triggerLabel'");
			$this->fireTrigger($trigger);
			return;
		}

		// I hate this pattern for this reason
		$self = $this;

		// schedule the future event
		$this->schedule($moduleNotifierClass, $triggerLabel, $model, $interval, function($timer) use ($self) {
			/** @var TimerInterface $timer */

			if(!$timer->getData() instanceof TriggerInterface) {
				throw new ScheduleException("Trigger must be provided to publish data");
			}

			$self->fireTrigger($timer->getData());
		});

	}

	/**
	 * Set a timer for the appointed method at the appointed time, cancelling a previous timer if it exists.
	 *
	 * @param TriggerInterface $trigger
	 * @param int $interval
	 * @param callable $publish
	 */
	protected function schedule($trigger, $interval, $publish) {
		// if a timer has already been set for this event on this object, we need to cancel the old and set the new
		$this->unschedule($trigger);

		$model = $trigger->getModel();
		$module = get_class($trigger->getModuleNotifier());
		$label = $trigger->getLabel();

		// register our timer
		/** @var TimerInterface $timer */
		$timer = $this->loop->addTimer($interval, $publish);
		$timer->setData($trigger);
		$this->putTimer($module, $label, $model, $timer);

		$this->verboser->v("Scheduling trigger '$module::$label'", '+');
	}


	/**
	 * Unschedule an existing timer, if it has been set.
	 *
	 * @param TriggerInterface $trigger
	 * @return bool
	 */
	protected function unschedule($trigger) {

		$model = $trigger->getModel();
		$module = get_class($trigger->getModuleNotifier());
		$label = $trigger->getLabel();

		if($this->hasTimer($module, $label, $model)) {
			$timer = $this->getTimer($module, $label, $model);
			$this->verboser->v("Unscheduling trigger '$module::$label'", '-');
			$this->loop->cancelTimer($timer);
			return true;
		}
		return false;
	}


	/**
	 * @param string $module
	 * @param string $label
	 * @param object $model
	 * @return TimerInterface|null
	 */
	protected function getTimer($module, $label, $model) {
		return $this->hasTimer($module, $label, $model)
			? $this->timers[$module][$label][$this->hashModel($model)]
			: null;
	}

	/**
	 * @param string $module
	 * @param string $label
	 * @param object $model
	 * @param TimerInterface $timer
	 */
	protected function putTimer($module, $label, $model, $timer) {
		$hash = $this->hashModel($model);
		$this->timers[$module][$label][$hash] = $timer;
	}

	/**
	 * @param string $module
	 * @param string $label
	 * @param object $model
	 * @return bool
	 */
	protected function hasTimer($module, $label, $model) {
		$hash = $this->hashModel($model);
		return isset($this->timers[$module][$label][$hash]);
	}

	/**
	 * @param string $module
	 * @param string $label
	 * @param object $model
	 */
	public function removeTimer($module, $label, $model) {
		$hash = $this->hashModel($model);
		unset($this->timers[$module][$label][$hash]);
	}

	/**
	 * @param object $model
	 * @return string
	 */
	protected function hashModel($model) {
		$modelClass = get_class($model);
		$mapClass = $modelClass . "Mapper";

		if(class_exists($mapClass)) {
			$getId = $mapClass::getInstance()->getGetIdentifierMethodName();
			$hash = sha1("$modelClass-".(string)$model->$getId());
		}
		else {
			$hash = sha1(print_r($model, true));
		}
		return $hash;
	}

	/**
	 * Return the module to be working with, based on the model class (with additional safety checks)
	 *
	 * @param string $modelClass
	 * @throws ScheduleException
	 * @return ModuleNotifierInterface
	 */
	public function getModule($modelClass) {
		$class = $modelClass . "Notifier";
		if(!class_exists($class)) {
			throw new ScheduleException("No notifier for model '$modelClass'.");
		}
		$instance = new $class($this->conf);
		if(!$instance instanceof ModuleNotifierInterface) {
			throw new ScheduleException("Notifer class '$class' found, but not an instance of ModuleNotifierInterface.");
		}
		return $instance;

	}


	/**
	 * @param AckResolver $ackResolver
	 * @param TriggerInterface $trigger
	 */
	protected function attachResolverTrigger($ackResolver, $trigger) {
		if($this->resolvers->contains($ackResolver)) {
			/** @var SplObjectStorage $triggers */
			$triggers = $this->resolvers->offsetGet($ackResolver);
		}
		else {
			$triggers = new SplObjectStorage();
		}
		$triggers->attach($trigger);
		$this->resolvers->attach($ackResolver, $triggers);
	}

	/**
	 * @param TriggerInterface $trigger
	 * @return int|null the number of triggers left on the associated resolver, or null if not found
	 */
	protected function detachTrigger($trigger) {
		foreach($this->resolvers as $resolver) {
			$triggers = $this->resolvers[$resolver];

			/**
			 * @var AckResolver $resolver
			 * @var SplObjectStorage $triggers
			 */
			if($triggers->contains($trigger)) {
				$this->verboser->v("Detaching trigger from resolver", '-');
				$triggers->detach($trigger);
				if($triggers->count() == 0) {
					// this resolver's triggers have all fired, ACK the message
					$this->verboser->v("Resolver empty, ACKnowledging", '-');
					$resolver->ack();
				}
				else {
					$this->verboser->v('Resolver still contains ' . $triggers->count() . ' triggers');
				}
				return $triggers->count();
			}
		}
		return null;
	}

	/**
	 * @param AckResolver $ackResolver
	 * @return int|null
	 */
	protected function checkResolver($ackResolver) {
		if($this->resolvers->contains($ackResolver)) {
			$triggers = $this->resolvers->offsetGet($ackResolver);
			return count($triggers);
		}
		return null;
	}


	/**
	 * Called from the EventLoop at the appointed time, or immediately if the time has elapsed.
	 *
	 * @param TriggerInterface $trigger
	 * @throws Exception
	 * @return void
	 */
	public function fireTrigger($trigger) {
		// determine the type of what we've received

		// iterate the callbacks in the trigger and publish them
		foreach($trigger->getCallbacks() as $callback) {
			try {
				$this->publish($trigger, $callback);
			}
			catch(ScheduleException $e) {
				$this->verboser->v($e->getMessage(), '!');
			}
			catch(Exception $e) {
				// don't know what other exceptions will be thrown here, so for now, acknowledge and rethrow
				$this->verboser->v(sprintf("%s -> %s",get_class($e), $e->getMessage()), '!');
				throw $e;
			}

		}
	}

	/**
	 * @param TriggerInterface $trigger
	 * @param callable $callback
	 * @throws ScheduleException
	 */
	protected function publish($trigger, $callback) {
		if(!is_callable($callback)) {
			throw new ScheduleException("Uncallable callback supplied");
		}
		$this->getDispatcher()->enqueue($trigger, $this->getMethod(), serialize(array(
			$callback, $trigger->getModel(), $trigger->getUser(), $trigger->getDiff()
		)));
	}
} 