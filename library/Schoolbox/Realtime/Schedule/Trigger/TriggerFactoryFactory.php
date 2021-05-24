<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger;

use Alaress\Scheduler\Event\EventInterface;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;

class TriggerFactoryFactory
{

    /** @var int  */
    private $compression;


    /**
     * TriggerFactoryFactory constructor.
     *
     * @param bool $useCompression
     */
    public function __construct($useCompression = false)
    {
        $this->compression = $useCompression ? 9 : -1;
    }


    /**
     * @param ModuleNotifierInterface $notifier
     * @param EventInterface          $event
     * @return TriggerFactory
     */
    public function spawn(ModuleNotifierInterface $notifier, EventInterface $event)
    {
        return new TriggerFactory($notifier, $event, $this->compression);
    }
}
