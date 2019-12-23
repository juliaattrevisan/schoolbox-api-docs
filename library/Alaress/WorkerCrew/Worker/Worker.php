<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Worker;


use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Exception;
use Psr\Log\LoggerInterface;
use RangeException;

class Worker extends PipeWriter
{

    const READY = 0x1;
    const BUSY  = 0x2;
    const DONE  = 0x4;
    const ENDED = 0x8;
    const ERROR = 0x10;
    const ALL   = 0x1F;


    /** @var string */
    protected $cmd;
    /** @var resource */
    protected $pp;
    /** @var array */
    protected $pipes;
    /** @var int */
    protected $status;
    /** @var float */
    protected $start;
    /** @var Task */
    protected $task;

    /**
     * @var DispatchStrategyInterface
     */
    private $strat;


    /**
     * @param string                    $cmd
     * @param resource                  $pp
     * @param array                     $pipes
     * @param DispatchStrategyInterface $strat
     */
    function __construct($cmd, $pp, $pipes, $strat)
    {
        parent::__construct($strat, null, $pipes[0]);
        $this->cmd    = $cmd;
        $this->pp     = $pp;
        $this->pipes  = $pipes;
        $this->status = self::READY;
        $this->start  = microtime(true);
        $this->strat  = $strat;
    }


    /**
     * Ensure we clean up after ourselves
     */
    function __destruct()
    {
        // if the process has already terminated, these closures won't do anything (so suppress warnings)
        foreach ($this->pipes as &$p) {
            /** @noinspection PhpUsageOfSilenceOperatorInspection */
            @fclose($p);
        }
        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        @pclose($this->pp);
    }


    /**
     * @param LoggerInterface $log
     */
    public function attachLogger($log)
    {
        $this->log    = $log;
        $this->status = self::READY;
    }


    /**
     * @return string
     */
    public function getCommand()
    {
        return $this->cmd;
    }


    /**
     * @return array
     */
    public function getPipes()
    {
        return $this->pipes;
    }


    /**
     * @return resource
     */
    public function getProcessHandle()
    {
        return $this->pp;
    }


    /**
     * @return float
     */
    public function getStart()
    {
        return $this->start;
    }


    /**
     * @param int $status
     * @return self
     */
    public function setStatus($status)
    {
        // the Map of Transition Validity
        $map = array(
            self::BUSY  => array(
                self::DONE  => function ($worker) {
                        /** @var Worker $worker */
                        $worker->markTaskComplete();
                        return true;
                    },
                self::ERROR => function ($worker) {
                        /** @var Worker $worker */
                        return true;
                    },
            ),
            self::DONE  => array(
                self::READY => function ($worker) {
                        /** @var Worker $worker */
                        $worker->clearTask();
                        return true;
                    },
            ),
            self::ERROR => array(
                self::ENDED => function ($worker) {
                        /** @var Worker $worker */
                        $worker->clearTask();
                        return true;
                    },
            ),
            self::READY => array(
                self::BUSY  => function ($worker) {
                        /** @var Worker $worker */
                        // task must already be set (ie. use self::setTask())
                        return $worker->getTask() instanceof Task;
                    },
                self::ENDED => function ($worker) {
                        /** @var Worker $worker */
                        $worker->clearTask();
                        return true;
                    },
                self::ERROR => function ($worker) {
                        /** @var Worker $worker */
                        return true;
                    },
            ),
        );

        // if this is a valid transition, and the transition passes validation, update
        if (isset($map[$this->status][$status]) && $map[$this->status][$status]($this)) {
            $this->status = $status;
        }

        return $this->status;
    }


    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }


    /**
     * @param object $identifier
     * @param string $cmd
     * @param string $args
     * @throws RangeException
     * @return self
     */
    public function setTask($identifier, $cmd, $args)
    {
        if ($this->getStatus() != self::READY) {
            // go find someone else to do your dirty work
            throw new RangeException("Already tasked, cannot accept another");
        }
        $this->write($this->strat->composeTaskString($cmd, $args));
        $this->task = new Task($identifier, $cmd, $args);
        $this->setStatus(self::BUSY);
        return $this;
    }


    /**
     * @param $data
     * @return self
     */
    public function appendData($data)
    {
        $this->task->append($data);
        return $this;
    }


    /**
     * @return self
     */
    public function markTaskComplete()
    {
        $this->task->setFinish(microtime(true));
        return $this;
    }


    /**
     * @return Task
     */
    public function getTask()
    {
        return $this->task;
    }


    /**
     * @return self
     */
    public function clearTask()
    {
        $this->task = null;
        return $this;
    }

}
