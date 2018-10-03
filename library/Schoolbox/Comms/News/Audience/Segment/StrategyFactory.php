<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment;

use InvalidArgumentException;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Permission\Access\RoleAccessMapper;
use UnexpectedValueException;

/**
 * Responsible for enumeration of the IStrategy interface
 * @see IStrategy
 */
class StrategyFactory
{

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var RoleAccessMapper */
    private $roleAccessMapper;

    /** @var bool */
    private $moderationEnabled;

    private const ENTITY_VALUES = [
        Strategy\Campus::ENTITY_TYPE,
        Strategy\Folder::ENTITY_TYPE,
        Strategy\PastoralGroup::ENTITY_TYPE,
        Strategy\ResHouse::ENTITY_TYPE,
        Strategy\Role::ENTITY_TYPE,
        Strategy\RoleType::ENTITY_TYPE,
        Strategy\SchoolHouse::ENTITY_TYPE,
        Strategy\Service::ENTITY_TYPE,
        Strategy\Super::ENTITY_TYPE,
        Strategy\User::ENTITY_TYPE,
        Strategy\Year::ENTITY_TYPE,
    ];

    /**
     * Cache the strategies so repeated calls to make() for the same strat will return the same instance
     * @var IStrategy[]
     */
    private $strategyCache = [];


    /**
     * @param Folder\Mapper    $folderMapper
     * @param RoleAccessMapper $roleAccessMapper
     * @param bool             $moderationEnabled
     */
    public function __construct(Folder\Mapper $folderMapper, RoleAccessMapper $roleAccessMapper, bool $moderationEnabled)
    {
        $this->folderMapper      = $folderMapper;
        $this->roleAccessMapper  = $roleAccessMapper;
        $this->moderationEnabled = $moderationEnabled;
    }


    /**
     * @param string $entityType
     * @param User   $user
     * @return IStrategy
     * @throws InvalidArgumentException
     */
    public function make(string $entityType, User $user): IStrategy
    {
        if(!\array_key_exists($entityType, $this->strategyCache)) {
            $this->strategyCache[$entityType] = $this->makeNewInstance($entityType, $user);
        }
        return $this->strategyCache[$entityType];
    }


    /**
     * @param string $entityType
     * @param User   $user
     * @return IStrategy
     * @throws InvalidArgumentException
     */
    private function makeNewInstance(string $entityType, User $user): IStrategy
    {
        switch ($entityType) {
            case Strategy\User::ENTITY_TYPE:
                return new Strategy\User();
            case Strategy\Super::ENTITY_TYPE:
                return new Strategy\Super();
            case Strategy\Campus::ENTITY_TYPE:
                return new Strategy\Campus();
            case Strategy\Role::ENTITY_TYPE:
                return new Strategy\Role();
            case Strategy\RoleType::ENTITY_TYPE:
                return new Strategy\RoleType();
            case Strategy\SchoolHouse::ENTITY_TYPE:
                return new Strategy\SchoolHouse();
            case Strategy\ResHouse::ENTITY_TYPE:
                return new Strategy\ResHouse();
            case Strategy\Year::ENTITY_TYPE:
                return new Strategy\Year();
            case Strategy\Service::ENTITY_TYPE:
                return new Strategy\Service();
            case Strategy\PastoralGroup::ENTITY_TYPE:
                return new Strategy\PastoralGroup();
            case Strategy\Folder::ENTITY_TYPE:
                return new Strategy\Folder($user, $this->folderMapper, $this->roleAccessMapper, $this->moderationEnabled);
        }
        throw new InvalidArgumentException("Unrecognised entity type: '{$entityType}'");
    }


    /**
     * @param User $user
     * @return GenericCollection|IStrategy[]
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function makeAll(User $user): GenericCollection
    {
        $c = new GenericCollection(IStrategy::class);
        foreach(self::ENTITY_VALUES as $entityValue){
            $c[$entityValue] = $this->make($entityValue, $user);
        }
        return $c;
    }
}
