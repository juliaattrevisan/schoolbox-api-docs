<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Folder;

use Alaress\Database;
use Alaress\Database\Predicate\Builder;
use Alaress\DataStructures\ObjectTree;
use PDO;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use UnderflowException;
use UnexpectedValueException;
use function array_keys;
use function count;
use function implode;

/**
 * Responsible for building a folder tree
 */
class TreeBuilder
{

    /** @var Folder\Mapper */
    private $folderMapper;


    /**
     * @param Folder\Mapper $folderMapper
     */
    public function __construct(Folder\Mapper $folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }


    /**
     * Build a folder tree from the specified root
     *
     * Folders will only have their own attributes and parent hydrated (no other relationships, eg codes etc)
     *
     * @param int           $rootId
     * @param callable|null $hashFunction
     * @param Builder|null  $extraPb Optional predicate to filter the tree.
     * @return ObjectTree
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Mapper\Exception
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    public function buildTree(int $rootId, callable $hashFunction = null, Builder $extraPb = null): ObjectTree
    {
        $folderTree = new ObjectTree($hashFunction);
        $root       = $this->getRoot($rootId);

        $folderTree->addElement($root);
        $this->addFoldersToTreeRecursive($folderTree, [$rootId => $root], $extraPb);

        return $folderTree;
    }


    /**
     * Loads root folder model
     *
     * @param int $rootId
     * @return Model
     * @throws Mapper\Exception
     */
    private function getRoot(int $rootId): Folder\Model
    {
        if ($rootId === IFolder::ROOT_ID) {
            return $this->folderMapper->findCheaplyById($rootId);
        }
        $sql  = "
            SELECT {$this->folderMapper->getColumnString()}
            FROM folder
            WHERE id = :id AND deleted_at IS NULL;
        ";
        $rows = $this->folderMapper->getDatabase()->query($sql, ['id' => $rootId])->fetchAll();
        if (!count($rows)) {
            throw new Mapper\Exception();
        }
        return $this->folderMapper->hydrate($rows[0]);
    }


    /**
     * Recursively add schoolbox folders to folder tree structure
     *
     * @param ObjectTree     $folderTree
     * @param Folder\Model[] $parents Must be indexed by id
     * @param Builder|null   $extraPb
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws UnexpectedValueException
     * @throws UnderflowException
     * @throws Mapper\Exception
     */
    private function addFoldersToTreeRecursive(ObjectTree $folderTree, array $parents, Builder $extraPb = null): void
    {
        // Base Case:
        if (!count($parents)) {
            return;
        }

        // Recursive case:
        $pb = $this->folderMapper->getPredicateBuilder()
                                 ->in('parent', array_keys($parents))
                                 ->isNull('deleted_at');

        if ($extraPb) {
            $pb->all($extraPb);
        }

        $whereClause = implode(' AND ', $pb->getWhere());

        // We can save about 75% memory/time by bypassing mapper
        $sql = "
            SELECT {$this->folderMapper->getColumnString()}
            FROM {$this->folderMapper->getTable()}
            WHERE {$whereClause}
        ";

        $statement = $this->folderMapper->getDatabase()->query($sql, $pb->getParams());

        /* This has the potential to have very large optimisations.
         * On vagrant, to build 30,000 folders:
         *  Using Mapper::getColumnString() + Mapper::hydrate()
         *      xdebug off: 5.5x, xdebug ON: 55s
         *  Using raw query to select id,parent,name + explicit id/parent hydration
         *      xdebug off: 1.5x, xdebug ON: 8s
         */
        $folders = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $folder = $this->folderMapper->hydrate($row);
            $folder->setParent($parents[$folder->getParent()->getId()]);
            $folderTree->addElement($folder, $folder->getParent());
            $folders[$folder->getId()] = $folder;
        }

        $this->addFoldersToTreeRecursive($folderTree, $folders, $extraPb);
    }
}
