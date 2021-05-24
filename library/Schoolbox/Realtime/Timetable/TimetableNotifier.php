<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Timetable;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Date\Clock;
use Alaress\Date\IClock;
use Alaress\Redis;
use InvalidArgumentException;
use Monolog\Logger;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;
use Schoolbox\Campus;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Alert\IPublisher;
use Schoolbox\Realtime\Alert\RedisPublisher;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Trigger\Timetable\TimetableTrigger;
use Schoolbox\Realtime\WorkNotifier;

class TimetableNotifier extends WorkNotifier
{

    /** @var IPublisher */
    private $publisher;

    /** @var IClock */
    protected $clock;


    /**
     * @param IConfigLoader   $configLoader
     * @param LoggerInterface $log
     * @param IClock          $clock
     * @param IPublisher      $publisher
     */
    public function __construct($configLoader = null, $log = null, IClock $clock = null, IPublisher $publisher = null)
    {
        parent::__construct($configLoader, $log);

        if ($clock instanceof IClock) {
            $this->clock = $clock;
        }

        if ($publisher instanceof IPublisher) {
            $this->publisher = $publisher;
        }
    }


    /**
     * @return IPublisher
     */
    public function publisher()
    {
        // lazyload
        if ($this->publisher === null) {
            $this->publisher = new RedisPublisher(Redis::getClientInstance(), INSTANCE_ID);
        }
        return $this->publisher;
    }


    /**
     * @return IClock
     */
    public function clock()
    {
        // lazyload
        if ($this->clock === null) {
            $this->clock = new Clock();
        }
        return $this->clock;
    }


    /**
     * This method is executed by a worker
     *
     * @param Campus $campus
     * @return int the number of actions taken
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    public function processStart($campus)
    {
        if ($name = $this->process($campus, TimetableTrigger::TARGET_START)) {
            return $this->sendAlert($campus, "Start of {$name}");
        }
        return 0;
    }


    /**
     * This method is executed by a worker
     *
     * @param Campus $campus
     * @return int the number of actions taken
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    public function processWarn($campus)
    {
        if ($name = $this->process($campus, TimetableTrigger::TARGET_WARN)) {
            return $this->sendAlert($campus, "{$name} starts in 5 minutes");
        }
        return 0;
    }


    /**
     * This method is executed by a worker
     *
     * @param Campus $campus
     * @return int the number of actions taken
     */
    public function processEnd($campus)
    {
        // According to the brief in [SB-7500] they don't want period end notifications.
        // They want "Start of Period X", and not "Period Y has now completed", so just bombing out here to suppress
        // the alert. Leaving this here in case they change their mind.

        return 0;

        /*
        if ($name = $this->process($campus, TimetableTrigger::TARGET_END)) {
            return $this->sendAlert($campus, "$name has now completed");
        }
        return 0;
        */
    }


    /**
     * @param Campus $campus
     * @param string $target
     * @return string the relevant period name
     */
    public function process($campus, $target = '')
    {
        if (!$target) {
            return 0;
        }

        try {
            $handler = new \External_Timetable();
        } catch (\Exception $e) {
            $this->log()->critical('Failed to connect to external timetable, no alerts sent', ['exception' => $e]);
            return 0;
        }

        $day     = $handler->getDayNumber($campus);
        $defn    = $handler->getTimetableDefinition(null, $campus, $day);
        $len     = count($defn['number']);
        $stamp   = $this->clock()->time();
        $stamp  -= ($stamp % 60);

        for ($i = 0; $i < $len; ++$i) {
            $start    = strtotime($defn['start'][$i]);
            $end      = strtotime($defn['end'][$i]);
            $inside   = $stamp > $start && $stamp <= $end;
            $inFuture = $stamp <= $start;
            $inPast   = $stamp > $end;

            if ($inPast) {
                // $this->log()->debug($defn['desc'][$i] . " is in the past, continuing");
                continue;
            }

            if ($target === TimetableTrigger::TARGET_END) {
                // if we're inside this period (excepting margin for error)
                if ($inside) {
                    // Apparently they care about breaks now, this should probably be an option in the config
                    // (although the whole module should be a config option).

                    return $defn['desc'][$i];

                    /*// only return classes, don't tell us about breaks
                    if ($defn['sequence'][$i] < 2) {
                        $this->log()->info("Alerting $target of ".$defn['desc'][$i]);
                        return $defn['desc'][$i];
                    } else {
                        $this->log()->debug("Suppressed alert of $target of ".$defn['desc'][$i]);
                        return '';
                    }*/
                }
            } elseif ($target === TimetableTrigger::TARGET_START) {
                // if we're within the MOE window ahead of the start of this period
                if ($inFuture) {
                    // Apparently they care about breaks now, this should probably be an option in the config
                    // (although the whole module should be a config option).

                    return $defn['desc'][$i];

                    /*// only return classes, don't tell us about breaks
                    if ($defn['sequence'][$i] < 2) {
                        $this->log()->info("Alerting $target of ".$defn['desc'][$i]);
                        return $defn['desc'][$i];
                    } else {
                        $this->log()->debug("Suppressed alert of $target of ".$defn['desc'][$i]);
                        return '';
                    }*/
                }
            } elseif ($target === TimetableTrigger::TARGET_WARN) {
                // if we're within the warning period
                if ($inFuture) {
                    // Apparently they care about breaks now, this should probably be an option in the config
                    // (although the whole module should be a config option).

                    return $defn['desc'][$i];

                    /*// only return classes, don't tell us about breaks
                    if ($defn['sequence'][$i] < 2) {
                        $this->log()->info("Alerting $target of ".$defn['desc'][$i]);
                        return $defn['desc'][$i];
                    } else {
                        $this->log()->debug("Suppressed alert of $target of ".$defn['desc'][$i]);
                        return '';
                    }*/
                }
            }
        }

        return '';
    }


    /**
     * @return Builder
     */
    protected function newBob()
    {
        $bob = new Builder();

        // setup defaults
        return $bob
            ->setSeverity(Logger::INFO)
            ->setNamespace('ALERT')
            ->setHref('/timetable')
            ->putMeta('ttl', 30);
    }


    /**
     * @param Campus $campus
     * @param string $body
     * @param string $title
     * @param string $icon
     * @throws OutOfBoundsException
     * @throws InvalidArgumentException
     * @return int
     */
    protected function sendAlert(Campus $campus, $body, $title = '', $icon = '')
    {
        $campusId = $campus->getId();

        $this->log()->debug("Alerting campus $campusId");

        $bob = $this->newBob()
                    ->setTitle($body)
                    ->setTopics([ "notify.campus.$campusId" ])
                    ->putMeta('title', $title ?: 'Timetable')
                    ->setIcon($icon ?: '/images/icon.php?size=64&icon=' . NSMapper::NS_TIMETABLE); // {$this->iconSize}

        $instance = $bob->build();

        // get the IDs of all users in this campus
        $stmt = \Database::getInstance()->prepare('
            SELECT u.id
            FROM `user` u
            LEFT JOIN userCampus c on u.id = c.uid
            WHERE c.campus = ?
        ');

        $stmt->execute([ $campusId ]);

        $i = 0;

        while ($id = $stmt->fetchColumn()) {
            $i += $this->publisher()->alertUser($id, $instance);
        }

        $this->log()->debug("Alerted $i users");

        return $i;
    }
}
