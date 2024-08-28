<?php

use Schoolbox\Mapper;
use Schoolbox\Realtime\Test\AbstractNotificationTestCase;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class EotcReminders extends AbstractNotificationTestCase
{
    const GLENDA_SMITH  = 157;
    const JACKIE_BENSON = 247;
    const ZANE_SMITH    = 151;
    const GARY_GEORGE   = 153;
    const ANNE_SMITH    = 159;
    const SOPHIA_SAXON  = 160;
    const RYAN_VOLLMER  = 13;
    const ALARESS_ADMIN = 19;

    const FIRST_REMINDER = 'first_reminder';
    const DAILY_REMINDER = 'daily_reminder';

    /** @var User[] */
    protected $userCache;


    /**
     * EotcReminders TestCase constructor.
     */
    public function prepare()
    {
        // fear not - you are sudo
        `mysql schoolbox < /usr/share/schoolbox/sql/custom/stkentigern/eotc-initial.sql 2>&1`;
        $this->log->notice('EOTC Initial applied');
        `mysql schoolbox < /usr/share/schoolbox/sql/notifications-all-instant.sql 2>&1`;
        $this->log->notice('Weapons to maximum');
        $this->log->notice(trim(`redis-cli keys "msg:vagrantx:*" | xargs redis-cli del 2>&1`) . ' redis queues cleared');
        `rabbitmqadmin get queue=vagrantx.sb.sched count=10000 requeue=false`;
        $this->log->notice("Rabbit schedule queue cleared");

        // configure the mappers we're going to use
        $this->configureMapper(UserMapper::getInstance());
        $this->configureMapper(\EOTC\EventPermissionMapper::getInstance());
        $this->configureMapper(\EOTC\EventMapper::getInstance());
        $this->configureMapper(\EOTC\EventMetadataMapper::getInstance());
        $this->configureMapper(\EOTC\ConfigMapper::getInstance());
    }


    /**
     * Provide the list of daemons that need to be attached for this test
     *
     * @return array
     */
    public function requiredDaemons()
    {
        return [ self::DAEMON_SCHEDULE ];
    }

    /**
     * Test completion isn't handled yet.
     *
     * @return false
     */
    protected function isTestComplete()
    {
        return false;
    }

    /**
     * Does nothing yet.
     *
     * @param int $stage
     */
    protected function goToStage($stage)
    {
    }

    /**
     * @param int $id
     * @return User
     */
    protected function getUser($id)
    {
        if (empty($this->userCache[$id])) {
            $this->userCache[$id] = UserMapper::getInstance()->findById($id);
        }

        return $this->userCache[$id];
    }


    /**
     * @param Mapper $mapper
     * @param int    $id
     * @return void
     */
    protected function configureMapper(Mapper $mapper, $id = self::JACKIE_BENSON)
    {
        $mapper->setSessionData([
            'user' => $this->getUser($id)
        ]);
    }


    /**
     * @param int  $daysFromNow
     * @param int  $targetStatus
     * @param bool $rams
     * @param int  $tic
     * @return \EOTC\Event
     */
    protected function createEvent($daysFromNow, $targetStatus, $rams = false, $tic = self::RYAN_VOLLMER)
    {
        $event  = new \EOTC\Event();
        $meta   = new \EOTC\EventMetadata();
        $status = new \EOTC\EventStatus();

        $status->setId(\EOTC\EventStatusMapper::STATUS_DEFAULT);
        $meta->setStatus($status);

        $author = UserMapper::getInstance()->getSessionUser();
        $tic    = $this->getUser($tic);

        /** @var \Schoolbox\Calendar\EventType $type */
        $type = \Schoolbox\Calendar\EventTypeMapper::getInstance()->findById(1);

        // push this out; it will get reset once the transitions are complete
        $start = Alaress_Date_Format::now()->addYears(1);
        $end   = Alaress_Date_Format::now()->addYears(1)->addMinutes(5);

        // keep an easy reference of this test case
        $config = [
            'targetStatus' => $targetStatus,
            'daysFromNow'  => $daysFromNow
        ];

        $event->setAuthor($author);
        $event->setTeacherInCharge($tic);
        $event->setType($type);
        $event->setTitle("[[ {$daysFromNow}DaysFromNow; Status" . str_replace(' ', '', EOTC\EventStatusMapper::getInstance()->findById($targetStatus)->getName()) . ' ]]');
        $event->setStartDateTime($start);
        $event->setEndDateTime($end);
        $event->setLocation('TEST EOTC LOCATION');
        $event->setDetail(json_encode($config));
        $event->setAllDay(false);
        $event->setPublishDate('1970-01-01');
        $event->setCampusCollection(new \Schoolbox\Collection([ 1 ]));
        $event->setMetadata($meta);

        // if rams, grab a file from the DB
        $ramsCollection = $rams
            ? Schoolbox\Resource\Storage\Mapper::getInstance()->findByColumn('hash', '9df012f4c34652467d9ca99eeea7472ec8403c99')
            : new \Schoolbox\Collection();

        $event->getMetadata()->setRamsFiles($ramsCollection);
        $event->getMetadata()->setRamsRequired($ramsCollection->count() > 0);

        return $event;
    }


    /**
     * Saves the given EOTC event
     *
     * @param \EOTC\Event $event
     * @param int         $asUser
     */
    protected function persistEvent(\EOTC\Event $event, $asUser = 0)
    {
        $startDate = $event->getStartDateTime();
        $endDate   = $event->getEndDateTime();
        $config    = json_decode($event->getDetail(), true);

        $eventRelations = new \Schoolbox\Calendar\EventRelations(null, null, null);

        $this->log->notice(
            sprintf(
                "Saving new event to start at %s: [%1d, %2d, %s]",
                $startDate->getDatabase(),
                $config['targetStatus'],
                $config['daysFromNow'],
                $event->getMetadata()->getRamsRequired() ? 'R' : '.'
            ),
            [ 'char' => '>' ]
        );

        if ($asUser) {
            // if a session user is specified, configure the mapper to effect as them
            $this->configureMapper(\EOTC\EventMapper::getInstance(), $asUser);
        }

        $eventRecurrenceMapper = new \EOTC\EventRecurrenceMapper(\EOTC\EventMapper::getInstance());
        $eventRecurrenceMapper->save($event, 'self', '', '', $startDate, $endDate, null, $eventRelations);
    }


    /**
     * When both daemons are ready to go,
     *
     * @routed    Added (?P<count>\d+) subscriptions
     * @scheduled Connected to STOMP
     */
    public function onReady()
    {
        $this->log->notice("Setting EOTC permissions", [ 'char' => '>' ]);

        $permissionMapper = \EOTC\EventPermissionMapper::getInstance();
        $this->configureMapper($permissionMapper, self::ALARESS_ADMIN);

        // this is userId => statusLookupId
        $map = [
            self::ZANE_SMITH   => EOTC\EventStatusMapper::STATUS_DEFAULT,
//            self::SOPHIA_SAXON => EOTC\EventStatusMapper::STATUS_INPRINCIPLE,
            self::ANNE_SMITH   => EOTC\EventStatusMapper::STATUS_INPRINCIPLE,
            self::GLENDA_SMITH => EOTC\EventStatusMapper::STATUS_DETAILSCOMPLETED,
            self::GARY_GEORGE  => EOTC\EventStatusMapper::STATUS_AWAITINGFINALAPPROVAL,
        ];

        foreach ($map as $userId => $statusId) {
            $user = $this->getUser($userId);
            $permissionMapper->deleteForUser($user);

            $now = Alaress_Date_Format::now();

            $permission = new \EOTC\EventPermission();
            $permission->setUserId($userId);
            $permission->setCampusId(1);
            $permission->setStatusId($statusId);
            $permission->setAllowed($statusId);
//            $permission->setSubscribed($statusId);
            $permission->setCreatedAt($now);
            $permission->setUpdatedAt($now);

            $permissionMapper->save($permission);
        }
    }


    /**
     * @scheduled No notifier for model 'EOTC\\EventPermission'
     * @scheduled No notifier for model 'EOTC\\EventPermission'
     * @scheduled No notifier for model 'EOTC\\EventPermission'
     * @scheduled No notifier for model 'EOTC\\EventPermission'
     */
    public function onSubscribed()
    {
        $this->log->notice("Permissions set", [ 'char' => '<' ]);

        // retrieve the reminder thresholds
        $configs = \EOTC\ConfigMapper::getInstance()->findByColumn('name', [
            self::FIRST_REMINDER, self::DAILY_REMINDER
        ]);

        $configs = array_combine($configs->getPropertyValues('name'), $configs->getPropertyValues('value'));

        /**
         * We gon create a LOT of events
         * @var EOTC\Event[] $events
         */
        $events = [];

        foreach ([ false, true ] as $ramsRequired) {
            foreach (EOTC\EventStatusMapper::getInstance()->findAll() as $targetStatus) {
                if ($targetStatus->getId() == EOTC\EventStatusMapper::STATUS_REJECTED) {
                    // skip the cancelled status; it be special
                    continue;
                }

                if (!$ramsRequired && $targetStatus->getId() == EOTC\EventStatusMapper::STATUS_DETAILSCOMPLETED) {
                    // skip details complete if rams are not required
                    continue;
                }

                $events[] = $this->createEvent($configs[self::FIRST_REMINDER], $targetStatus->getId(), $ramsRequired);

                // create an event for every day between the threshold and now
                for ($i = $configs[self::DAILY_REMINDER]; $i > 0; --$i) {
                    $events[] = $this->createEvent($i, $targetStatus->getId(), $ramsRequired);
                }
            }
        }


        // cache built - let's do this.
        foreach ($events as $event) {
            $this->persistEvent($event);
        }
    }


    /**
     *
     * @param int         $effectorId
     * @param \EOTC\Event $event
     * @param int         $toStatus
     */
    protected function transitionEvent($effectorId, EOTC\Event $event, $toStatus)
    {
        // set the mapper session data
        $this->configureMapper(EOTC\EventMetadataMapper::getInstance(), $effectorId);

        $status = \EOTC\EventStatusMapper::getInstance()->findById($toStatus);

        $event->getMetadata()->setStatus($status);
        $event->getMetadata()->setComment('Auto Transition');

        $metadataMapper = \EOTC\EventMetadataMapper::getInstance();
        $metadataMapper->save($event->getMetadata());
    }


    /**
     * @scheduled notify.user.(?P<recipientId>\d+).eotc.(?P<eventId>\d+).(?:submitted|statuschanged)
     *
     * @param int $recipientId
     * @param int $eventId
     */
    public function onTransition($recipientId, $eventId)
    {

        // retrieve the event, find out if it's at its target status, and if not, push it forward
        $event     = EOTC\EventMapper::getInstance()->findById($eventId);
        $testCase  = json_decode($event->getDetail(), true);
        $recipient = $this->getUser($recipientId);

        $currentStatus = $event->getMetadata()->getStatus()->getId();
        $targetStatus  = $testCase['targetStatus'];

        if ($currentStatus < $targetStatus) {
            $newStatus = EOTC\EventStatusMapper::getInstance()->findNextStatus($event);

            if ((new EOTC\Authenticator())->isEOTCAdministrator($recipient, $event)) {
                // this user is allowed to transition this event from this state
                $this->log->notice("{$recipient->formatName()} transitioning event #$eventId from $currentStatus to $newStatus", [ 'char' => '>' ]);
                $this->transitionEvent($recipientId, $event, $newStatus);
            } else {
                // block user transition
                $this->log->info("{$recipient->formatName()} cannot transition event #$eventId from $currentStatus to $newStatus");
            }
        } else {
            // target status reached: update event startdate
            $this->log->notice("Event #$eventId has reached its target status of $targetStatus", [ 'char' => '+' ]);

            // if it hasn't been brought forward yet, bring it forward
            if ($event->getStartDateTime()->getYear() > Alaress_Date_Format::now()->getYear()) {
                // we're going to update the event as the original author
                $this->configureMapper(EOTC\EventMapper::getInstance(), $event->getAuthor()->getId());

                $start = Alaress_Date_Format::now()->addDays($testCase['daysFromNow'])->addSeconds(30);

                $this->log->notice("As {$event->getAuthor()->formatName()}, setting event #$eventId start time to {$start->getDatabase()}", [ 'char' => '>' ]);
                $event->setStartDateTime($start);

                EOTC\EventMapper::getInstance()->save($event);
            }
        }
    }


    /**
     * @scheduled Scheduling trigger 'EOTC\\EventMetadata:upcoming' for (?P<datetime>.*)
     *
     * @param string $datetime
     */
    public function onUpcomingScheduled($datetime)
    {
        $now      = Alaress_Date_Format::now()->addMinutes(-1);
        $schedule = new Alaress_Date_Format($datetime, 'Y-m-d H:i:s');

        if ($schedule->getYear() > $now->getYear()) {
            // just show me things from this year
            return;
        }

        $this->log->notice("Scheduling trigger 'EOTC\\EventMetadata:upcoming' for $datetime", [ 'char' => '-' ]);

        if (!$schedule->isSameDay($now)) {
            // this trigger isn't set for today.
            $diff = $schedule->diffInDays($now);
            $this->log->notice(sprintf("This trigger is set for %d day%s from now", $diff, $diff == 1 ? '' : 's'));
        }
    }


    /**
     * @scheduled Executing EOTC\\EventMetadataNotifier::notifyUpcoming
     */
    public function onScheduleFire()
    {
        $this->log->notice("Firing EOTC\\EventMetadataNotifier::notifyUpcoming", [ 'char' => '?' ]);
    }

    /**
     *
     * @routed ^SEND (?P<messageId>[0-9a-f]{40}:[0-9a-f]{40}) -> instant.user.(?P<userId>\d+)
     *
     * @param string $messageId
     * @param int    $userId
     */
    public function onDelivery($messageId, $userId)
    {
        return;
        // TODO the below doesn't quite work

        try {
            $message = $this->fetchMessageInstance($messageId)->jsonSerialize();
        } catch (OutOfBoundsException $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            return;
        }

        if (!preg_match('`^/modules/eotc/status/modify/(\d+)$`', $message['href'], $matches)) {
            // not a message we want to listen to
            return;
        }

        $eventId = $matches[1];
        $event   = EOTC\EventMapper::getInstance()->findById($eventId);

        // won't match 'tomorrow'
        if (preg_match('`starts in (\d+) days, but`', $message['title']['text'], $matches)) {
            $distance = $matches[1];

            if ($distance > 1) {
                // pull this event a day closer
                $this->log->notice("Pulling event #$eventId one day closer", [ 'char' => '>' ]);

                $nowPlus30 = Alaress_Date_Format::now()->addSeconds(30);

                $event->getStartDateTime()->addDays(-1);

                while ($event->getStartDateTime()->lt($nowPlus30)) {
                    $event->getStartDateTime()->addSeconds(10);
                }

                $this->configureMapper(EOTC\EventMapper::getInstance(), $event->getAuthor()->getId());
                EOTC\EventMapper::getInstance()->save($event);
            }
        }
    }
}
