<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News;

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;

class ValidNewsModerator
{
    /** @var UserAccessMapper */
    private $userAccessMapper;

    /**
     * ValidNewsModerator constructor.
     *
     * @param UserAccessMapper $userAccessMapper
     */
    public function __construct(UserAccessMapper $userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
    }

    /**
     * @param User $user
     *
     * @return bool
     */
    public function isSatisfiedBy(User $user): bool
    {
        return $user->isSuperuser() || ($this->userAccessMapper->getAccessLevel($user, null, UserAccess::SERVICE_NEWS) > UserAccess::ACCESS_WRITE);
    }
}
