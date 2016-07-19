<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Timetable;


use Alaress\Date\IClock;
use Alaress\Scheduler\CronScheduler;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Stomp\Client;
use RuntimeException;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Schedule\Trigger\Timetable\TriggerFactory;
use Schoolbox\Realtime\Schedule\Trigger\Timetable\TimetableTrigger;

class TimetableScheduler extends CronScheduler {

    /** @var TimetableTrigger[] */
    protected $triggers = array();

    /** @var Collection|Campus[] */
    protected $campuses;

    /** @var TriggerFactory */
    protected $factory;

    /**
     * @param LoopInterface $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param Client $client
     * @param LoggerInterface $log
     * @param Collection|Campus[] $campuses
     * @param TriggerFactory $factory
     * @param IConfigLoader $conf
     * @param IClock $clock
     */
    public function __construct($loop, $dispatchStrategy, $client, $log, $campuses, $factory, $conf = null, $clock = null) {
        parent::__construct($loop, $dispatchStrategy, $log, $conf, $clock);
        $this->method = DefaultDispatchStrategy::TASK_TIMETABLE;
        $this->factory = $factory;
        $this->factory->setClock($this->clock);

        $this->campuses = $campuses;

        $this->on(SIGHUP, array($this, 'onReload'));

        // instantiate schedule
        $this->reload();
    }


    /**
     * Callback executed when SIGHUP received by process
     */
    public function onReload() {
        $this->conf->dropCache();
        $this->reload();
    }


    /**
     * Drop existing schedule and reinitialise
     *
     * @return bool
     */
    public function reload() {
        // drop existing
        foreach($this->triggers as $k => $t) {
            $this->unschedule($t);
            unset($this->triggers[$k]);
        }

        $labels = [
            TimetableTrigger::TARGET_START,
            TimetableTrigger::TARGET_WARN,
            TimetableTrigger::TARGET_END
        ];

        // read each campus, and schedule the next event based on it
        foreach($this->campuses as $campus) {
            // one each for start, start warning, end
            $id = $campus->getId();
            try {
                foreach ($labels as $label) {
                    $this->triggers["$id.$label"] = $this->factory->buildTrigger($label, $campus);
                }
            }
            catch(InvalidArgumentException $e) {
                $this->log->error(get_class($e) . "in TimetableScheduler: " . $e->getMessage(), [ $e ]);
            }
            catch(RuntimeException $e) {
                $this->log->error(get_class($e) . "in TimetableScheduler: " . $e->getMessage(), [ $e ]);
            }
        }

        foreach ($this->triggers as $id => $trigger) {
            try {
                $this->scheduleNotification($trigger);
            }
            catch(InvalidArgumentException $e) {
                $this->log->error(get_class($e) . "in TimetableScheduler: " . $e->getMessage(), [ $e ]);
            }
            catch(RuntimeException $e) {
                $this->log->error(get_class($e) . "in TimetableScheduler: " . $e->getMessage(), [ $e ]);
            }

        }
    }


    /**
     * Report in an appropriate way, based on the data returned by the worker
     *
     * @param $data
     * @return mixed
     */
    protected function report($data)
    {
        $this->log->notice("Timetable alerts sent: ".unserialize($data[0]));
    }

}
