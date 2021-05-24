<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Timetable;

use Alaress\Date\IClock;
use Alaress\Scheduler\CronScheduler;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Schedule\Trigger\Timetable\TriggerFactory;
use Schoolbox\Realtime\Schedule\Trigger\Timetable\TimetableTrigger;

class TimetableScheduler extends CronScheduler
{

    /** @var Collection|Campus[] */
    protected $campuses;

    /** @var TriggerFactory */
    protected $factory;

    /** @var IConfigLoader */
    private $conf;


    /**
     * @param LoopInterface $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param LoggerInterface $log
     * @param Collection|Campus[] $campuses
     * @param TriggerFactory $factory
     * @param IConfigLoader $conf
     * @param IClock $clock
     */
    public function __construct(
        LoopInterface $loop,
        DispatchStrategyInterface $dispatchStrategy,
        LoggerInterface $log,
        Collection $campuses,
        TriggerFactory $factory,
        IConfigLoader $conf,
        IClock $clock
    ) {
        parent::__construct($loop, $dispatchStrategy, $log, $clock);

        $this->factory  = $factory;
        $this->campuses = $campuses;
        $this->conf     = $conf;

        // instantiate schedule
        $this->reload();
    }


    /**
     * (Re)compute the triggers for this particular scheduler
     *
     * @return TriggerInterface[]
     */
    protected function computeTriggers()
    {
        $triggers = [];

        $labels = [
            TimetableTrigger::TARGET_START,
            TimetableTrigger::TARGET_WARN,
            TimetableTrigger::TARGET_END
        ];

        // read each campus, and schedule the next event based on it
        foreach ($this->campuses as $campus) {
            // one each for start, start warning, end
            $id = $campus->getId();

            try {
                foreach ($labels as $label) {
                    $triggers["$id.$label"] = $this->factory->buildTrigger($label, $campus);
                }
            } catch (InvalidArgumentException $e) {
                $this->logException($e);
            } catch (RuntimeException $e) {
                $this->logException($e);
            }
        }

        return $triggers;
    }


    /**
     * Report in an appropriate way, based on the data returned by the worker
     *
     * @param array $data
     * @return void
     */
    protected function report($data)
    {
        $this->log->notice('Timetable alerts sent: ' . unserialize($data[0]));
    }


    /**
     * @return string
     */
    protected function getMethod()
    {
        return DefaultDispatchStrategy::TASK_TIMETABLE;
    }


    /**
     * @return void
     */
    protected function dropCache()
    {
        $this->conf->dropCache();
    }
}
