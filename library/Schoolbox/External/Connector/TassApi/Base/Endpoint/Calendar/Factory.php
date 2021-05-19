<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar;

use Event\Source\Mapper as EventSourceMapper;
use Psr\Log\LoggerInterface;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\CampusMapper;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\ApiCertification;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\GenericCollection;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;


/**
 * Class Factory
 *
 * The overall import recipe. A composite of multiple classes each of which has its own
 * particular responsibility. The composite execute each of its pieces in sequence.
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar
 */
class Factory
{

    /**
     * Collect events from this many months in the past (a negative int)
     */
    private const FROM = -24;


    /**
     * Collect events to this many months in the future
     */
    private const TO = 24;


    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * @var string
     */
    private string $host;


    /**
     * @var string
     */
    private string $key;


    /**
     * @var EventMapper
     */
    private EventMapper $eventMapper;


    /**
     * @var EventSourceMapper
     */
    private EventSourceMapper $eventSourceMapper;


    /**
     * @var RoleMapper
     */
    private RoleMapper $roleMapper;


    /**
     * Factory constructor.
     *
     * @param LoggerInterface   $log
     * @param bool              $perform
     * @param string            $host
     * @param string            $key
     * @param EventMapper       $eventMapper
     * @param EventSourceMapper $eventSourceMapper
     * @param RoleMapper        $roleMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        string $host,
        string $key,
        EventMapper $eventMapper,
        EventSourceMapper $eventSourceMapper,
        RoleMapper $roleMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->host = $host;
        $this->key = $key;

        $this->eventMapper = $eventMapper;
        $this->eventSourceMapper = $eventSourceMapper;
        $this->roleMapper = $roleMapper;
    }


    /**
     * @return GenericCollection
     * @throws \DomainException
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function produce(): GenericCollection
    {
        $this->log->info('Accessing api endpoint');

        $modules = [
            Codec\DSN::MODULE_SCHOOL_CALENDAR_AND_NOTICES
        ];

        $api = [];
        $dsnErrors = [];

        try {
            foreach ($modules as $module) {
                $api[$module] = new Api($this->host, $this->key, $module);
            }
        } catch (\DomainException $e) {

            /* schoolcalendarandnotices might not be enabled for a school who chooses to eschew
             * this feature; instead of catastrophically dying with a fatal, just exist quietly
             */
            $dsnErrors[] = $e->getMessage();
        }

        /* Get a list of the errors, log 'em, then leave
         */
        if (\count($dsnErrors)) {
            foreach ($dsnErrors as $dsnError) {
                $this->log->warning($dsnError);
            }

            exit();
        }

        /* A list of all the campuses from which we can chooze
         *
         * Get all the campuses but we can't index them for quick lookup
         * since they are allowed to have the same name and ext_timetable_code
         * via our ui...
         */
        $campuses = CampusMapper::getInstance()->findAll();

        /* Obtain events between these two datetimes
         */
        $from = \Alaress_Date_Format::now()->addMonths(self::FROM);
        $to = \Alaress_Date_Format::now()->addMonths(self::TO);

        /* A list of the event types that can be associated with an event that is
         * indexed by the eventType column which I have presumed to be unique
         */
        $eventTypes = EventTypeMapper::getInstance()->findAll()->indexBy('type');

        /* Without event types we can't import anything - they're mandatory
         */
        if (!$eventTypes->count()) {
            $this->log->critical(sprintf(
                'There needs to be at least one Event Type to import events.'
            ));

            exit();
        }

        /* Elected event Owner and Author is arbitrarily this person for now...
         */
        $owner = UserMapper::getInstance()->findByUsername(UserMapper::USERNAME_ALARESS_ADMIN);

        /* Purge previously-imported events and event relationships
         */
        return (new GenericCollection(IStrategy::class))
            ->add(new ParentCalendar(
                $this->log,
                $this->perform,
                $api,
                $campuses,
                $eventTypes,
                $owner,
                $this->eventMapper,
                $this->eventSourceMapper,
                $this->roleMapper,
                $from,
                $to
            ))
            ->add(new ParentNotices(
                $this->log,
                $this->perform,
                $api,
                $campuses,
                $eventTypes,
                $owner,
                $this->eventMapper,
                $this->eventSourceMapper,
                $this->roleMapper,
                $from,
                $to
            ))
            ->add(new StudentCalendar(
                $this->log,
                $this->perform,
                $api,
                $campuses,
                $eventTypes,
                $owner,
                $this->eventMapper,
                $this->eventSourceMapper,
                $this->roleMapper,
                $from,
                $to
            ))
            ->add(new StudentNotices(
                $this->log,
                $this->perform,
                $api,
                $campuses,
                $eventTypes,
                $owner,
                $this->eventMapper,
                $this->eventSourceMapper,
                $this->roleMapper,
                $from,
                $to
            ))
            ->add(new TeacherCalendar(
                $this->log,
                $this->perform,
                $api,
                $campuses,
                $eventTypes,
                $owner,
                $this->eventMapper,
                $this->eventSourceMapper,
                $this->roleMapper,
                $from,
                $to
            ))
            ->add(new TeacherNotices(
                $this->log,
                $this->perform,
                $api,
                $campuses,
                $eventTypes,
                $owner,
                $this->eventMapper,
                $this->eventSourceMapper,
                $this->roleMapper,
                $from,
                $to
            ))
            ->add(new ApiCertification(
                $this->log,
                $api
            ));
    }
}
