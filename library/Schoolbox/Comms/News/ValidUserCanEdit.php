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
     * @param Model $news
     * @return bool
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function isSatisfiedBy(User $user, Model $news): bool
    {
        return $news->getModeratedAt()->isNull() || (new ValidUserIsCommsOfficer())->isSatisfiedBy($user);
    }
}
