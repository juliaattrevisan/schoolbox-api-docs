<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use InvalidArgumentException;
use UnexpectedValueException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use Schoolbox\Mapper;
use Alaress\Database\Predicate;

class IsCommsOfficer implements IAuthorizationRule
{

    /** @var UserAccessMapper */
    private $userAccessMapper;


    /**
     * @param UserAccessMapper $userAccessMapper
     */
    public function __construct(UserAccessMapper $userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
    }


    /**
     * @param array     $params
     * @param User|null $user
     * @throws InvalidArgumentException
     * @throws Unauthorized
     */
    public function authorize(array $params = [], User $user = null): void
    {
        if (!$user) {
            throw new InvalidArgumentException("No session user available to test permissions");
        }

        $commsOfficerPerms = $this->userAccessMapper->findServiceForUser($user, UserAccess::SERVICE_COMMS_OFFICER);

        if (!$commsOfficerPerms->count()) {
            throw new Unauthorized("User ({$user->getId()}) is not a comms officer");
        }
    }
}
