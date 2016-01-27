<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Timetable;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Date\Clock;
use Alaress\Date\IClock;
use External_User;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Schoolbox\Campus;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Schedule\Trigger\Timetable\TimetableTrigger;
use Schoolbox\Realtime\WorkNotifier;

class TimetableNotifier extends WorkNotifier {

    /** @var Notif */
    protected $notifier;

    /** @var IClock */
    protected $clock;


    /**
     * @param IConfigLoader   $configLoader
     * @param LoggerInterface $log
     * @param IClock          $clock
     */
    public function __construct($configLoader = null, $log = null, IClock $clock = null)
    {
        parent::__construct($configLoader, $log);
        if ($clock instanceof IClock) {
            $this->clock = $clock;
        }
    }


    /**
     * @return Notif
     */
    public function notifier()
    {
        // lazyload
        if (empty($this->notifier)) {
            $this->notifier = new Notif('alert', $this->conf);
        }
        return $this->notifier;
    }


    /**
     * @return IClock
     */
    public function clock()
    {
        // lazyload
        if (empty($this->clock)) {
            $this->clock = new Clock();
        }
        return $this->clock;
    }


    /**
     * This method is executed by a worker
     *
     * @param Campus $campus
     * @return int the number of actions taken
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

        $handler = new \External_Timetable();
        $day     = $handler->getDayNumber($campus);
        $defn    = $handler->getTimetableDefinition(null, $campus, $day);
        $len     = count($defn['number']);
        $stamp   = $this->clock()->time();
        $stamp   = $stamp - ($stamp % 60);

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

            if ($target == TimetableTrigger::TARGET_END) {
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
            } elseif ($target == TimetableTrigger::TARGET_START) {
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
            } elseif ($target == TimetableTrigger::TARGET_WARN) {
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
     * @param Campus  $campus
     * @param string  $body
     * @param string  $title
     * @param string  $icon
     * @return int
     */
    protected function sendAlert($campus, $body, $title = '', $icon = '')
    {
        $campusId = $campus->getId();

        $this->log()->debug("Alerting campus $campusId");

        $bob = $this->newBob()
                    ->setTitle($body)
                    ->setTopics(array("notify.campus.$campusId"))
                    ->putMeta('title', $title ?: 'Timetable')
                    ->setIcon($icon ?: "/images/icon.php?icon=clock&size=64"); // {$this->iconSize}

        $json = $bob->build()->jsonSerialize();

        // get the IDs of all users in this campus
        $stmt = \Database::getInstance()->prepare("
            SELECT u.id
            FROM `user` u
            LEFT JOIN userCampus c on u.id = c.uid
            WHERE c.campus = ?
        ");

        $stmt->execute(array($campusId));

        $i = 0;
        $n = $this->notifier();

        while (++$i && $id = $stmt->fetchColumn()) {
            $n->logTo("instant.user.$id.alert", Logger::INFO, json_encode($json));
        }
        $this->log()->debug("Alerted $i users");

        return $i;
    }

}