<?php
/**
 * ValidUserCanAccept
 *
 * Definition of an article that can be accepted during moderation process
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Moderation;

use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\ValidUserIsCommsOfficer;
use Schoolbox\User;

class ValidUserCanAccept
{

    /**
     * Definition of an article that can be accepted during moderation process
     *
     * @param User  $user
     * @param bool  $moderationRequired
     * @param Model $news
     * @return bool
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function isSatisfiedBy(User $user, bool $moderationRequired, Model $news): bool
    {

        /* Must fulfil the following criteria
         *   User must be a Moderator, and
         *   Moderation must be required, and
         *   Article is submitted, and
         *   Article is not yet moderated
         */
        $isCommsOfficer = (new ValidUserIsCommsOfficer())->isSatisfiedBy($user);

        return $isCommsOfficer && $moderationRequired && $news->isSubmitted() && !$news->isModerated();
    }
}
