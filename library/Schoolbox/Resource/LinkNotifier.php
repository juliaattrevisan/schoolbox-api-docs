<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource;

use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class LinkNotifier extends ResourceNotifier
{


    /**
     * @inheritdoc
     */
    public function getFeed($context, User $user, $limit = self::HARD_LIMIT, $offset = 0)
    {
        return $this->getFeedInternal($context, $user, $limit, $offset, LogMapper::KIND_LINK);
    }


    /**
     * @inheritdoc
     */
    public function notifyCreated($roletype, $link, $effector, $diff = null): Envelope
    {
        try {
            $category = $this->folderMapper->findById($link->getFolder()->getId());
        } catch (Exception $e) {
            return new Envelope();
        }

        return $this->notify(
            LogMapper::KIND_LINK,
            $link->getId(),
            self::TRIGGER_CREATED,    // "link.{id}.created"
            new Param($link->getDescription(), $link->getHref(), ParameterInterface::STACKING_APPEND), // the :resource param
            $effector,
            $category,
            $roletype,
            $this->getAccessLevel($link)
        );
    }


    /**
     * @inheritdoc
     */
    public function notifyDeleted($roletype, $link, $effector, $diff = null): Envelope
    {
        try {
            $category = $this->folderMapper->findById($link->getFolder()->getId());
        } catch (Exception $e) {
            return new Envelope();
        }

        // abstract this so far it doesn't even make sense any more
        return $this->notify(
            LogMapper::KIND_LINK,
            $link->getId(),
            self::TRIGGER_DELETED,    // "link.{id}.deleted"
            new Param($link->getDescription(), null, ParameterInterface::STACKING_APPEND), // the :resource param
            $effector,
            $category,
            $roletype,
            $this->getAccessLevel($link)
        );
    }


    /**
     * @param Link $resource
     * @return string
     */
    protected function getResourceName($resource): string
    {
        return $resource->getDescription();
    }


    /**
     * @param Link $resource
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
        return LogMapper::KIND_LINK;
    }


    /**
     * @return string
     */
    protected function getResourceNameProperty(): string
    {
        return 'description';
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
     * @param Link $link
     */
    protected function getAccessLevel($link): int
    {
        if ($link->isHidden()) {
            return UserAccess::ACCESS_WRITE;
        } else {
            return UserAccess::ACCESS_READ;
        }
    }
}
