<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\Segment\Strategy\Composite;
use Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Permission\Access\RoleAccessMapper;
use UnexpectedValueException;
use External_UserInterface;

/**
 * Responsible for enumeration of the IStrategy interface
 *
 * @see IStrategy
 */
class StrategyFactory
{

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var RoleAccessMapper */
    private $roleAccessMapper;

    /** @var Access */
    private $access;

    /** @var bool */
    private $staffCanPost;

    /** @var bool */
    private $moderationRequired;

    /** @var bool */
    private $canTargetTeachersOfUsersInFolder;

    /**
     * Is the fixtures module enabled?
     */
    private bool $isFixturesEnabled;

    /** @var External_UserInterface */
    private $externalUser;

    /** @var IConnector */
    private $db;

    /** @var array|array[][] */
    private $knownUserSubjects;

    /**
     * Cache the strategies so repeated calls to make() for the same strat will return the same instance
     *
     * @var IStrategy[]
     */
    private $strategyCache = [];


    /**
     * Constructor
     *
     * @param UserAccessMapper       $userAccessMapper
     * @param bool                   $staffCanPost
     * @param bool                   $moderationRequired
     * @param bool                   $canTargetTeachersOfUsersInFolder
     * @param bool                   $isFixturesEnabled
     * @param Folder\Mapper          $folderMapper
     * @param RoleAccessMapper       $roleAccessMapper
     * @param Access                 $access
     * @param External_UserInterface $externalUser
     * @param IConnector             $db
     * @param array[][]              $knownUserSubjects
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        bool $staffCanPost,
        bool $moderationRequired,
        bool $canTargetTeachersOfUsersInFolder,
        bool $isFixturesEnabled,
        Folder\Mapper $folderMapper,
        RoleAccessMapper $roleAccessMapper,
        Access $access,
        External_UserInterface $externalUser,
        IConnector $db,
        array $knownUserSubjects
    ) {
        $this->userAccessMapper                 = $userAccessMapper;
        $this->staffCanPost                     = $staffCanPost;
        $this->moderationRequired               = $moderationRequired;
        $this->canTargetTeachersOfUsersInFolder = $canTargetTeachersOfUsersInFolder;
        $this->isFixturesEnabled                = $isFixturesEnabled;
        $this->folderMapper                     = $folderMapper;
        $this->roleAccessMapper                 = $roleAccessMapper;
        $this->access                           = $access;
        $this->externalUser                     = $externalUser;
        $this->db                               = $db;
        $this->knownUserSubjects                = $knownUserSubjects;
    }


    /**
     * Indexed by entity type
     *
     * @return bool[]
     */
    public static function getValidNullEntities(): array
    {
        $validNullEntities = [
            Composite::getEntityType() => Composite::isValidNullEntityValue(),
        ];
        foreach (self::getAllSimpleStrategies() as $strategyClass) {
            $validNullEntities[$strategyClass::getEntityType()] = $strategyClass::isValidNullEntityValue();
        }
        return $validNullEntities;
    }


    /**
     * Returns all valid simple entity_type values
     *
     * @return string[]
     */
    public static function getSimpleEntityTypes(): array
    {
        $entityTypes = [];
        foreach (self::getAllSimpleStrategies() as $strategy) {
            $entityTypes[] = $strategy::getEntityType();
        }
        return $entityTypes;
    }


    /**
     * Grouped by entity_type
     *
     * @return string[][]
     *
     * @throws UnexpectedValueException
     * @throws UnknownEntityTypeException
     */
    public function getValidRelationshipModifiers(): array
    {
        $validModifiers = [];
        foreach ($this->makeAllStrategies() as $strategy) {
            $validModifiers[$strategy::getEntityType()] = $strategy->getValidRelationshipModifiers();
        }
        return $validModifiers;
    }


    /**
     * Returns a map of relationshipModifier to set of valid entities
     *
     * @return string[][]
     * @throws UnexpectedValueException
     * @throws UnknownEntityTypeException
     */
    public function getValidRelationshipEntityMap(): array
    {
        // Maps entityType to relationships
        $validSegmentRelationships = $this->getValidRelationshipModifiers();

        $relationshipEntityMap = [];
        foreach (Model::getRelationships() as $relationship) {
            $validEntities = [];
            foreach ($validSegmentRelationships as $entity => $relationships) {
                if (\in_array($relationship, $relationships, true)) {
                    $validEntities[] = $entity;
                }
            }
            $relationshipEntityMap[$relationship] = $validEntities;
        }
        return $relationshipEntityMap;
    }


    /**
     * Returns all simple (ie non-composite) entity types
     *
     * @return string[]|IStrategy[]
     */
    private static function getAllSimpleStrategies(): array
    {
        return [
            Strategy\Campus::class,
            Strategy\FixturesTeam::class,
            Strategy\Folder::class,
            Strategy\ResHouse::class,
            Strategy\Role::class,
            Strategy\RoleType::class,
            Strategy\SchoolHouse::class,
            Strategy\Service::class,
            Strategy\Super::class,
            Strategy\User::class,
            Strategy\Year::class,
        ];
    }


