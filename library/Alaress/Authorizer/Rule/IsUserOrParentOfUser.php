<?php
/**
 * Validates whether the user is either the given user, or their parent.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use External_UserInterface;
use Schoolbox\UserMapper;

class IsUserOrParentOfUser extends Any
{
    /**
     * Constructor (obviously)
     *
     * @param External_UserInterface $externalDb
     * @param UserMapper             $userMapper
     * @param string|callable        $userIdSource
     */
    public function __construct(
        External_UserInterface $externalDb,
        UserMapper $userMapper,
        $userIdSource = 'id'
    ) {
        parent::__construct(
            new IsUser($userIdSource),
            new IsParentOfUser($externalDb, $userMapper, $userIdSource)
        );
    }
}
