<?php
/**
 * ValidUserCanEdit
 *
 * Definition of a $user who can edit $article
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Schoolbox\User;

class ValidUserCanEdit
{

    /**
     * Definition of a $user who can edit $article
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

        /* User is a comms officer; can edit at any time
         */
        if ((new ValidUserIsCommsOfficer())->isSatisfiedBy($user)) {
            return true;
        }

        /* Otherwise, you must fulfil all of these requirements:
         *   You are considered an author of this article
         *   Article has not been accepted for publication
         */
        return (new ValidUserIsAuthor())->isSatisfiedBy($user, $article) && $article->getAcceptedAt()->isNull();
    }
}
