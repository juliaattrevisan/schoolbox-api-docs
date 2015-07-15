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
            return $this->sendAlert($campus, "$name is now beginning");
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
        if ($name = $this->process($campus, TimetableTrigger::TARGET_END)) {
            return $this->sendAlert($campus, "$name has now completed");
        }
        return 0;
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
//                $this->log()->debug($defn['desc'][$i] . " is in the past, continuing");
                continue;
            }

            if ($target == TimetableTrigger::TARGET_END) {
                // if we're inside this period (excepting margin for error)
                if ($inside) {
                    // only return classes, don't tell us about breaks
                    if ($defn['sequence'][$i] < 2) {
                        $this->log()->info("Alerting $target of ".$defn['desc'][$i]);
                        return $defn['desc'][$i];
                    } else {
                        $this->log()->debug("Suppressed alert of $target of ".$defn['desc'][$i]);
                        return '';
                    }
                }
            } elseif ($target == TimetableTrigger::TARGET_START) {
                // if we're within the MOE window ahead of the start of this period
                if ($inFuture) {
                    // only return classes, don't tell us about breaks
                    if ($defn['sequence'][$i] < 2) {
                        $this->log()->info("Alerting $target of ".$defn['desc'][$i]);
                        return $defn['desc'][$i];
                    } else {
                        $this->log()->debug("Suppressed alert of $target of ".$defn['desc'][$i]);
                        return '';
                    }
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
                    ->setIcon($icon ?: "/images/icon.php?icon=clock&size={$this->iconSize}");

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