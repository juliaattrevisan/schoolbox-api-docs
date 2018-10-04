<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment;

use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\Segment\Strategy\Composite;
use Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Permission\Access\RoleAccessMapper;
use UnexpectedValueException;

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

    /**
     * Cache the strategies so repeated calls to make() for the same strat will return the same instance
     *
     * @var IStrategy[]
     */
    private $strategyCache = [];


    /**
     * @param UserAccessMapper $userAccessMapper
     * @param bool             $staffCanPost
     * @param bool             $moderationRequired
     * @param Folder\Mapper    $folderMapper
     * @param RoleAccessMapper $roleAccessMapper
     * @param Access           $access
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        bool $staffCanPost,
        bool $moderationRequired,
        Folder\Mapper $folderMapper,
        RoleAccessMapper $roleAccessMapper,
        Access $access
    ) {
        $this->userAccessMapper   = $userAccessMapper;
        $this->staffCanPost       = $staffCanPost;
        $this->moderationRequired = $moderationRequired;
        $this->folderMapper       = $folderMapper;
        $this->roleAccessMapper   = $roleAccessMapper;
        $this->access             = $access;
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
     * Indexed by entity type
     *
     * @return bool[]
     */
    public static function getValidRelationshipModifiers(): array
    {
        $validModifiers = [
            Composite::getEntityType() => Composite::getValidRelationshipModifiers(),
        ];
        foreach (self::getAllSimpleStrategies() as $strategyClass) {
            $validModifiers[$strategyClass::getEntityType()] = $strategyClass::getValidRelationshipModifiers();
        }
        return $validModifiers;
    }


    /**
     * Returns a map of relationshipModifier to set of valid entities
     *
     * @return string[][]
     */
    public static function getValidRelationshipEntityMap(): array
    {
        // Maps entityType to relationships
        $validSegmentRelationships = self::getValidRelationshipModifiers();

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
     * @return IStrategy[]
     */
    private static function getAllSimpleStrategies(): array
    {
        return [
            Strategy\Campus::class,
            Strategy\Folder::class,
            Strategy\PastoralGroup::class,
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
            case Strategy\User::getEntityType():
                return new Strategy\User($this->userAccessMapper, $this->staffCanPost, $this->moderationRequired);
            case Strategy\Super::getEntityType():
                return new Strategy\Super($this->userAccessMapper, $this->staffCanPost, $this->moderationRequired);
            case Strategy\Campus::getEntityType():
                return new Strategy\Campus($this->userAccessMapper, $this->staffCanPost, $this->moderationRequired);
            case Strategy\Role::getEntityType():
                return new Strategy\Role($this->userAccessMapper, $this->staffCanPost, $this->moderationRequired);
            case Strategy\RoleType::getEntityType():
                return new Strategy\RoleType($this->userAccessMapper, $this->staffCanPost, $this->moderationRequired);
            case Strategy\SchoolHouse::getEntityType():
                return new Strategy\SchoolHouse($this->userAccessMapper, $this->staffCanPost, $this->moderationRequired);
            case Strategy\ResHouse::getEntityType():
                return new Strategy\ResHouse($this->userAccessMapper, $this->staffCanPost, $this->moderationRequired);
            case Strategy\Year::getEntityType():
                return new Strategy\Year($this->userAccessMapper, $this->staffCanPost, $this->moderationRequired);
            case Strategy\Service::getEntityType():
                return new Strategy\Service($this->userAccessMapper, $this->staffCanPost, $this->moderationRequired);
            case Strategy\PastoralGroup::getEntityType():
                return new Strategy\PastoralGroup($this->userAccessMapper, $this->staffCanPost, $this->moderationRequired);
            case Strategy\Folder::getEntityType():
                return new Strategy\Folder(
                    $this->userAccessMapper,
                    $this->staffCanPost,
                    $this->moderationRequired,
                    $this->folderMapper,
                    $this->roleAccessMapper,
                    $this->access
                );
        }
        throw new UnknownEntityTypeException("Unrecognised entity type: '{$entityType}'");
    }


    /**
     * Returns the set of all Audience entityType strategies (including composite)
     *
     * @return GenericCollection|ISimpleStrategy[]
     * @throws UnknownEntityTypeException
     * @throws UnexpectedValueException If we attempt to insert something other than a simple strategy into the collection
     */
    public function makeAllStrategies(): GenericCollection
    {
        $c             = $this->makeAllSimpleStrategies();
        $compositeType = Strategy\Composite::getEntityType();
        $c->setOffset($compositeType, $this->makeStrategy($compositeType));
        return $c;
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
