<?php
/**
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use InvalidArgumentException;
use Alaress\Authorizer\Rule;


/**
 * This class is used to authorize users to access features like the markbook.  It checks that the user is not a learner
 * first, then checks if they are staff or have WRITE access to the folder.
 * @package Schoolbox\Learning\Authorizer\Rule
 */
class isInstructor extends AbstractParticipationRule
{

    /**
     * @var Access
     */
    private $access;


    /**
     * @param Access $access
     */
    public function __construct(Access $access)
    {
        $this->access = $access;
    }


    /**
     * @param array     $params
     * @param User|null $user
     * @return bool
     * @throws Failure\Unauthorized If user
     * @throws InvalidArgumentException If unable to locate an User instance in route params
     */
    public function authorize(array $params = [], User $user = null)
    {

        $folder     = $params['folder'];

        if (!$user) {
            throw new InvalidArgumentException('No user supplied to authorize');
        }

        $hasReadOnly = (new Rule\HasServicePermission(UserAccess::SERVICE_HOMEPAGE, UserAccess::ACCESS_READ))->authorize($params, $user);

        if($hasReadOnly) {
            return false;
        }


        if ($user->getRole()->isStaff() || $this->access->isInstructor($user, $folder)) {
            return true;
        }


        throw new Failure\Unauthorized();
    }
}
