<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Timetable;


use Alaress\Date\IClock;
use OutOfBoundsException;
use Schoolbox\Campus;

class TriggerFactory
{
    /** @var IClock */
    protected $clock;

    /** @var callable[] */
    protected $strats;


    /**
     * TriggerFactory constructor.
     */
    public function __construct()
    {
        $this->strats = [
            TimetableTrigger::TARGET_START => function (Campus $campus) { return new StartTrigger($campus, $this->clock); },
            TimetableTrigger::TARGET_WARN  => function (Campus $campus) { return new WarnTrigger($campus, $this->clock);  },
            TimetableTrigger::TARGET_END   => function (Campus $campus) { return new EndTrigger($campus, $this->clock);   },
        ];
    }


    /**
     * @param IClock $clock
     * @return static
     */
    public function setClock($clock)
    {
        $this->clock = $clock;
        return $this;
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
