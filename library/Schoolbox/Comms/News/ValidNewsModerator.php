<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News;

use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

class ValidNewsModerator
{
    /** @var Access */
    private $access;

    /**
     * ValidNewsModerator constructor.
     *
     * @param Access $access
     */
    public function __construct(Access $access)
    {
        $this->access = $access;
    }

    /**
     * @param User $user
     *
     * @return bool
     */
    public function isSatisfiedBy(User $user): bool
    {
        return $user->isSuperuser() || ($this->access->getAccessLevel($user, UserAccess::SERVICE_NEWS) > UserAccess::ACCESS_WRITE);
    }
}
