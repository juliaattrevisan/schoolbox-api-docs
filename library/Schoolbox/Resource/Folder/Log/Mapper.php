<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Folder\Log;

use Schoolbox;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

/**
 * @method Model createModel($idValue = null)
 */
class Mapper extends Schoolbox\Mapper
{
    protected function mapping()
    {
        $this->hasModel(Model::class);
        $this->hasTable('homepage_log');

        $this->uses('datetime', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP, 'lastAccessTime');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('folder_id', FolderMapper::class, 'id', null, null, 'folder', 'folder');
        $this->uses('hitcount', self::TYPE_INTEGER);
    }

    /**
     * Override of getUpdateString() to only ever increment the hit count, and
     * to update the last visit time to the current time.
     *
     * @return String
     */
    public function getUpdateString(): string
    {
        return '`hitcount` = `hitcount` + 1, `datetime` = NOW()';
    }

    /**
     * Increments hit count for this user and category.
     *
     * @param int|User         $user A User object or user ID
     * @param Folder\Model $folder A Category object or ID
     * @throws Schoolbox\Mapper\Exception
     */
    public function incrementHitcount($user, $folder)
    {
        $item = $this->createModel();
        $item->setUser($user)->setFolder($folder)->setHitcount(1);
        $this->save($item);
    }

    /**
     * Override default behaviour: this mapper does not raise any notifications.
     *
     * @param object $object
     * @param string $verb
     * @param null   $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return false;
    }

    /**
     * Get the last time a person visited this place
     *
     * @param Folder\Model $folder
     * @param User         $user
     * @return Schoolbox\Collection|Model[]
     * @throws Schoolbox\Mapper\Exception
     */
    public function lastVisit(Folder\Model $folder, User $user)
    {
        $query = "
            SELECT
            	{$this->getColumnString('hl')},
              {$this->getJoinMapper('folder')->getColumnString('f')}
            FROM `{$this->table}` hl
            JOIN {$this->getJoinSql('folder', 'hl', 'f')}
            WHERE hl.folder_id = :folderId
            AND hl.user_id = :userId
		";

        $statement = $this->database->prepare($query);
        $statement->execute(array(
                                'folderId' => $folder->getId(),
                                'userId'   => $user->getId(),
                            ));

        /* Manually construct hierarchy of items
         */
        $collection = $this->createCollection();

        $folderMapper = $this->getJoinMapper('folder');

        foreach ($statement as $row) {
            $log = $this->hydrate($row, 'hl');
            $log->setFolder($folderMapper->hydrate($row, 'f'));
            $collection->add($log);
        }

        return $collection;
    }
}
