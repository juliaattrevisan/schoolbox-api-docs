<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Pipe;

use Alaress\Async\Notification\Instance\InstanceBundle;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\Worker\AbstractProcess;
use Alaress\WorkerCrew\Worker\PipeWriter;
use Aura\Di\Exception\ReflectionFailure;
use ErrorException;
use InvalidArgumentException;
use PDOException;
use Predis\PredisException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Schoolbox\Di\Configurator;
use Schoolbox\Mapper;
use Schoolbox\Realtime\MessageList\IListFactory;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\User;

class WorkerProcess extends AbstractProcess
{

    /** @var IListFactory */
    private $factory;

    /** @var Configurator */
    private $configurator;


    /**
     * @param LoopInterface             $loop
     * @param LoggerInterface           $log
     * @param PipeWriter                $writer
     * @param DispatchStrategyInterface $strat
     * @param IListFactory              $factory
     * @param Configurator              $configurator
     */
    public function __construct($loop, $log, $writer, $strat, IListFactory $factory, Configurator $configurator)
    {
        parent::__construct($loop, $log, $writer, $strat);
        $this->factory = $factory;
        $this->configurator = $configurator;
    }


    /**
     * @param string $command
     * @param array  $args
     * @throws ErrorException
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws RuntimeException
     * @return mixed
     */
    protected function process($command, $args)
    {
        $this->log->debug("Received $command command");

        try {
            switch ($command) {
                // retrieve messages
                case DefaultDispatchStrategy::TASK_FETCH:
                    list($userId, $count) = $args;
                    return $this->fetch($userId, $count);

                // publish messages
                case DefaultDispatchStrategy::TASK_PUBLISH:
                    $data = unserialize($args[0]);
                    if (!(is_array($data) && count($data) === 4)) {
                        throw new RuntimeException('Data should be array(4); is ' . gettype($data) . '(' . count($data) . ')');
                    }
                    list($callback, $model, $user, $diff) = $data;
                    return $this->publish($callback, $model, $user, $diff);

                // digest or alert messages
                case DefaultDispatchStrategy::TASK_DIGEST:
                case DefaultDispatchStrategy::TASK_TIMETABLE:
                    $data = unserialize($args[0]);
                    if (!(is_array($data) && count($data) >= 2)) {
                        throw new RuntimeException('Data should be array(2+); is ' . gettype($data) . '(' . count($data) . ')');
                    }
                    return $this->sendTo($data[0], $data[1]);
            }
        } catch (Mapper\Exception $e) {
            // non-fatal
            throw new ErrorException(
                sprintf('Could not complete `%s`: %s', $command, $e->getMessage()),
                $e->getCode(),
                E_NOTICE,
                $e->getFile(),
                $e->getLine(),
                $e
            );
        }

        throw new InvalidArgumentException("Can't process '$command'.");
    }


    /**
     * Process fetch
     *
     * @param int $userId
     * @param int $limit
     * @throws PDOException
     * @return array
     */
    protected function fetch($userId, $limit = 0)
    {
        $this->log->debug(sprintf('Fetching %s for user %d', $limit ?: 'all', $userId));
        $decorator = new Decorator();

        try {
            // get ALL messages in this queue and make them prettae!
            $this->factory->listed($userId)->fetchIntoBundle($decorator, true);

            // wrap in an array to preserve keys during transport
            return [ $decorator->renderAsSocketArray(false, $limit) ];
        } catch (PredisException $e) {
            // Redis has gone away -_-;
            throw new PDOException($e->getMessage(), $e->getCode(), $e);
        }
    }


    /**
     * Process publish/digest
     *
     * @param callable $callback
     * @param object   $model
     * @param User     $user
     * @param array    $diff
     * @return string
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    protected function publish($callback, $model, $user, $diff)
    {
        if (!is_callable($callback)) {
            throw new RuntimeException('Unable to execute callback, got ' . json_encode($callback));
        }

        try {
            // overwrite the serialized thing with a real version
            $callback[0] = $this->getNotifier($callback);
            $callback[0]->enableLogging($this->log, true);
        } catch (InvalidArgumentException $e) {
            // ignore this
        }

        $this->log->debug('Executing ' . $this->callable2string($callback));

        // this call SHOULD return an instance of InstanceBundle
        $return = call_user_func($callback, $model, $user, $diff);
        $this->log->debug('Callback complete');

        if (null === $return) {
            $this->log->debug('Response is NULL, casting to empty Envelope');
            $return = new Envelope();
        }

        if ($return instanceof InstanceBundle) {
            $this->log->debug('InstanceBundle ' . ($return->isEmpty() ? 'does not contain messages' : 'contains messages'));

            $serial = serialize($return);
            if ($serial === false) {
                throw new RuntimeException('Failed to serialize InstanceBundle');
            }

            return $serial;
        }

        throw new InvalidArgumentException(
            "Async publish response must be an instance of Alaress\\Async\\Notification\\InstanceBundle; got "
            . (is_object($return) ? get_class($return) : gettype($return))
        );
    }


    /**
     * @param callable $callback
     * @return ModuleNotifierInterface
     * @throws InvalidArgumentException
     */
    private function getNotifier(callable $callback)
    {
        if (is_array($callback) && isset($callback[0]) && $callback[0] instanceof ModuleNotifierInterface) {
            $notifierClassName = get_class($callback[0]);

            try {
                // return a new, shiny instance of this class, correctly configured
                $this->configurator->configureClass($notifierClassName);
                return $this->configurator->newInstance($notifierClassName);
            } catch (ReflectionFailure $e) {
                // kennet configure? ok, just return as-is
                return $callback[0];
            }
        }

        throw new InvalidArgumentException('Callback is not a notifier');
    }


    /**
     * @param callable $callback
     * @param object   $object
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @return string
     */
    protected function sendTo($callback, $object)
    {
        if (!is_callable($callback)) {
            throw new RuntimeException('Unable to execute callback, got ' . json_encode($callback));
        }

        // inject the debugger into the notifier if possible
        if (is_array($callback) && is_object($callback[0])) {
            $setLogger = array($callback[0], 'setLogger');
            if (is_callable($setLogger)) {
                call_user_func($setLogger, $this->log);
            }
        }

        $this->log->debug('Executing ' . $this->callable2string($callback));
        // this call MUST return an integer
        $return = call_user_func($callback, $object);
        $this->log->debug('Callback complete');
        // handle standard digest case

        if (is_int($return)) {
            $this->log->debug('Response is numeric, returning as is');
            return serialize($return);
        }

        throw new InvalidArgumentException(
            'Async digest response must be an integer; got '
            . (is_object($return) ? get_class($return) : gettype($return))
        );
    }


    /**
     * @param callable $callable
     * @return string
     */
    protected function callable2string($callable)
    {
        if (is_array($callable)) {
            return get_class($callable[0]) . '::' . $callable[1];
        }
        return (string)$callable;
    }
}
