<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint;

use Event\Source\Mapper;
use Psr\Log\LoggerInterface;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Calendar\Relation\CategoryMapper;
use Schoolbox\Calendar\Relation\PersonalMapper;
use Schoolbox\CampusMapper;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Class Factory
 *
 * The overall import recipe. A composite of multiple classes each of which has its own
 * particular responsibility. The composite execute each of its pieces in sequence.
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint
 */
class Factory
{

    /**
     * luEventType setup
     */
    private const TIMETABLE_EVENT_TYPE = 'Timetable';
    private const TIMETABLE_COLOR = '#dedede';


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
     * @var UserProfile\Mapper
     */
    private UserProfile\Mapper $baseProfileMapper;


    /**
     * @var UserProfile\Student\Mapper
     */
    private UserProfile\Student\Mapper $studentProfileMapper;


    /**
     * @var UserProfile\Parent\Mapper
     */
    private UserProfile\Parent\Mapper $parentProfileMapper;


    /**
     * @var UserProfile\Staff\Mapper
     */
    private UserProfile\Staff\Mapper $staffProfileMapper;


    /**
     * @var UserProfile\Relationship\Mapper
     */
    private UserProfile\Relationship\Mapper $relationshipMapper;


    /**
     * @var UserMapper
     */
    private UserMapper $userMapper;


    /**
     * @var EventMapper
     */
    private EventMapper $eventMapper;


    /**
     * @var Mapper
     */
    private \Event\Source\Mapper $eventSourceMapper;


    /**
     * @var EventTypeMapper
     */
    private \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper;
    

    /**
     * @var PersonalMapper
     */
    private PersonalMapper $relationPersonalMapper;


    /**
     * @var CategoryMapper
     */
    private CategoryMapper $relationFolderMapper;


    /**
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private \Schoolbox\Resource\Folder\Mapper $folderMapper;


    /**
     * Factory constructor.
     *
     * @param LoggerInterface                   $log
     * @param bool                              $perform
     * @param string                            $host
     * @param string                            $key
     * @param UserProfile\Mapper                $baseProfileMapper
     * @param UserProfile\Student\Mapper        $studentProfileMapper
     * @param UserProfile\Parent\Mapper         $parentProfileMapper
     * @param UserProfile\Staff\Mapper          $staffProfileMapper
     * @param UserProfile\Relationship\Mapper   $relationshipMapper
     * @param UserMapper                        $userMapper
     * @param EventMapper                       $eventMapper
     * @param Mapper                            $eventSourceMapper
     * @param EventTypeMapper                   $eventTypeMapper
     * @param PersonalMapper                    $relationalPersonalMapper
     * @param CategoryMapper                    $relationFolderMapper
     * @param \Schoolbox\Resource\Folder\Mapper $folderMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        string $host,
        string $key,
        /* Profile pieces */
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper,
        /* Timetable pieces */
        \Schoolbox\UserMapper $userMapper,
        EventMapper $eventMapper,
        \Event\Source\Mapper $eventSourceMapper,
        \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper,
        PersonalMapper $relationalPersonalMapper,
        CategoryMapper $relationFolderMapper,
        \Schoolbox\Resource\Folder\Mapper $folderMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->host = $host;
        $this->key = $key;

        /* Profile pieces
         */
        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;

        /* Timetable pieces
         */
        $this->userMapper = $userMapper;
        $this->eventMapper = $eventMapper;
        $this->eventSourceMapper = $eventSourceMapper;
        $this->eventTypeMapper = $eventTypeMapper;
        $this->relationPersonalMapper = $relationalPersonalMapper;
        $this->relationFolderMapper = $relationFolderMapper;
        $this->folderMapper = $folderMapper;
    }


    /**
     * @return GenericCollection
     * @throws UnexpectedValueException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function produce(): GenericCollection
    {
        $api = new Api($this->host, $this->key);

        /* Default event owner
         */
        $defaultOwner = $this->userMapper->findByUsername(UserMapper::USERNAME_ALARESS_ADMIN);

        /* Default event type, I guess
         */
        $defaultEventType = $this->findDefaultEventType();

        return (new GenericCollection(IStrategy::class))
            /* Purge previously-imported profile relationships and classes
             */
//            ->add(new Profile\Prepare(
//                $this->log,
//                $this->perform,
//                $this->baseProfileMapper,
//                $this->studentProfileMapper,
//                $this->parentProfileMapper,
//                $this->staffProfileMapper,
//                $this->relationshipMapper
//            ))
//            ->add(new Profile\Profile(
//                $this->log,
//                $this->perform,
//                $api,
//                $this->baseProfileMapper,
//                $this->studentProfileMapper,
//                $this->parentProfileMapper,
//                $this->staffProfileMapper,
//                $this->relationshipMapper
//            ))
//            ->add(new Profile\Cleanup(
//                $this->log,
//                $this->perform,
//                $this->baseProfileMapper,
//                $this->studentProfileMapper,
//                $this->parentProfileMapper,
//                $this->staffProfileMapper,
//                $this->relationshipMapper
//           ))
            ->add(new TimetableCalendar\Prepare(
                $this->log,
                $this->perform,
                $this->eventMapper,
                \Event\Source\Mapper::getInstance(),
            ))
            ->add(new TimetableCalendar\Events(
                $this->log,
                $this->perform,
                $api,
                $defaultOwner,
                $defaultEventType,
                $this->userMapper,
                $this->eventMapper,
                $this->eventSourceMapper,
                $this->relationPersonalMapper,
                $this->relationFolderMapper,
                $this->folderMapper
            ))
            ->add(new ApiCertification(
                $this->log,
                $api
            ));
    }


    /**
     * Forcibly create a new luEventType for Compass Timetable events
     * if one doesn't already exist for us to reuse
     *
     * @return EventType
     * @throws UnexpectedValueException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     */
    private function findDefaultEventType(): EventType
    {
        try {
            $defaultEventType = $this->eventTypeMapper->findOneByColumn('eventType', self::TIMETABLE_EVENT_TYPE);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $defaultEventType = $this->eventTypeMapper->createModel()
                ->setType(self::TIMETABLE_EVENT_TYPE)
                ->setColor(self::TIMETABLE_COLOR);

            $this->eventTypeMapper->save($defaultEventType);
        }

        return $defaultEventType;
    }
}
