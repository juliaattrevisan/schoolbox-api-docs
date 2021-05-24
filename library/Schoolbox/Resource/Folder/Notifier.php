<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Folder;

use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\LogMapper;
use Schoolbox\Resource\ResourceNotifier;
use Schoolbox\User;

class Notifier extends ResourceNotifier
{

    const TRIGGER_RENAMED = 'renamed';
    const TRIGGER_MOVED = 'moved';


    /**
     * @inheritdoc
     */
    public function getFeed($context, User $user, $limit = self::HARD_LIMIT, $offset = 0)
    {
        return $this->getFeedInternal($context, $user, $limit, $offset, LogMapper::KIND_FOLDER);
    }


    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_CMS;
    }


    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        switch ($bundle->getMethod()) {
            case Mapper::METHOD_CREATE:
                $bundle->attach(self::TRIGGER_CREATED, self::IMMEDIATELY);
                break;

            case Mapper::METHOD_UPDATE:
                $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
                break;

            case Mapper::METHOD_DELETE:
                $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
                break;
        }
    }


    /**
     * Returns the metadata pertaining to this source.
     * Useful to RSS
     *
     * @return mixed
     */
    public function getChannelMetadata()
    {
        return null;
    }


    /**
     * Notify relevant students that this category has been created
     * @inheritDoc
     */
    public function notifyCreated($roleType, $category, $creator, $diff = null): Envelope
    {
        return $this->notify(
            LogMapper::KIND_FOLDER,
            $category->getId(),
            self::TRIGGER_CREATED,
            new Param($category->getName(), "/homepage/" . $category->getId(), ParameterInterface::STACKING_APPEND),
            $creator,
            $category->getParent(),
            $roleType,
            UserAccess::ACCESS_READ
        );
    }


    /**
     * @inheritDoc
     */
    public function notifyDeleted($roleType, $category, $effector, $diff = null): Envelope
    {
        return $this->notify(
            LogMapper::KIND_FOLDER,
            $category->getId(),
            self::TRIGGER_DELETED,
            new Param($category->getName(), null, ParameterInterface::STACKING_APPEND),
            $effector,
            $category->getParent(),
            $roleType,
            UserAccess::ACCESS_READ
        );
    }


    /**
     * @param Model $resource
     * @return string
     */
    protected function getResourceName($resource): string
    {
        return $resource->getName();
    }


    /**
     * @param Model $resource
     * @return Model
     */
    protected function getResourceCategory($resource): Model
    {
        return $resource->getParent();
    }


    /**
     * @return string
     */
    protected function getResourceKind(): string
    {
        return LogMapper::KIND_FOLDER;
    }


    /**
     * @return string
     */
    protected function getResourceNameProperty(): string
    {
        return 'name';
    }


    /**
     * @return string
     */
    protected function getResourceCategoryProperty(): string
    {
        return 'parent';
    }


    /**
     * @return int
     * @param Model $resource
     */
    protected function getAccessLevel($resource): int
    {
        return UserAccess::ACCESS_READ;
    }
}
