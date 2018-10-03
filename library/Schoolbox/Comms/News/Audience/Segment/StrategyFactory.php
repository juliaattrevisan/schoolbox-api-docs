<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment;

use InvalidArgumentException;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
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

    private const ENTITY_VALUES = [
        EntityType\Campus\Strategy::ENTITY_TYPE,
        EntityType\Folder\Strategy::ENTITY_TYPE,
        EntityType\PastoralGroup\Strategy::ENTITY_TYPE,
        EntityType\ResHouse\Strategy::ENTITY_TYPE,
        EntityType\Role\Strategy::ENTITY_TYPE,
        EntityType\RoleType\Strategy::ENTITY_TYPE,
        EntityType\SchoolHouse\Strategy::ENTITY_TYPE,
        EntityType\Service\Strategy::ENTITY_TYPE,
        EntityType\Super\Strategy::ENTITY_TYPE,
        EntityType\User\Strategy::ENTITY_TYPE,
        EntityType\Year\Strategy::ENTITY_TYPE,
    ];

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var RoleAccessMapper */
    private $roleAccessMapper;

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
     * @param Folder\Mapper    $folderMapper
     * @param RoleAccessMapper $roleAccessMapper
     * @param bool             $moderationRequired
     * @param bool             $staffCanPost
     */
    public function __construct(
        UserAccessMapper $userAccessMapper,
        Folder\Mapper $folderMapper,
        RoleAccessMapper $roleAccessMapper,
        bool $moderationRequired,
        bool $staffCanPost
    ) {
        $this->userAccessMapper   = $userAccessMapper;
        $this->folderMapper       = $folderMapper;
        $this->roleAccessMapper   = $roleAccessMapper;
        $this->moderationRequired = $moderationRequired;
        $this->staffCanPost       = $staffCanPost;
    }


    /**
     * @param string $entityType
     * @param User   $user
     * @return IStrategy
     * @throws InvalidArgumentException
     */
    public function makeStrategy(string $entityType, User $user): IStrategy
    {
        if (!\array_key_exists($entityType, $this->strategyCache)) {
            $this->strategyCache[$entityType] = $this->makeNewStrategyInstance($entityType, $user);
        }
        return $this->strategyCache[$entityType];
    }


    /**
     * @param string $entityType
     * @param User   $user
     * @return IStrategy
     * @throws InvalidArgumentException
     */
    private function makeNewStrategyInstance(string $entityType, User $user): IStrategy
    {
        switch ($entityType) {
            case EntityType\User\Strategy::ENTITY_TYPE:
                return new EntityType\User\Strategy($this->userAccessMapper, $this->staffCanPost);
            case EntityType\Super\Strategy::ENTITY_TYPE:
                return new EntityType\Super\Strategy($this->userAccessMapper, $this->staffCanPost);
            case EntityType\Campus\Strategy::ENTITY_TYPE:
                return new EntityType\Campus\Strategy($this->userAccessMapper, $this->staffCanPost);
            case EntityType\Role\Strategy::ENTITY_TYPE:
                return new EntityType\Role\Strategy($this->userAccessMapper, $this->staffCanPost);
            case EntityType\RoleType\Strategy::ENTITY_TYPE:
                return new EntityType\RoleType\Strategy($this->userAccessMapper, $this->staffCanPost);
            case EntityType\SchoolHouse\Strategy::ENTITY_TYPE:
                return new EntityType\SchoolHouse\Strategy($this->userAccessMapper, $this->staffCanPost);
            case EntityType\ResHouse\Strategy::ENTITY_TYPE:
                return new EntityType\ResHouse\Strategy($this->userAccessMapper, $this->staffCanPost);
            case EntityType\Year\Strategy::ENTITY_TYPE:
                return new EntityType\Year\Strategy($this->userAccessMapper, $this->staffCanPost);
            case EntityType\Service\Strategy::ENTITY_TYPE:
                return new EntityType\Service\Strategy($this->userAccessMapper, $this->staffCanPost);
            case EntityType\PastoralGroup\Strategy::ENTITY_TYPE:
                return new EntityType\PastoralGroup\Strategy($this->userAccessMapper, $this->staffCanPost);
            case EntityType\Folder\Strategy::ENTITY_TYPE:
                return new EntityType\Folder\Strategy(
                    $this->userAccessMapper,
                    $this->staffCanPost,
                    new EntityType\Folder\Search($user, $this->folderMapper, $this->roleAccessMapper)
                );
        }
        throw new InvalidArgumentException("Unrecognised entity type: '{$entityType}'");
    }


    /**
     * @param User $user
     * @return GenericCollection|IStrategy[]
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     * @todo Review usages to determine if they really need the IStrategy interface, or just a subset
     */
    public function makeAllStrategies(User $user): GenericCollection
    {
        $c = new GenericCollection(IStrategy::class);
        foreach (self::ENTITY_VALUES as $entityValue) {
            $c[$entityValue] = $this->makeStrategy($entityValue, $user);
        }
        return $c;
    }
}
