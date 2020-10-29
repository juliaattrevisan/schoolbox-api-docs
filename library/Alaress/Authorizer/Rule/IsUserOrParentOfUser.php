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
     * @param string|callable        $paramSource
     */
    public function __construct(
        External_UserInterface $externalDb,
        UserMapper $userMapper,
        $paramSource = 'id'
    ) {
        parent::__construct(
            new IsUser($paramSource),
            new IsParentOfUser($externalDb, $userMapper, $paramSource)
        );
    }
}
