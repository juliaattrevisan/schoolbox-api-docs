<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Timetable;

use Alaress\Date\IClock;
use OutOfBoundsException;
use Schoolbox\Campus;

class TriggerFactory
{

    /** @var callable[] */
    private $strats;


    /**
     * @param IClock $clock
     */
    public function __construct(IClock $clock)
    {
        $this->strats = [
            TimetableTrigger::TARGET_START => function (Campus $campus) use ($clock) {
                return new StartTrigger($campus, $clock);
            },
            TimetableTrigger::TARGET_WARN  => function (Campus $campus) use ($clock) {
                return new WarnTrigger($campus, $clock);
            },
            TimetableTrigger::TARGET_END   => function (Campus $campus) use ($clock) {
                return new EndTrigger($campus, $clock);
            },
        ];
    }


    /**
     * @param string $label
     * @param Campus $campus
     * @throws OutOfBoundsException
     * @return TimetableTrigger
     */
    public function buildTrigger($label, Campus $campus)
    {
        if (array_key_exists($label, $this->strats)) {
            return $this->strats[$label]($campus);
        }

        throw new OutOfBoundsException("No trigger for '$label' exists.");
    }
}
