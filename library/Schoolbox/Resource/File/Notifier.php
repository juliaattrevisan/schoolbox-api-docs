<?php
/**
 * @author     Amri Sannang <amri.sannang@alaress.com.au>
 * @copiedFrom Matt Day <matt.day@alaress.com.au>
 * @copyright  Copyright 2019, Schoolbox Pty Ltd
 * @link       https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\File;

use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as Folder;
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
     * @inheritdoc
     */
    public function notifyCreated($roletype, $file, $effector, $diff = null): Envelope
    {
        if (!$file->getFolder()) {
            return new Envelope();
        }

        try {
            $category = $this->folderMapper->findById($file->getFolder()->getId());
        } catch (Exception $e) {
            return new Envelope();
        }

        return $this->notify(
            LogMapper::KIND_FILE,
            $file->getId(),
            self::TRIGGER_CREATED, // "file.{id}.created"
            new Param($file->getTitle(), '/send.php?id=' . $file->getId(), ParameterInterface::STACKING_APPEND), // the :resource param
            $effector,
            $category,
            $roletype,
            $this->getAccessLevel($file)
        );
    }


    /**
     * @inheritdoc
     */
    public function notifyDeleted($roletype, $file, $effector, $diff = null): Envelope
    {
        try {
            $category = $this->folderMapper->findById($file->getFolder()->getId());
        } catch (Exception $e) {
            return new Envelope();
        }

        // abstract this so far it doesn't even make sense any more
        return $this->notify(
            LogMapper::KIND_FILE,
            $file->getId(),
            self::TRIGGER_DELETED, // "file.{id}.deleted"
            new Param($file->getTitle(), null, ParameterInterface::STACKING_APPEND), // the :resource param
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
    protected function getResourceName($resource): string
    {
        return $resource->getTitle();
    }


    /**
     * @param Model $resource
     * @return Folder
     * @throws Exception
     */
    protected function getResourceCategory($resource): Folder
    {
        return $this->folderMapper->findById($resource->getFolder()->getId());
    }


    /**
     * @return string
     */
    protected function getResourceKind(): string
    {
        return LogMapper::KIND_FILE;
    }


    /**
     * @return string
     */
    protected function getResourceNameProperty(): string
    {
        return 'title';
    }


    /**
     * @return string
     */
    protected function getResourceCategoryProperty(): string
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
