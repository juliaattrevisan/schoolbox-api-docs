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
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\ValidUserIsCommsOfficer;

class ValidUserCanReject
{

    /**
     * Definition of a $user who can reject $news
     *
     * @param User  $user
     * @param Model $news
     * @return bool
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function isSatisfiedBy(User $user, Model $news): bool
    {
        $isCommsOfficer = (new ValidUserIsCommsOfficer())->isSatisfiedBy($user);

        /* Comms Officers don't need to reject their own articles
         * Unless there's some overriding desire to be self-analytical
         * (maybe that's a thing, idk)
         */
        if ($isCommsOfficer) {
            return !($news->getAuthor()->getId() === $user->getId());
        }

        /* Anyone else cannot reject articles at any other time
         * Only Comms Officers can reject articles
         */
        return false;
    }
}
