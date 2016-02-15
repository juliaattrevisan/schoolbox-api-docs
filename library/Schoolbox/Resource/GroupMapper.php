<?php
/**
 * Used for mappings group types to categories
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Resource;
use Schoolbox\Mapper;

use \PDO;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;

class GroupMapper extends Mapper {

    protected function mapping() {
        $this->hasModel('\\Schoolbox\\Resource\\Group');
        $this->hasTable('userGroups');
        $this->hasMap(array(
                'column'     => 'cid',
                'property'   => 'category',
                'type'       => self::TYPE_INTEGER,
            ));
        $this->hasMap(array(
                'column'     => 'type',
                'property'   => 'type',
                'type'       => self::TYPE_INTEGER,
            ));
        $this->hasMap(array(
            'column'   => 'force_follow',
            'property' => 'forceFollow',
            'type'     => self::TYPE_BOOLEAN
        ));

        $this->hasJoin(
            'folder',
            array(
                'mapper'    => '\\Schoolbox\\Resource\\Folder\\Mapper',
                'this_key'  => 'cid',
                'other_key' => 'id',
            )
        );
    }

    /**
     * Find all folders associated with userGroups
     *
     * @return \Schoolbox\Collection
     */
    public function findFolders() {
        $allFolders = $this->createCollection();
        $folderMapper = new FolderMapper();

        $query = "
            SELECT
                {$this->getColumnString('ug')},
                {$this->getJoinColumnString('folder', 'f')}
            FROM
                `{$this->getTable()}` ug
            LEFT JOIN
                {$this->getJoinSql('folder', 'ug', 'f')}
            WHERE f.id IS NOT NULL
        ";

        $statement = $this->database->prepare($query);
        $statement->execute();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $allFolders->add($folderMapper->hydrate($row, 'f'));
        }

        return $allFolders;
    }
}