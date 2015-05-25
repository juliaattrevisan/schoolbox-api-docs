<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;


use Alaress\Async\Notification\Instance\InstanceBundle;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\Worker\AbstractProcess;
use Alaress\WorkerCrew\Worker\PipeWriter;
use ErrorException;
use InvalidArgumentException;
use PDOException;
use Predis\PredisException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Schoolbox\Mapper;
use Schoolbox\Notif\UserQueue\UserQueueFactory;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\User;

class WorkerProcess extends AbstractProcess
{

    /** @var UserQueueFactory */
    protected $uqf;


    /**
     * @param LoopInterface             $loop
     * @param LoggerInterface           $log
     * @param PipeWriter                $writer
     * @param DispatchStrategyInterface $strat
     * @param UserQueueFactory          $uqf
     */
    public function __construct($loop, $log, $writer, $strat, $uqf)
    {
        parent::__construct($loop, $log, $writer, $strat);
        $this->uqf = $uqf;
    }


    /**
     * @param string $command
     * @param array  $args
     * @throws ErrorException
     * @throws InvalidArgumentException
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
                    if (!(is_array($data) && count($data) == 4)) {
                        throw new RuntimeException("Data should be array(4); is " . gettype($data) . "(" . count($data) . ")");
                    }
                    list($callback, $model, $user, $diff) = $data;
                    return $this->publish($callback, $model, $user, $diff);

                // digest or alert messages
                case DefaultDispatchStrategy::TASK_DIGEST:
                case DefaultDispatchStrategy::TASK_TIMETABLE:
                    $data = unserialize($args[0]);
                    if (!(is_array($data) && count($data) >= 2)) {
                        throw new RuntimeException("Data should be array(2+); is " . gettype($data) . "(" . count($data) . ")");
                    }
                    return $this->sendTo($data[0], $data[1]);
            }
        } catch (Mapper\Exception $e) {
            // non-fatal
            throw new ErrorException(
                sprintf("Could not complete '%s': %s", $command, $e->getMessage()),
                $e->getCode(), E_NOTICE, $e->getFile(), $e->getLine(), $e
            );
        }

        throw new InvalidArgumentException("Can't process '$command'.");
    }


    /**
     * Process fetch
     *
     * @param $userId
     * @param $count
     * @throws PDOException
     * @return array
     */
    protected function fetch($userId, $count)
    {
        $this->log->debug("Fetching $count for user $userId");
        $decorator = new Decorator();

        // get ALL messages in this queue
        $queue = $this->uqf->buildForUser($userId);

        try {
            // make messages pretty
            $queue->fetchNotificationsIntoBundle($decorator, true);

            // wrap in an array to preserve keys
            return array($decorator->renderAsSocketArray(false, $count));

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
            throw new RuntimeException("Unable to execute callback, got " . json_encode($callback));
        }

        $this->log->debug("Executing " . $this->callable2string($callback));

        // this call SHOULD return an instance of InstanceBundle (be that Messenger or Envelope)
        $return = call_user_func($callback, $model, $user, $diff);
        $this->log->debug("Callback complete");

        if (is_null($return)) {
            $this->log->debug("Response is NULL, casting to empty Envelope");
            $return = new Envelope();
        }

        if ($return instanceof InstanceBundle) {
            $this->log->debug("InstanceBundle " . ($return->isEmpty() ? 'does not contain messages' : 'contains messages'));

            $serial = serialize($return);
            if ($serial === false) {
                throw new RuntimeException("Failed to serialize InstanceBundle");
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
     * @param object   $object
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @return string
     */
    protected function sendTo($callback, $object)
    {
        if (!is_callable($callback)) {
            throw new RuntimeException("Unable to execute callback, got " . json_encode($callback));
        }

        // inject the debugger into the notifier if possible
        if (is_array($callback) && is_object($callback[0])) {
            $setLogger = array($callback[0], 'setLogger');
            if (is_callable($setLogger)) {
                call_user_func($setLogger, $this->log);
            }
        }

        $this->log->debug("Executing " . $this->callable2string($callback));
        // this call MUST return an integer
        $return = call_user_func($callback, $object);
        $this->log->debug("Callback complete");
        // handle standard digest case

        if (is_integer($return)) {
            $this->log->debug("Response is numeric, returning as is");
            $serial = serialize($return);
            return $serial;
        }

        throw new InvalidArgumentException(
            "Async digest response must be an integer; got "
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