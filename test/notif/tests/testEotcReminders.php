<?php
use Schoolbox\Mapper;
use Schoolbox\Realtime\Test\AbstractNotificationTest;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

class EotcReminders extends AbstractNotificationTest
{
    const GLENDA_SMITH  = 157;
    const JACKIE_BENSON = 247;
    const ZANE_SMITH    = 151;
    const ANNE_SMITH    = 159;
    const SOPHIA_SAXON  = 160;
    const RYAN_VOLLMER  = 13;
    const ALARESS_ADMIN = 19;

    /** @var User[] */
    protected $userCache;


    /**
     * EotcReminders TestCase constructor.
     */
    public function prepare()
    {
        // fear not - you are sudo
        $this->log->notice(`mysql schoolbox < /vagrant/sql/custom/stkentigern/eotc-initial.sql 2>&1` . 'EOTC Initial applied');
        $this->log->notice(`mysql schoolbox < /vagrant/sql/notifications-all-instant.sql 2>&1` . 'Weapons to maximum');
        $this->log->notice(trim(`redis-cli keys "msg:vagrantx:*" | xargs redis-cli del 2>&1`) . ' queues cleared');

        // configure the mappers we're going to use
        $this->configureMapper(UserMapper::getInstance());
        $this->configureMapper(\EOTC\EventPermissionMapper::getInstance());
        $this->configureMapper(\EOTC\EventMapper::getInstance());
        $this->configureMapper(\EOTC\EventMetadataMapper::getInstance());
    }


    /**
     * Provide the list of daemons that need to be attached for this test
     *
     * @return array
     */
    public function requiredDaemons()
    {
        return [ self::DAEMON_SCHEDULE, self::DAEMON_REROUTE ];
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
     * @param int $daysFromNow
     * @param int $tic
     * @return \EOTC\Event
     */
    protected function createEvent($daysFromNow, $tic = self::RYAN_VOLLMER)
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

        // set this to be (5 + 5n) seconds after current time, but (n) days from now
        $start = Alaress_Date_Format::now()->addSeconds(5 + 5 * $daysFromNow)->addDays($daysFromNow);
        $end   = Alaress_Date_Format::now()->addMinutes(5)->addDays($daysFromNow);

        $event->setAuthor($author);
        $event->setTeacherInCharge($tic);
        $event->setType($type);
        $event->setTitle("Event $daysFromNow Days From Now");
        $event->setStartDateTime($start);
        $event->setEndDateTime($end);
        $event->setLocation('TEST EOTC LOCATION');
        $event->setDetail('');
        $event->setAllDay(false);
        $event->setPublishDate('1970-01-01');
        $event->setCampusCollection(new \Schoolbox\Collection([ 1 ]));
        $event->setMetadata($meta);
        $event->getMetadata()->setRamsFiles(new \Schoolbox\Collection());

        return $event;
    }

    protected function persistEvent(\EOTC\Event $event)
    {
        $startDate = $event->getStartDateTime();
        $endDate   = $event->getEndDateTime();

        $eventRelations = new \Schoolbox\Calendar\EventRelations(null, null, null);

        $this->log->info("Saving new event to start at {$startDate->getDatabase()}", [ 'char' => '>' ]);

        $eventRecurrenceMapper = new \EOTC\EventRecurrenceMapper(\EOTC\EventMapper::getInstance());
        $eventRecurrenceMapper->save($event, 'self', '', '', $startDate, $endDate, null, $eventRelations);
    }


    /**
     * When both daemons are ready to go, 
     *
     * @rerouted  Added (?P<count>\d+) subscriptions
     * @scheduled Connected to STOMP
     */
    public function onReady()
    {
        $this->log->info("Setting EOTC permissions", [ 'char' => '>' ]);

        $permissionMapper = \EOTC\EventPermissionMapper::getInstance();
        $this->configureMapper($permissionMapper, self::ALARESS_ADMIN);

        // this is userId => statusLookupId
        $map = [
            self::ZANE_SMITH   => 1,
            self::SOPHIA_SAXON => 2,
            self::ANNE_SMITH   => 2,
            self::GLENDA_SMITH => 3,
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
        $this->log->info("Permissions set", [ 'char' => '<' ]);

        // create a new event to start tomorrow
        $this->persistEvent($this->createEvent(10));
        $this->persistEvent($this->createEvent(3));
        $this->persistEvent($this->createEvent(2));
        $this->persistEvent($this->createEvent(1));

        // create an event to start tomorrow, but transition it to

    }


    /**
     * @rerouted SEND (?P<messageId>[0-9a-f]{40}:[0-9a-f]{40}) -> instant.user.(?P<userId>\d+)
     *
     * @param string $messageId
     * @param int    $userId
     */
    public function onDelivery($messageId, $userId)
    {
        // known message ID => [ list of people who should be receiving it ]
        // the assertion will fail if this message is received by someone outside this list
        $messageMap = [
            'b388dab10c71d373e5f000c3a4609432560df33d:b0e6848da29cd8a3f069c1b865261ee7a3a50e94' => [ self::ZANE_SMITH, self::RYAN_VOLLMER ]
        ];

        try {
            $message = $this->fetchMessageInstance($messageId)->jsonSerialize();

            $title = $message['title']['text'];

            $this->log->info($title, [ 'char' => '>' ]);

            if (isset($messageMap[$messageId])) {
                $this->assertContains($userId, $messageMap[$messageId]);
            }

        } catch (OutOfBoundsException $e) {
            $this->log->error($e->getMessage(), [$e]);
        }
    }

}
