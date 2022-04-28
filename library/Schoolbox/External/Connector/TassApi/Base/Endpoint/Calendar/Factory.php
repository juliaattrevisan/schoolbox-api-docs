<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar;

use Psr\Log\LoggerInterface;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\CampusMapper;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\ApiCertification;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Exception\MissingModule;
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
     * Event source is an arbitrary string that helps identify the thing that imported
     * them into the local event table so you can do a mass remove if needed
     */
    public const SOURCE_NAME = 'suds_tass';


    /**
     * Collect events from this many months in the past (a negative int)
     */
    private const FROM = -1;


    /**
     * Collect events to this many months in the future
     */
    private const TO = 12;


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
     * @var CampusMapper
     */
    private \Schoolbox\CampusMapper $campusMapper;


    /**
     * @var EventTypeMapper
     */
    private \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper;


    /**
     * @var \Schoolbox\External\Event\Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var \Schoolbox\External\Event\Roletype\Mapper
     */
    private \Schoolbox\External\Event\Roletype\Mapper $roletypeRelationMapper;


    /**
     * @var \Schoolbox\External\Event\Campus\Mapper
     */
    private \Schoolbox\External\Event\Campus\Mapper $campusRelationMapper;


    /**
     * @var RoleMapper
     */
    private RoleMapper $roleMapper;


    /**
     * Factory constructor.
     *
     * @param LoggerInterface                           $log
     * @param bool                                      $perform
     * @param string                                    $host
     * @param string                                    $key
     * @param CampusMapper                              $campusMapper
     * @param EventTypeMapper                           $eventTypeMapper
     * @param \Schoolbox\External\Event\Mapper          $eventMapper
     * @param \Schoolbox\External\Event\Roletype\Mapper $roletypeRelationMapper
     * @param \Schoolbox\External\Event\Campus\Mapper   $campusRelationMapper
     * @param RoleMapper                                $roleMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        string $host,
        string $key,
        \Schoolbox\CampusMapper $campusMapper,
        \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper,
        \Schoolbox\External\Event\Mapper $eventMapper,
        \Schoolbox\External\Event\Roletype\Mapper $roletypeRelationMapper,
        \Schoolbox\External\Event\Campus\Mapper $campusRelationMapper,
        RoleMapper $roleMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->host = $host;
        $this->key = $key;
        $this->campusMapper = $campusMapper;
        $this->eventTypeMapper = $eventTypeMapper;
        $this->eventMapper = $eventMapper;
        $this->roletypeRelationMapper = $roletypeRelationMapper;
        $this->campusRelationMapper = $campusRelationMapper;
        $this->roleMapper = $roleMapper;
    }


    /**
     * @return null|GenericCollection
     * @throws \DomainException
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function produce(): ?GenericCollection
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
        } catch (MissingModule $e) {
            /* You tried to access a module that wasn't specified in the dsn
             * Tell me that, then exit quietly; the TASS API calendar is optional
             */
            $this->log->debug(sprintf(
                'Tried to access TASS module %s, but it was not specified in School Data Endpoint key.',
                $module
            ));

            return null;
        } catch (\DomainException $e) {
            /* schoolcalendarandnotices might not be enabled for a school who chooses to eschew
             * this feature; instead of catastrophically dying with a fatal, just accumulate quietly
             */
            $dsnErrors[] = $e->getMessage();
        }

        /* Get a list of the errors, log 'em, then leave
         */
        if (\count($dsnErrors)) {
            foreach ($dsnErrors as $dsnError) {
                $this->log->warning($dsnError);
            }

            throw new \DomainException(sprintf(
                'DSN Errors: %s',
                implode(', ', $dsnErrors)
            ));
        }

        /* A list of the event types that can be associated with an event that is
         * indexed by the eventType column which I have presumed to be unique
         */
        $eventTypes = $this->eventTypeMapper->findAll()->indexBy('type');

        /* Without event types we can't import anything - they're mandatory
         */
        if (!$eventTypes->count()) {
            throw new \DomainException('There needs to be at least one Event Type to import events.');
        }

        /* A list of all the campuses from which we can chooze
         *
         * Get all the campuses but we can't index them for quick lookup
         * since they are allowed to have the same name and ext_timetable_code
         * via our ui...
         */
        $campuses = $this->campusMapper->findAll();

        /* Obtain events between these two datetimes
         */
        $from = \Alaress_Date_Format::now()->addMonths(self::FROM);
        $to = \Alaress_Date_Format::now()->addMonths(self::TO);

        /* Remember statistics generated procedurally from all the tass products
         * and dump them all at the very end, together - people weren't reading
         * the logs properly and assumed incorrectly when the last product
         * (typically getTeacherNotices) had 0 events in it. People presumed that
         * to be attributable to the entire import which isn't always true.
         *
         * Therefore collect all those notes and put them all together here
         */
        $registry = new Registry();

        /* Purge previously-imported events and event relationships
         */
        return (new GenericCollection(IStrategy::class))
            ->add(new Prepare(
                $this->log,
                $this->perform,
                $this->eventMapper,
                self::SOURCE_NAME
            ))
            ->add(new ParentCalendar(
                $this->log,
                $this->perform,
                $api,
                $campuses,
                $eventTypes,
                $this->eventMapper,
                $this->roletypeRelationMapper,
                $this->campusRelationMapper,
                $this->roleMapper,
                self::SOURCE_NAME,
                $registry,
                $from,
                $to
            ))
            ->add(new ParentNotices(
                $this->log,
                $this->perform,
                $api,
                $campuses,
                $eventTypes,
                $this->eventMapper,
                $this->roletypeRelationMapper,
                $this->campusRelationMapper,
                $this->roleMapper,
                self::SOURCE_NAME,
                $registry,
                $from,
                $to
            ))
            ->add(new StudentCalendar(
                $this->log,
                $this->perform,
                $api,
                $campuses,
                $eventTypes,
                $this->eventMapper,
                $this->roletypeRelationMapper,
                $this->campusRelationMapper,
                $this->roleMapper,
                self::SOURCE_NAME,
                $registry,
                $from,
                $to
            ))
            ->add(new StudentNotices(
                $this->log,
                $this->perform,
                $api,
                $campuses,
                $eventTypes,
                $this->eventMapper,
                $this->roletypeRelationMapper,
                $this->campusRelationMapper,
                $this->roleMapper,
                self::SOURCE_NAME,
                $registry,
                $from,
                $to
            ))
            ->add(new TeacherCalendar(
                $this->log,
                $this->perform,
                $api,
                $campuses,
                $eventTypes,
                $this->eventMapper,
                $this->roletypeRelationMapper,
                $this->campusRelationMapper,
                $this->roleMapper,
                self::SOURCE_NAME,
                $registry,
                $from,
                $to
            ))
            ->add(new TeacherNotices(
                $this->log,
                $this->perform,
                $api,
                $campuses,
                $eventTypes,
                $this->eventMapper,
                $this->roletypeRelationMapper,
                $this->campusRelationMapper,
                $this->roleMapper,
                self::SOURCE_NAME,
                $registry,
                $from,
                $to
            ))
            ->add(new Cleanup(
                $this->log,
                $this->perform,
                $this->eventMapper,
                self::SOURCE_NAME
            ))
            ->add(new Summary(
                $this->log,
                $registry
            ))
            ->add(new ApiCertification(
                $this->log,
                $api
            ));
    }
}
