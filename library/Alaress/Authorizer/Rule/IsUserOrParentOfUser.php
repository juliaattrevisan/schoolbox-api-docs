<?php
/**
 * Validates whether the user is either the given user, or their parent.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
     * @param string                 $paramsKey
     */
    public function __construct(
        External_UserInterface $externalDb,
        UserMapper $userMapper,
        $paramsKey = 'id'
    ) {
        $this
            ->addRule(new IsUser($paramsKey))
            ->addRule(new IsParentOfUser($externalDb, $userMapper, $paramsKey))
        ;
    }
}
