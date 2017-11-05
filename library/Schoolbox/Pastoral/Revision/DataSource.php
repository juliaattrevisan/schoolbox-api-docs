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
 * All access to pastoral revisions should be through this object, not the
 * mapper. This is for cleanliness reasons.
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
     * User-sensitive wrapper for findForRecord
     *
     * @param User     $user
     * @param Pastoral $record
     * @return Collection|Model[]
     * @throws MapperException
     */
    public function findForRecord(User $user, Pastoral $record): Collection
    {
        return $this->mapper->filterRecordsForUser($this->mapper->findForRecord($record), $user);
    }
}
