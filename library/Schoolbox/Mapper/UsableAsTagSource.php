<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper;

use Schoolbox\Collection;
use Schoolbox\User;

/**
 * Interface for Mapper objects which may be used as the source for a tag cloud.
 *
 * In practise, this is a stripped-down version of Searchable.
 *
 * Mappers which implement this interface should use a model class which
 * implements JsonSerializable, so that a collection from this source can be
 * sent as a JSON string.
 *
 * @package Schoolbox\Mapper
 */
interface UsableAsTagSource
{
    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * @param User        $user    The user performing the search.
     *                             (Some results aren't viewable by all users)
     * @param string      $keyword Search string
     * @param int         $offset  Number of results to offset result by
     * @param int         $limit   Max number of results to retrieve, or 0 if no limit
     * @return Collection|object[] A collection of objects.
     */
    public function findForTag(User $user, $keyword, $offset = 0, $limit = 0);

    // Below methods removed from interface for now, as I no longer believe
    // they are necessary.

//    /**
//     * Retrieve name of tag given the ID number, or null if it doesn't exist.
//     *
//     * @param mixed $id
//     * @return null|string
//     */
//    public function findNameById($id);
//
//    /**
//     * Retrieve tag for a name, optionally creating a new tag if it does not already exist.
//     *
//     * @param string $name
//     * @param bool $create
//     * @return null|int
//     */
//    public function findIdByName($name, $create = false);
}
