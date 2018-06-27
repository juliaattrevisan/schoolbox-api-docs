<?php
/**
 * ValidUserCanReject
 *
 * Definition of a $user who can reject $news
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Moderation;

use Schoolbox\User;
use Schoolbox\Comms\News\ValidUserIsCommsOfficer;

class ValidUserCanReject
{

    /**
     * Definition of a $user who can reject $news
     *
     * @param User  $user
     * @return bool
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function isSatisfiedBy(User $user): bool
    {
        /* Comms Officers can reject anything at any time
         */
        return (new ValidUserIsCommsOfficer())->isSatisfiedBy($user);
    }
}
