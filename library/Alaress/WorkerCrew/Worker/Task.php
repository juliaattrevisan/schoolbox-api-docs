<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Worker;

use Psr\Log\LoggerInterface;

class Task
{

    /** @var object */
    protected $id;
    /** @var string */
    protected $command;
    /** @var string */
    protected $args;
    /** @var string */
    protected $data;
    /** @var float */
    protected $start;
    /** @var float */
    protected $finish;


    /**
     * @param object $identifier
     * @param string $command
     * @param string $args
     */
    public function __construct($identifier, $command, $args)
    {
        $this->id      = $identifier;
        $this->command = $command;
        $this->args    = $args;
        $this->data    = '';
        $this->start   = microtime(true);
    }


    /**
     * Append the given data
     *
     * @param string $data
     * @return self
     */
    public function append($data)
    {
        $this->data .= $data;
        return $this;
    }


    /**
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }


    public function getArgs()
    {
        return $this->args;
    }


    /**
     * @param bool $clean
     * @return string
     */
    public function getData($clean = true)
    {
        // clean will remove all non-printable (control) characters from the data
        return $clean ? preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $this->data) : $this->data;
    }


    /**
     * @return float
     */
    public function getFinish()
    {
        return $this->finish;
    }


    /**
     * @return object
     */
    public function getIdentifier()
    {
        return $this->id;
    }


    /**
     * @return float
     */
    public function getStart()
    {
        return $this->start;
    }


    /**
     * @param float $end
     * @return self
     */
    public function setFinish($end)
    {
        $this->finish = $end;
        return $this;
    }


    /**
     * Debug yourself into the given LoggerInterface
     *
     * @param LoggerInterface $log
     */
    public function debug($log)
    {
        if (!$log instanceof LoggerInterface) {
            return;
        }

        $duration = ($this->finish - $this->start) * 1000;

        $log->debug(sprintf(
            "cmd=%s|args=%s|time=%10.04f|data=%s",
            $this->command,
            json_encode($this->args),
            $duration,
            $this->data
        ));
    }
}
