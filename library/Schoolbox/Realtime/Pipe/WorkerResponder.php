<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Pipe;

use Schoolbox\Realtime\ProcessException;

class WorkerResponder
{

    const PROC = './worker.php';
    const ERR_LOG = "/tmp/error-output.txt";
    const CWD  = null;

    protected $command;
    protected $args;

    protected $pp;

    protected $pipes;

    public $launchtime;

    /**
     * @param string $command
     * @param string $args
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Realtime\ProcessException
     */
    public function __construct($command, $args)
    {

        $this->command = (string)$command;
        $this->args = json_encode($args);

        if (!is_string($this->command)) {
            throw new \InvalidArgumentException("Illegal command supplied: must be a string");
        }

        $descriptorSpec = array(
//          0 => array("pipe", "r"), // stdin is a pipe that the child will read from
            1 => array("pipe", "w"), // stdout is a pipe that the child will write to
            2 => array("file", 'err.log', 'a')  // stderr
        );

        $env = array(
            'c' => $this->command,
            'a' => $this->args
        );

        $this->pp = proc_open(self::PROC, $descriptorSpec, $this->pipes, self::CWD, $env);

        $this->launchtime = microtime(true);

        if (!is_resource($this->pp)) {
            throw new ProcessException("Failed to start worker process");
        }

        foreach ($this->pipes as &$pipe) {
            stream_set_blocking($pipe, 0);
        }
    }


    /**
     * @return int
     */
    public function stop()
    {
        foreach ($this->pipes as &$pipe) {
            @fclose($pipe);
        }

        $exitStatus = proc_close($this->pp);
        unset($this->pp);
        return $exitStatus;
    }


    /**
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }


//  public function getInputStream() {
//      return $this->pipes[0];
//  }

    public function getOutputStream()
    {
        return $this->pipes[1];
    }

//  public function getErrorStream() {
//      return $this->pipes[2];
//  }
}
