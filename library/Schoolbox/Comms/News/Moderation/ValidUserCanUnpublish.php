<?php
/**
 * ValidUserCanUnpublish
 *
 * Definition of a $user who can unpublish $article
 *
 * This is actually very similar to ValidUserCanReject, but is used to determine
 * some front-end phrasing
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Moderation;

use Schoolbox\User;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\ValidUserIsCommsOfficer;

class ValidUserCanUnpublish
{

    /**
     * Definition of a $user who can reject $news
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
        $isCommsOfficer = (new ValidUserIsCommsOfficer())->isSatisfiedBy($user);

        /* Comms Officers can unpublish anyone's articles, even their own
         */
        return $isCommsOfficer && ($article->isPublished() || $article->isExpired());
    }
}
