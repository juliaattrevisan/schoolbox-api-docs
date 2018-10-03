<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\EntityType;

use Schoolbox\Comms\News\Audience;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use Schoolbox\Comms\News\Audience\Segment\IStrategy;
use UnexpectedValueException;

/**
 * Common logic and safe-defaults for Audience segment strategies
 */
abstract class StrategyBase implements IStrategy
{

    public const ENTITY_TYPE = '';  // concrete classes must override

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var bool */
    private $staffCanPost;


    /**
     * @param UserAccessMapper $userAccessMapper
     * @param bool             $staffCanPost
     */
    public function __construct(UserAccessMapper $userAccessMapper, bool $staffCanPost)
    {
        $this->userAccessMapper = $userAccessMapper;
        $this->staffCanPost     = $staffCanPost;
    }


    /**
     * @return string
     * @throws UnexpectedValueException
     */
    public function getEntityType(): string
    {
        if (!static::ENTITY_TYPE) {
            throw new UnexpectedValueException('Concrete classes must implement.');
        }
        return static::ENTITY_TYPE;
    }


    /**
     * @inheritdoc
     * @throws \Schoolbox\Mapper\Exception
     */
    public function canBeTargetedByUser(User $user): bool
    {
        // Default to only SU and users with WRITE+ News Service Perm
        return $user->isSuperuser() || $this->hasNewsServiceWritePerm($user);
    }


    /**
     * Returns whether the user has WRITE+ News Service permission
     *
     * @param User $user
     * @return bool
     * @throws \Schoolbox\Mapper\Exception
     */
    private function hasNewsServiceWritePerm(User $user): bool
    {
        return $this->userAccessMapper->getAccess($user, null, UserAccess::SERVICE_NEWS) >= UserAccess::ACCESS_WRITE;
    }


    /**
     * @inheritdoc
     */
    public function canTargetNullEntityValue(): bool
    {
        // Default implementation. Assume 1:1 with users, eg not possible for a user to not have a Role.
        return false;
    }


    /**
     * @inheritdoc
     */
    public function getValidRelationshipModifiers(User $user): array
    {
        // Safe default it to not permit relationship modification
        return [Audience\Model::RELATIONSHIP_SELF];
    }


    /**
     * @return bool
     */
    protected function getStaffCanPost(): bool
    {
        return $this->staffCanPost;
    }


    /**
     * @inheritdoc
     */
    public function canViewEntityValue(User $user, ?string $entityValue): bool
    {
        // TODO default to false for now
        return false;
    }
}
