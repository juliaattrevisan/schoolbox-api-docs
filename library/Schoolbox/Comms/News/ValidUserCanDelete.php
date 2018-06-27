<?php
/**
 * ValidUserCanDelete
 *
 * Definition of a $user who can delete $news
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Schoolbox\User;

class ValidUserCanDelete
{

    /**
     * Definition of a $user who can delete $news
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
        /* Comms Officers can destroy anything at any time
         */
        if ((new ValidUserIsCommsOfficer())->isSatisfiedBy($user)) {
            return true;
        }

        /* Articles that haven't been accepted can be safely deleted
         */
        if ($news->getAcceptedAt()->isNull()) {
            return true;
        }

        /* Cannot delete articles at any other time
         */
        return false;
    }
}
