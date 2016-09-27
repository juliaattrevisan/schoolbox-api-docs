<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper as BaseMapper;

/**
 * Proxy mapper for IAssessable objects.
 *
 * It simulates some parts of a Mapper's interface, and acts as a proxy for mappers.
 */
class MapperProxy
{
    /**
     * Finds an assessable by ID
     *
     * @param int $id
     */
    public function findById($id)
    {
        //
    }

    /**
     * Finds all assessables for a unit specification
     *
     * @param int $unitSpecId
     */
    public function findAllForUnitSpecificationId($unitSpecId)
    {
        //
    }

    /**
     * Finds all assessables for a unit implementation
     *
     * @param int $unitImplId
     */
    public function findAllForUnitImplementationId($unitImplId)
    {
        //
    }

    /**
     * Finds all assessables for a folder
     *
     * @param int $folderId
     */
    public function findAllForFolderId($folderId)
    {
        //
    }

    /**
     * Finds all assessables that are part of the given project
     *
     * @param int $projectId
     */
    public function findAllForProjectId($projectId)
    {
        //
    }

    /**
     * Saves an assessable
     *
     * @param IAssessable $assessable
     */
    public function save(IAssessable $assessable)
    {
        //
    }

    /**
     * Deletes an assessable
     *
     * @param IAssessable $assessable
     */
    public function delete(IAssessable $assessable)
    {
        //
    }
}
