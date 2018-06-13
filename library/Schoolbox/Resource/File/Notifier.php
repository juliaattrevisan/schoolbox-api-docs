<?php
/**
 * @author     Amri Sannang <amri.sannang@alaress.com.au>
 * @copiedFrom Matt Day <matt.day@alaress.com.au>
 * @copyright  Copyright 2015, Alaress Pty Ltd
 * @link       http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\File;

use Alaress\Async\Notification\Parameter\Param;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\LogMapper;
use Schoolbox\Resource\ResourceNotifier;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\User;

class Notifier extends ResourceNotifier
{

    /**
     * @inheritdoc
     */
    public function getFeed($context, User $user, $limit = self::HARD_LIMIT, $offset = 0)
    {
        return $this->getFeedInternal($context, $user->getRole()->getType(), $limit, $offset, LogMapper::KIND_FILE);
    }


    /**
     * @param string $roletype
     * @param Model  $file
     * @param User   $effector
     * @param array  $diff
     * @return Envelope
     */
    public function notifyCreated($roletype, $file, $effector, $diff = null)
    {
        try {
            $category = $this->folderMapper->findById($file->getFolder()->getId());
        } catch (Deleted $e) {
            return new Envelope();
        }

        return $this->notify(
            LogMapper::KIND_FILE,
            $file->getId(),
            self::TRIGGER_CREATED, // "file.{id}.created"
            new Param($file->getTitle(), '/send.php?id=' . $file->getId()), // the :resource param
            $effector,
            $category,
            $roletype,
            $this->getAccessLevel($file)
        );
    }


    /**
     * @param string $roletype
     * @param Model  $file
     * @param User   $effector
     * @param array  $diff
     * @return Envelope
     */
    public function notifyDeletedInternal($roletype, $file, $effector, $diff = null)
    {
        try {
            $category = $this->folderMapper->findById($file->getFolder()->getId());
        } catch (Deleted $e) {
            return new Envelope();
        }

        // abstract this so far it doesn't even make sense any more
        return $this->notify(
            LogMapper::KIND_FILE,
            $file->getId(),
            self::TRIGGER_DELETED, // "file.{id}.deleted"
            new Param($file->getTitle()), // the :resource param
            $effector,
            $category,
            $roletype,
            $this->getAccessLevel($file)
        );
    }


    /**
     * @param Model $resource
     * @return string
     */
    protected function getResourceName($resource)
    {
        return $resource->getTitle();
    }


    /**
     * @param Model $resource
     * @return Folder\Model
     */
    protected function getResourceCategory($resource)
    {
        return $this->folderMapper->findById($resource->getFolder()->getId());
    }


    /**
     * @return string
     */
    protected function getResourceKind()
    {
        return LogMapper::KIND_FILE;
    }


    /**
     * @return string
     */
    protected function getResourceNameProperty()
    {
        return 'title';
    }


    /**
     * @return string
     */
    protected function getResourceCategoryProperty()
    {
        return 'folder';
    }


    /**
     * @return int
     * @param Model $file
     */
    protected function getAccessLevel($file): int
    {
        if ($file->isHidden()) {
            return UserAccess::ACCESS_WRITE;
        } else {
            return UserAccess::ACCESS_READ;
        }
    }

}
