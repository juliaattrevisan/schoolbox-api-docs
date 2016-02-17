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
use \Schoolbox\User;

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
     * Find all folders matching a certain name,
     * associated with userGroups
     *
     * @param $name
     * @return \Schoolbox\Collection
     */
    public function findFolders($name = '') {
        $allFolders = $this->createCollection();
        $folderMapper = new FolderMapper();

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

    /**
     * Find all folders that a user hasn't joined yet
     *
     * @param User $user
     * @return \Schoolbox\Collection
     */
    public function findUnjoinedFolders(User $user) {
        $allFolders = $this->createCollection();
        $folderMapper = new FolderMapper();

        $query = "
            SELECT
                {$this->getColumnString('ug')},
                {$this->getJoinColumnString('folder', 'f')},
                userAccess.*
            FROM `{$this->getTable()}` ug
            LEFT JOIN {$this->getJoinSql('folder', 'ug', 'f')}
            LEFT JOIN `userAccess`
            ON (userAccess.service = " . HOMEPAGE_SERVICE . " AND ug.cid = userAccess.sid AND userAccess.uid = :userId)
            WHERE f.id IS NOT NULL
            AND userAccess.uid IS NULL
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'userId' => $user->getId()
        ]);

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $allFolders->add($folderMapper->hydrate($row, 'f'));
        }

        return $allFolders;
    }
}