<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Database\Quoter\Mysql\Quoter;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\Segment\UserResolutionSql;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use Schoolbox\Comms\News\Audience\Segment\ISimpleStrategy;

use function array_intersect;
use function count;

/**
 * Common logic and safe-defaults for simple (ie not composite) Audience segment strategies
 */
abstract class SimpleStrategy implements ISimpleStrategy
{

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var Access */
    private $access;

    /** @var bool */
    private $staffCanPost;

    /** @var bool */
    private $moderationRequired;


    /**
     * @param UserAccessMapper $userAccessMapper
     * @param Access           $access
     * @param bool             $staffCanPost
     * @param bool             $moderationRequired
     */
    public function __construct(UserAccessMapper $userAccessMapper, Access $access, bool $staffCanPost, bool $moderationRequired)
    {
        $this->userAccessMapper   = $userAccessMapper;
        $this->access             = $access;
        $this->staffCanPost       = $staffCanPost;
        $this->moderationRequired = $moderationRequired;
    }


    /**
     * @inheritdoc
     */
    public function canAudienceToEntityType(User $user): bool
    {
        // Default to only SU and users with WRITE+ News Service Perm
        return $user->isSuperuser() || $this->hasNewsServicePerm($user, UserAccess::ACCESS_WRITE);
    }


    /**
     * @inheritdoc
     */
    public function canAudienceToEntityValue(User $user, ?string $entityValue): bool
    {
        // Superusers and News Moderators can audience to any entity value
        return
            $user->isSuperuser() ||
            $this->hasNewsServicePerm($user, UserAccess::ACCESS_WRITE) ||
            ($this->staffCanPost && $user->getRole()->isStaff());
    }


    /**
     * Returns whether the user has at least the specified News Service permission
     *
     * @param User $user
     * @param int  $accessLevel
     * @return bool
     */
    protected function hasNewsServicePerm(User $user, int $accessLevel): bool
    {
        return $this->access->getAccessLevel($user, UserAccess::SERVICE_NEWS) >= $accessLevel;
    }


    /**
     * @inheritdoc
     */
    public function getValidRelationshipModifiers(string $entityMeta = null): array
    {
        // Default to being disabled
        return [];
    }


    /**
     * @inheritdoc
     */
    public function canViewArticleAudiencedToEntityValue(User $user, ?string $entityValue, string $modifier): bool
    {
        // TODO default to false for now
        return false;
    }


    /**
     * @@inheritdoc
     */
    public function canModerate(User $user, ?string $entityValue): bool
    {
        // Default implementation
        return
            $user->isSuperuser() ||
            $this->hasNewsServicePerm($user, UserAccess::ACCESS_FULL) ||
            (
                !$this->moderationRequired &&
                (
                    ($user->getRole()->isStaff() && $this->staffCanPost) ||
                    $this->hasNewsServicePerm($user, UserAccess::ACCESS_WRITE)
                )
            );
    }


    /**
     * @inheritDoc
     */
    public function isUserInAudienceValues(User $user, array $entityValues, string $relationship): ?bool
    {
        // Default implementation.
        // In most cases, the user and their children (if relevant), will have sufficient hydration.
        switch ($relationship) {
            case Audience\Model::RELATIONSHIP_SELF:
                $users = User::createCollection()->add($user);
                return count(array_intersect($this->getValues($users), $entityValues)) > 0;
            case Audience\Model::RELATIONSHIP_PARENTS_OF:
                if (!$user->getRole()->isParent()) {
                    return false;
                }
                if ($user->getChildren() === null) {
                    return null;
                }
                return count(array_intersect($this->getValues($user->getChildren()), $entityValues)) > 0;
            case Audience\Model::RELATIONSHIP_TEACHERS_OF:
                if (!$user->getRole()->isStaff()) {
                    return false;
                }
                return null;
        }
        return null;
    }


    /**
     * @return bool
     */
    protected function getStaffCanPost(): bool
    {
        return $this->staffCanPost;
    }


    /**
     * @return UserAccessMapper
     */
    protected function getUserAccessMapper(): UserAccessMapper
    {
        return $this->userAccessMapper;
    }


    /**
     * @return Access
     */
    protected function getAccess(): Access
    {
        return $this->access;
    }


    /**
     * Return a new Predicate Builder
     *
     * @param string $paramPrefix
     * @param string $table
     * @return Builder
     */
    protected function newPb(string $paramPrefix, string $table = ''): Builder
    {
        // Prefix cannot start with digit
        return (new Builder(new Quoter(), $table))->setParamPrefix($paramPrefix);
    }


    /**
     * Return a UserResolution that will cause the query optimiser to detect an impossible WHERE clause
     *
     * In reality this should be called very infrequently, and exists just to maintain the interface.
     * It can occur when policy changes. Eg if a segment targets "campus:NULL", and we then disable targeting
     * of users without a campus, this will be used to ensure no users are matched.
     *
     * @param string $userTableAlias
     * @param string $uniquePrefix
     * @return UserResolutionSql
     * @throws InvalidColumnException
     */
    protected function buildImpossibleUserResolution(string $userTableAlias, string $uniquePrefix): UserResolutionSql
    {
        return new UserResolutionSql(
            '',
            $this->newPb($uniquePrefix)->all(
                $this->newPb($uniquePrefix)
                     ->equals("{$userTableAlias}.id", 1)
                     ->equals("{$userTableAlias}.id", 2)
            )
        );
    }


    /**
     * Convenience method to filter out any null values from an array
     *
     * @param null[]|string[] $entityValues
     * @return string[]
     */
    protected function filterOutNullValues(array $entityValues): array
    {
        foreach ($entityValues as $idx => $value) {
            if ($value === null) {
                unset($entityValues[$idx]);
            }
        }
        return $entityValues;
    }
}
