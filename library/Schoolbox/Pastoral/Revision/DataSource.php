<?php
/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Revision;

use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
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
     * @var Mapper
     */
    private $mapper;

    /**
     * Constructor
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * User-sensitive wrapper for `Mapper::findForRecord`.
     *
     * @param User     $currentUser
     * @param Pastoral $record
     * @return Collection|Model[]
     * @throws MapperException
     */
    public function findForRecord(User $currentUser, Pastoral $record): Collection
    {
        return $this->mapper->filterRecordsForUser($this->mapper->findForRecord($record), $currentUser);
    }
}
