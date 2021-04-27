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
use Schoolbox\CampusMapper;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\GenericCollection;
use Schoolbox\RoleMapper;


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

        foreach ($modules as $module) {
            $api[$module] = new Api($this->host, $this->key, $module);
        }

        /* A list of all the campuses from which we can chooze
         *
         * Get all the campuses but we can't index them for quick lookup
         * since they are allowed to have the same name and ext_timetable_code
         * via our ui...
         */
        $campuses = CampusMapper::getInstance()->findAll();

        /* Purge previously-imported events and event relationships
         */
        return (new GenericCollection(IStrategy::class))
            ->add(new ParentCalendar(
                $this->log,
                $this->perform,
                $api,
                $campuses,
                $this->eventMapper,
                $this->eventSourceMapper,
                $this->roleMapper
            ))
            ->add(new ParentNotices($this->log, $this->perform, $api))
            ->add(new StudentCalendar($this->log, $this->perform, $api))
            ->add(new StudentNotices($this->log, $this->perform, $api))
            ->add(new TeacherCalendar($this->log, $this->perform, $api))
            ->add(new TeacherNotices($this->log, $this->perform, $api))
            ;
    }
}
