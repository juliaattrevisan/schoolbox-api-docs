<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Routing;

use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\Worker\AbstractProcess;
use Alaress\WorkerCrew\Worker\PipeWriter;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Schoolbox\Realtime\Rerouter\QueueSorter;
use Schoolbox\UserMapper;

class Worker extends AbstractProcess
{

    /** @var QueueSorter */
    private $sorter;

    /**
     * @param QueueSorter               $queueSorter
     * @param LoopInterface             $loop
     * @param LoggerInterface           $log
     * @param PipeWriter                $writer
     * @param DispatchStrategyInterface $strat
     */
    public function __construct($queueSorter, $loop, $log, $writer, $strat)
    {
        parent::__construct($loop, $log, $writer, $strat);
        $this->sorter = $queueSorter;
    }


    /**
     * @param string $command
     * @param array  $args
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @return mixed
     */
    protected function process($command, $args)
    {
        if ($command != DispatchStrategy::TASK_ROUTE) {
            throw new InvalidArgumentException("Worker can only route");
        }
        $count = 0;
        $this->log->debug("Iterating frames provided");
        foreach ($args as $v) {
            list($frame, $userIds) = $v;
            foreach ($userIds as $userId) {
                $user = UserMapper::getInstance()->findById($userId);
                $count += $this->sorter->process($user, $frame) ? 1 : 0;
            }
        }

        $this->log->info("Rerouted $count message" . ($count == 1 ? '' : 's'));

        return $count;
    }

}