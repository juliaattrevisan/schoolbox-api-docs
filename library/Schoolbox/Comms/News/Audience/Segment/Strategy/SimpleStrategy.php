<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment\Strategy;

use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use Schoolbox\Comms\News\Audience\Segment\ISimpleStrategy;

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
        return $this->access->getAccessLevel($user, null, UserAccess::SERVICE_NEWS) >= $accessLevel;
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
}
