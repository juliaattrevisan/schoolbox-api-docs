<?php

/**
 * Destinations
 *
 * Find a list of folders matching a search string, into which a course can be imported
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Import;

use Schoolbox\Collection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Model;

class Destinations
{
    /**
     * @var Folder\Mapper
     */
    private $folderMapper;


    /**
     * Destinations constructor.
     * @param Folder\Mapper $folderMapper
     */
    public function __construct(Folder\Mapper $folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }


    /**
     * Find destinations into which a course can be imported
     *
     * @param string $keyword
     * @return Collection|Model[]
     * @throws DefinitionException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     */
    public function findImportable($keyword)
    {
        return $this->folderMapper->searchByName($keyword, false, $this->findGroupsAndClassesScalar());
    }

    /**
     * A uniquified list of folders which are either a group or have a class code, or both
     * Returns a scalar list of ids for these folders so you can use it with other predicates
     *
     * Sorry about the name
     *
     * @return array
     */
    private function findGroupsAndClassesScalar(): array
    {
        /* This actually turns out to be more performant than the COALESCE() version
         * The folder ids found by this query are the valid ids to which a course can be imported
         *
            SELECT f.id, folder_id, cid, COALESCE(folder_id, cid)
            FROM folder f
            LEFT JOIN folder_code fc ON f.id = fc.folder_id
            LEFT JOIN userGroups ug ON f.id = ug.cid
            WHERE f.deleted_at IS NULL
            AND fc.deleted_at IS NULL
            AND (folder_id IS NOT NULL OR cid IS NOT NULL)
        */
        $query = "
            (SELECT f.id
            FROM {$this->folderMapper->getTable()} f
            INNER JOIN folder_code fc ON f.id = fc.folder_id
            AND fc.deleted_at IS NULL)
            UNION
            (SELECT f.id
            FROM {$this->folderMapper->getTable()} f
            INNER JOIN userGroups ug ON f.id = ug.cid)
         ";

        $stmt = $this->folderMapper->getDatabase()->query($query);

        $ids = [];

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $ids[$r['id']] = $r['id'];
        }

        return array_values($ids);
    }
}
