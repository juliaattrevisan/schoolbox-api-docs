<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment;

use InvalidArgumentException;
use Schoolbox\User;
use Schoolbox\Resource\Folder;
use Schoolbox\Permission\Access\RoleAccessMapper;

/**
 * Responsible for enumeration of the IStrategy interface
 * @see IStrategy
 */
class StrategyFactory
{

    /** @var User */
    private $user;

    /** @var Folder\Mapper */
    private $folderMapper;

    /** @var RoleAccessMapper */
    private $roleAccessMapper;

    /** @var bool */
    private $moderationEnabled;


    /**
     * @param User             $user
     * @param Folder\Mapper    $folderMapper
     * @param RoleAccessMapper $roleAccessMapper
     * @param bool             $moderationEnabled
     */
    public function __construct(User $user, Folder\Mapper $folderMapper, RoleAccessMapper $roleAccessMapper, bool $moderationEnabled)
    {
        $this->user              = $user;
        $this->folderMapper      = $folderMapper;
        $this->roleAccessMapper  = $roleAccessMapper;
        $this->moderationEnabled = $moderationEnabled;
    }


    /**
     * @param string $entityType
     * @return IStrategy
     * @throws InvalidArgumentException
     */
    public function make(string $entityType): IStrategy
    {
        switch ($entityType) {
            case Strategy\User::ENTITY_TYPE:
                return new Strategy\User();
            case Strategy\Super::ENTITY_TYPE:
                return null;
            case Strategy\Campus::ENTITY_TYPE:
                return new Strategy\Campus();
            case Strategy\Role::ENTITY_TYPE:
                return new Strategy\Role();
            case Strategy\RoleType::ENTITY_TYPE:
                return null;
            case Strategy\SchoolHouse::ENTITY_TYPE:
                return new Strategy\SchoolHouse();
            case Strategy\ResHouse::ENTITY_TYPE:
                return null;
            case Strategy\Year::ENTITY_TYPE:
                return new Strategy\Year();
            case Strategy\Service::ENTITY_TYPE:
                return null;
            case Strategy\PastoralGroup::ENTITY_TYPE:
                return new Strategy\PastoralGroup();
            case Strategy\Folder::ENTITY_TYPE:
                return new Strategy\Folder($this->user, $this->folderMapper, $this->roleAccessMapper, $this->moderationEnabled);
        }
        throw new InvalidArgumentException("Unrecognised entity type: '{$entityType}'");
    }
}
