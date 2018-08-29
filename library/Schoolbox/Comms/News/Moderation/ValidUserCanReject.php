<?php
/**
 * ValidUserCanReject
 *
 * Definition of a $user who can reject $article
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

        /* Comms Officers can reject anyone's articles, even their own
         *
         * Can only reject articles that were submitted for moderation
         * thus preventing you from rejecting drafts
         */
        return $isCommsOfficer && $article->isSubmitted();
    }
}
