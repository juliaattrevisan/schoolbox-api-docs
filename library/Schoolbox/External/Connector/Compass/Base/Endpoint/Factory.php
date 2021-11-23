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
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\GenericCollection;
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
    private EventTypeMapper $eventTypeMapper;


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
     * @var ClassEntryMapper
     */
    private ClassEntryMapper $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private ClassInvolvementMapper $classInvolvementMapper;


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
     * @param ClassEntryMapper                  $classEntryMapper
     * @param ClassInvolvementMapper            $classInvolvementMapper
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
        UserMapper $userMapper,
        EventMapper $eventMapper,
        \Event\Source\Mapper $eventSourceMapper,
        EventTypeMapper $eventTypeMapper,
        PersonalMapper $relationalPersonalMapper,
        CategoryMapper $relationFolderMapper,
        \Schoolbox\Resource\Folder\Mapper $folderMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper
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
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
    }


    /**
     * @return GenericCollection
     * @throws UnexpectedValueException
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function produce(): GenericCollection
    {
        $api = new Api($this->host, $this->key);

        /* Default event owner
         */
        $credentials = (new Codec\DSN())->decode($this->key);

        try {
            $defaultOwner = $this->userMapper->findById($credentials['timetableowner']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->log->info(sprintf(
                'Timetable owner user account %s not found, but we needed that. Exiting',
                $credentials['timetableowner']
            ));

            return new GenericCollection(IStrategy::class);
        }

        /* Default event type, I guess
         */
        $defaultEventType = $this->findDefaultEventType($defaultOwner);

        return (new GenericCollection(IStrategy::class))
            /* Purge previously-imported profile relationships and classes
             */
            ->add(new Profile\Prepare(
                $this->log,
                $this->perform,
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper,
                $this->relationshipMapper
            ))
            ->add(new Profile\Profile(
                $this->log,
                $this->perform,
                $api,
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper,
                $this->relationshipMapper
            ))
            ->add(new Profile\Cleanup(
                $this->log,
                $this->perform,
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper,
                $this->relationshipMapper
            ))
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
                $this->folderMapper,
                $this->classEntryMapper,
                $this->classInvolvementMapper,
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
     * @param \Schoolbox\User $defaultOwner
     * @return EventType
     * @throws UnexpectedValueException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     */
    private function findDefaultEventType(\Schoolbox\User $defaultOwner): EventType
    {
        /* Arbitrarily put this event into sequence 999
         * The next time someone saves this list, they can put it wherever they want
         */
        $sequence = 999;

        try {
            $defaultEventType = $this->eventTypeMapper->findOneByColumn('eventType', self::TIMETABLE_EVENT_TYPE);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $defaultEventType = $this->eventTypeMapper->createModel()
                ->setType(self::TIMETABLE_EVENT_TYPE)
                ->setColor(self::TIMETABLE_COLOR)
                ->setUpdatedBy($defaultOwner->getId())
                ->setSequence($sequence);

            $this->eventTypeMapper->save($defaultEventType);
        }

        return $defaultEventType;
    }
}
