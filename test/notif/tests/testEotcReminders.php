<?php
use Alaress\Async\Test\AbstractTest;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

class EotcReminders extends AbstractTest
{
    const GLENDA_SMITH  = 157;
    const JACKIE_BENSON = 247;
    const ZANE_SMITH    = 151;
    const ANNE_SMITH    = 159;
    const SOPHIA_SAXON  = 160;
    const ALARESS_ADMIN = 19;

    /** @var User[] */
    protected $userCache;


    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     */
    public function setUp()
    {
        // fear not - you are sudo
        $this->log->notice(`mysql schoolbox < /vagrant/sql/custom/stkentigern/eotc-initial.sql 2>&1`);
        $this->log->notice(`mysql schoolbox < /vagrant/sql/notifications-all-instant.sql 2>&1`);
        $this->log->notice(`redis-cli keys "msg:vagrantx:*" | xargs redis-cli del 2>&1`);

        // configure the mappers we're going to use
        $this->configureMapper(UserMapper::getInstance());
        $this->configureMapper(\EOTC\EventPermissionMapper::getInstance());
        $this->configureMapper(\EOTC\EventMapper::getInstance());
        $this->configureMapper(\EOTC\EventMetadataMapper::getInstance());
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
     * Provide the list of daemons that need to be attached for this test
     *
     * @return array
     */
    public function requiredDaemons()
    {
        return [ self::DAEMON_SCHEDULE, self::DAEMON_REROUTE ];
    }


    /**
     * When both daemons are ready to go, 
     *
     * @rerouted  Added \d+ subscriptions
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
            $permission->setSubscribed($statusId);
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

        $eventMapper = \EOTC\EventMapper::getInstance();
        $this->configureMapper($eventMapper);

        $event = new \EOTC\Event();

        try {
            $metadataMapper = \EOTC\EventMetadataMapper::getInstance();
            $metadata = $metadataMapper->findByEvent($event);
        }
        catch (\Exception $e) {
            $metadata = new \EOTC\EventMetadata();
            $metadataStatus = new \EOTC\EventStatus();
            $metadataStatus->setId(\EOTC\EventStatusMapper::STATUS_DEFAULT);
            $metadata->setStatus($metadataStatus);
        }

        $author = $teacherInCharge = $this->getUser(self::JACKIE_BENSON);

        /** @var \Schoolbox\Calendar\EventType $eventType */
        $eventType = \Schoolbox\Calendar\EventTypeMapper::getInstance()->findById(1);

        // set this to be 15 seconds after current time, but tomorrow
        $startDate = Alaress_Date_Format::now()->addSeconds(15)->addDays(1);
        $endDate   = Alaress_Date_Format::now()->addMinutes(5)->addDays(1);

        $event->setAuthor($author);
        $event->setTeacherInCharge($teacherInCharge);
        $event->setType($eventType);
        $event->setTitle('TEST EOTC EVENT');
        $event->setStartDateTime($startDate);
        $event->setEndDateTime($endDate);
        $event->setLocation('TEST EOTC LOCATION');
        $event->setDetail('');
        $event->setAllDay(false);
        $event->setPublishDate('1970-01-01');
        $event->setCampusCollection(new \Schoolbox\Collection([ 1 ]));
        $event->setMetadata($metadata);
        $event->getMetadata()->setRamsFiles(new \Schoolbox\Collection());

        $eventRelations = new \Schoolbox\Calendar\EventRelations(null, null, null);

        $this->log->info("Saving new event to start at {$startDate->getDatabase()}", [ 'char' => '>' ]);

        $eventRecurrenceMapper = new \EOTC\EventRecurrenceMapper($eventMapper);
        $eventRecurrenceMapper->save($event, 'self', '', '', $startDate, $endDate, null, $eventRelations);
    }


    /**
     * @scheduled notify.user.151.eotc.1.upcoming
     * @scheduled notify.user.157.eotc.1.upcoming
     * @scheduled notify.user.159.eotc.1.upcoming
     * @scheduled notify.user.160.eotc.1.upcoming
     * @scheduled notify.user.247.eotc.1.upcoming
     */
    public function onBroadcast()
    {
        $this->log->notice('5 notifications broadcast', [ 'char' => '>' ]);
    }


}
