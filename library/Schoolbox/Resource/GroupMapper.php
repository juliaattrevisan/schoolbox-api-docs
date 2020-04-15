<?php
/**
 * Used for mappings group types to categories
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Resource;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use \PDO;
use Schoolbox\Permission\Access\UserAccess;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\User;

/**
 * Class GroupMapper
 *
 * @package Schoolbox\Resource
 * @method Group createModel($idValue = null)
 * @method Group hydrate(array $row, $alias = '', $callback = null)
 * @method Group[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 */
class GroupMapper extends Mapper {

    protected function mapping() {
        $this->hasModel(Group::class);
        $this->hasTable('userGroups');
        $this->uses('type', self::TYPE_INTEGER);
        $this->uses('force_follow', self::TYPE_BOOLEAN);
        $this->joins('cid', FolderMapper::class, 'id', null, self::COLUMN_IDENTIFIER, 'folder', 'folder');
    }


    /**
     * @inheritDoc
     */
    protected function getSelectString()
    {
        return "
            SELECT  {$this->getColumnString()},
                    {$this->getJoinColumnString('folder', 'f')}
        ";
    }


    /**
     * @inheritDoc
     */
    protected function getFromString()
    {
        return "
            FROM {$this->table}
            INNER JOIN {$this->getJoinSql('folder', '', 'f')}
        ";
    }


    /**
     * @inheritDoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var FolderMapper $folderMapper */
        $folderMapper = $this->getJoinMapper('folder');
        $groups       = $this->createCollection();
        foreach ($statement as $row) {
            $group = $this->hydrate($row)->setFolder($folderMapper->hydrate($row, 'f'));
            $groups[$group->getFolder()->getId()] = $group;
        }
        return $groups;
    }




    /**
     * Find all folders matching a certain name,
     * associated with userGroups
     *
     * @param $name
     * @return GenericCollection|Folder\Model[]
     */
    public function findFolders($name = '')
    {
        $folderMapper = $this->getJoinMapper('folder');
        $allFolders = $folderMapper->createCollection();

        /* if $name is empty, the LIKE returns everything anyway
         */
        $query = "
            SELECT
                {$this->getColumnString('ug')},
                {$this->getJoinColumnString('folder', 'f')}
            FROM
                `{$this->getTable()}` ug
            LEFT JOIN
                {$this->getJoinSql('folder', 'ug', 'f')}
            WHERE f.id IS NOT NULL
            AND f.name LIKE :name
            ORDER BY f.name
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'name' => "%{$name}%"
        ]);

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $allFolders->add($folderMapper->hydrate($row, 'f'));
        }

        return $allFolders;
    }
}
