<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Scheduler\Interactive;

use Alaress\Scheduler\StompScheduler;
use Psr\Log\LoggerInterface;

/**
 * Designed to accept data that ultiamtely came from STDIN, to output various data of the state of the scheduler
 *
 * Class Interactor
 * @package Alaress\Scheduler\Interactive
 */
class Interactor
{

    const CMD_STATUS = 'status';
    const CMD_HELP   = 'help';

    /** @var StompScheduler */
    protected $scheduler;

    /** @var LoggerInterface */
    protected $log;

    /**
     * @param StompScheduler $scheduler
     */
    public function __construct($scheduler)
    {
        $this->scheduler = $scheduler;
        $this->log = $scheduler->log;
    }

    /**
     * Handle data from STDIN: expect it to contain a trailing newline
     *
     * @param string $data
     */
    public function handle($data)
    {

        $fields = explode(' ', trim($data));
        $cmd = array_shift($fields);

        switch ($cmd) {
            case self::CMD_STATUS:
                $this->log->notice(print_r($this->scheduler->getStatus($fields), true));
                break;
            case self::CMD_HELP:
                $this->log->notice(<<<EOL
HELP
    status => valid options: pool, schedule
EOL
                );
                break;
            default:
                $this->log->error("Unrecognised command: '$cmd'");
        }
    }
}
