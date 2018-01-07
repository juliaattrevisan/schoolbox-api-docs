<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder\Log;

use Schoolbox;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Mapper extends Schoolbox\Mapper
{
    protected function mapping()
    {
        $this->hasModel(Model::class);
        $this->hasTable('homepage_log');

        $this->uses('datetime', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP, 'lastAccessTime');
        $this->joins('user', UserMapper::class, 'id', self::TYPE_MODEL, null, 'user', 'user');
        $this->joins('cid', FolderMapper::class, 'id', self::TYPE_MODEL, null, 'category', 'category');
        $this->uses('hitcount', self::TYPE_INTEGER);
    }

    /**
     * Override of getUpdateString() to only ever increment the hit count, and
     * to update the last visit time to the current time.
     *
     * @return String
     */
    public function getUpdateString()
    {
        return '`hitcount` = `hitcount` + 1, `datetime` = NOW()';
    }

    /**
     * Increments hit count for this user and category.
     *
     * @param int|User         $user     A User object or user ID
     * @param int|Folder\Model $category A Category object or ID
     */
    public function incrementHitcount($user, $category)
    {
        $item = $this->createModel();
        $item->setUser($user)->setCategory($category)->setHitcount(1);
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
     * @param Folder\Model $category
     * @param User $user
     * @return Schoolbox\Collection|Model[]
     */
    public function lastVisit(Folder\Model $category, User $user)
    {
        $query = "
            SELECT
            	{$this->getColumnString('hl')},
            	{$this->getJoinMapper('category')->getColumnString('c')}
            FROM `{$this->table}` hl
            JOIN {$this->getJoinSql('category', 'hl', 'c')}
            WHERE hl.cid = :categoryId
            AND hl.user = :userId
		";

        $statement = $this->database->prepare($query);
        $statement->execute(array(
            'categoryId' => $category->getId(),
            'userId' => $user->getId()
        ));

        /* Manually construct hierarchy of items
         */
        $collection = $this->createCollection();

        $categoryMapper = $this->getJoinMapper('category');

        foreach ($statement as $row) {
            $log = $this->hydrate($row, 'hl');

            $log->setCategory($categoryMapper->hydrate($row, 'c'));

            $collection->add($log);
        }

        return $collection;
    }
} 
