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
     * @param Model $article
     * @return bool
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function isSatisfiedBy(User $user, Model $article): bool
    {

        /* Must fulfil the following criteria
         *   User must be a Moderator, and
         *   Moderation must be required, and
         *   Article is submitted, and
         *   Article is not yet accepted
         */
        $isCommsOfficer = (new ValidUserIsCommsOfficer())->isSatisfiedBy($user);

        /* Allow Acceptance of articles even when moderation isn't required
         * Moderation can be disabled after an article is submitted, thus ending up in a weird limbo
         * when moderation was previously required at the time of submission, but is no longer required now
         *
         * Allow moderators to accept the remainder of articles that previously expected moderation acceptance
         */
        return $isCommsOfficer && $article->isSubmitted() && !$article->isApproved();
    }
}
