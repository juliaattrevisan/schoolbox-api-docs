<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder;


use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Schoolbox\Collection;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\LogMapper;
use Schoolbox\Resource\ResourceNotifier;
use Schoolbox\User;

class Notifier extends ResourceNotifier {

    const TRIGGER_RENAMED = 'renamed';
    const TRIGGER_MOVED   = 'moved';


    /**
     * @inheritdoc
     */
    public function getFeed($context, User $user, $limit = self::HARD_LIMIT, $offset = 0) {
        return $this->getFeedInternal($context, $user->getRole()->getType(), $limit, $offset, LogMapper::KIND_FOLDER);
    }


    /**
     * @return string
     */
    protected function getSlug() {
        return NSMapper::NS_CMS;
    }

    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle) {
        switch($bundle->getMethod()) {
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
    public function getChannelMetadata() {
        return null;
    }


    /**
     * Notify relevant students that this category has been created
     *
     * @param string $roleType
     * @param Model $category
     * @param User $creator
     * @param array $diff
     * @return Envelope
     */
    public function notifyCreated($roleType, $category, $creator, $diff = null) {
        return $this->notify(
            LogMapper::KIND_FOLDER, $category->getId(), self::TRIGGER_CREATED,
            new Param($category->getName(), "/homepage/".$category->getId()),
            $creator, $category->getParent(), $roleType
        );
    }



    /**
     * @param string $roleType
     * @param Model $category
     * @param User $effector
     * @param array $diff
     * @return Envelope
     */
    public function notifyDeleted($roleType, $category, $effector, $diff = null) {
        return $this->notify(
            LogMapper::KIND_FOLDER, $category->getId(), self::TRIGGER_DELETED,
            new Param($category->getName()),
            $effector, $category->getParent(), $roleType
        );
    }


    /**
     * @param Model $resource
     * @return string
     */
    protected function getResourceName($resource) {
        return $resource->getName();
    }


    /**
     * @param Model $resource
     * @return Model
     */
    protected function getResourceCategory($resource) {
        return $resource->getParent();
    }


    /**
     * @return string
     */
    protected function getResourceKind() {
        return LogMapper::KIND_FOLDER;
    }


    /**
     * @return string
     */
    protected function getResourceNameProperty() {
        return 'name';
    }


    /**
     * @return string
     */
    protected function getResourceCategoryProperty() {
        return 'parent';
    }

}
