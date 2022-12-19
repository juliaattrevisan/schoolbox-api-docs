<?php

/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Revision;

use Schoolbox\GenericCollection;
use Schoolbox\Pastoral\AccessLevelCensor;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\User;

/**
 * Data source for pastoral care revisions.
 *
 * Web request access to pastoral care revisions must be through this object, not
 * the mapper. This is because pastoral information is sensitive in nature, and
 * therefore it MUST be appropriately filtered and censored before it can be
 * viewed by a user.
 */
class DataSource
{
    /**
     * Constructor
     *
     * @param Mapper            $mapper
     * @param AccessLevelCensor $accessLevelCensor
     */
    public function __construct(private Mapper $mapper, private AccessLevelCensor $accessLevelCensor)
    {
    }

    /**
     * User-sensitive wrapper for `Mapper::findForRecord`.
     *
     * @param User     $currentUser
     * @param Pastoral $record
     * @return GenericCollection<Model>
     */
    public function findForRecord(User $currentUser, Pastoral $record): GenericCollection
    {
        return $this->accessLevelCensor->filterRecordsForUser($this->mapper->findForRecord($record), $currentUser);
    }
}