    /**
     * @param string $entityType
     * @return IStrategy
     * @throws UnknownEntityTypeException
     */
    public function makeStrategy(string $entityType): IStrategy
    {
        if (!\array_key_exists($entityType, $this->strategyCache)) {
            $this->strategyCache[$entityType] = $this->makeNewInstance($entityType);
        }
        return $this->strategyCache[$entityType];
    }


    /**
     * @param string $entityType
     * @return ISimpleStrategy
     * @throws UnknownEntityTypeException
     */
    public function makeSimpleStrategy(string $entityType): IStrategy
    {
        if (!\array_key_exists($entityType, $this->strategyCache)) {
            $this->strategyCache[$entityType] = $this->makeNewInstance($entityType);
        }
        return $this->strategyCache[$entityType];
    }


    /**
     * @param string $entityType
     * @return IStrategy
     * @throws UnknownEntityTypeException
     */
    private function makeNewInstance(string $entityType): IStrategy
    {
        switch ($entityType) {
            case Strategy\Composite::getEntityType():
                return new Strategy\Composite();
            case Strategy\User::getEntityType():
                return new Strategy\User($this->userAccessMapper, $this->access, $this->staffCanPost, $this->moderationRequired);
            case Strategy\Super::getEntityType():
                return new Strategy\Super($this->userAccessMapper, $this->access, $this->staffCanPost, $this->moderationRequired);
            case Strategy\Campus::getEntityType():
                return new Strategy\Campus($this->userAccessMapper, $this->access, $this->staffCanPost, $this->moderationRequired);
            case Strategy\Role::getEntityType():
                return new Strategy\Role($this->userAccessMapper, $this->access, $this->staffCanPost, $this->moderationRequired);
            case Strategy\RoleType::getEntityType():
                return new Strategy\RoleType($this->userAccessMapper, $this->access, $this->staffCanPost, $this->moderationRequired);
            case Strategy\SchoolHouse::getEntityType():
                return new Strategy\SchoolHouse($this->userAccessMapper, $this->access, $this->staffCanPost, $this->moderationRequired);
            case Strategy\ResHouse::getEntityType():
                return new Strategy\ResHouse($this->userAccessMapper, $this->access, $this->staffCanPost, $this->moderationRequired);
            case Strategy\Year::getEntityType():
                return new Strategy\Year($this->userAccessMapper, $this->access, $this->staffCanPost, $this->moderationRequired);
            case Strategy\Service::getEntityType():
                return new Strategy\Service($this->userAccessMapper, $this->access, $this->staffCanPost, $this->moderationRequired);
            case Strategy\Folder::getEntityType():
                return new Strategy\Folder(
                    $this->userAccessMapper,
                    $this->access,
                    $this->staffCanPost,
                    $this->moderationRequired,
                    $this->canTargetTeachersOfUsersInFolder,
                    $this->folderMapper,
                    $this->roleAccessMapper,
                    $this->externalUser,
                    $this->knownUserSubjects
                );
            case Strategy\FixturesTeam::getEntityType():
                return new Strategy\FixturesTeam(
                    $this->userAccessMapper,
                    $this->access,
                    $this->staffCanPost,
                    $this->moderationRequired,
                    $this->isFixturesEnabled,
                    $this->db,
                );
        }

        throw new UnknownEntityTypeException("Unrecognised entity type: '{$entityType}'");
    }


    /**
     * Returns the set of all Audience entityType strategies (including composite)
     *
     * @return GenericCollection|IStrategy[]
     * @throws UnknownEntityTypeException
     * @throws UnexpectedValueException If we attempt to insert something other than a simple strategy into the collection
     */
    public function makeAllStrategies(): GenericCollection
    {
        // Insert composite
        $compositeType = Strategy\Composite::getEntityType();
        $strategies    = new GenericCollection(IStrategy::class, [$compositeType => $this->makeStrategy($compositeType)]);

        // Insert all simple strategies
        foreach ($this->makeAllSimpleStrategies() as $simpleStrategy) {
            $strategies[$simpleStrategy::getEntityType()] = $simpleStrategy;
        }

        return $strategies;
    }


    /**
     * Returns the set of all Audience simple entityType strategies
     *
     * @return GenericCollection|ISimpleStrategy[]
     * @throws UnknownEntityTypeException
     * @throws UnexpectedValueException If we attempt to insert something other than a simple strategy into the collection
     */
    public function makeAllSimpleStrategies(): GenericCollection
    {
        $c = new GenericCollection(ISimpleStrategy::class);
        foreach (self::getAllSimpleStrategies() as $strategy) {
            $entityType = $strategy::getEntityType();
            $c->setOffset($entityType, $this->makeStrategy($entityType));
        }
        return $c;
    }
}
