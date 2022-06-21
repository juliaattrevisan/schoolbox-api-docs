<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Textbox;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\LogMapper;
use Schoolbox\Resource\ResourceNotifier;
use Schoolbox\User;

class Notifier extends ResourceNotifier
{
    /**
     * If this is an update, and neither the body nor heading were updated:
     * don't bundle anything.
     *
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        // Body or heading not updated: nothing to notify about
        if ($bundle->getMethod() === Mapper::METHOD_UPDATE) {
            $diff = $bundle->getEvent()->getDiff();
            if (!(isset($diff['body']) || isset($diff['heading']))) {
                return;
            }
        }

        parent::bundleTriggers($bundle);
    }


    /**
     * @param string     $action
     * @param string     $roletype
     * @param Model      $resource
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    protected function notifyAffected($action, $roletype, $resource, $effector, $diff = null): Envelope
    {
        $envelope = new Envelope();
        $resourceId = $resource->getId();

        try {
            $category = $this->folderMapper->findById($resource->getCategory()->getId());
        } catch (Mapper\Exception $e) {
            return $envelope;
        }

        $bob = new Builder();

        $heading = $this->getResourceName($resource);
        $href    = "/homepage/" . $category->getId() . "/#textbox" . $resourceId;

        $bob->setTitle(":author $action content in :homepage \":heading\"")
            ->setParams([
                'author'   => new UserParam($effector),
                'homepage' => new CategoryParam($category),
                'heading'  => new Param($heading, $href, ParameterInterface::STACKING_APPEND),
            ])
            ->setIcon($this->getUserIcon($effector))
            ->setHref($href)
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addTopic("cms.textbox.$resourceId.$action")
            ->setMeta(['time' => \Alaress_Date_Format::now()->getUnixTimeStamp()])
            ->addFilter('roletype', [$roletype]);

        $envelope->broadcastToCategory($category, UserAccess::ACCESS_READ, $bob->build());

        return $envelope;
    }


    /**
     * @inheritDoc
     */
    public function notifyCreated($roletype, $resource, $author, $diff = null): Envelope
    {
        return $this->notifyAffected(self::TRIGGER_CREATED, $roletype, $resource, $author, $diff);
    }


    /**
     * @inheritDoc
     */
    public function notifyUpdated($roletype, $resource, $effector, $diff = null): Envelope
    {
        return $this->notifyAffected(self::TRIGGER_UPDATED, $roletype, $resource, $effector, $diff);
    }


    /**
     * @inheritDoc
     */
    public function notifyDeleted($roletype, $resource, $effector, $diff = null): Envelope
    {
        return $this->notifyAffected(self::TRIGGER_DELETED, $roletype, $resource, $effector, $diff);
    }


    /**
     * @inheritdoc
     */
    public function getFeed($context, User $user, $limit = self::HARD_LIMIT, $offset = 0)
    {
        return $this->getFeedInternal($context, $user, $limit, $offset, LogMapper::KIND_CONTENT);
    }


    /**
     * @param Model $resource
     * @return string
     */
    protected function getResourceName($resource): string
    {
        if (empty($resource->getHeading())) {
            return "Text Box";
        } else {
            return $resource->getHeading();
        }
    }


    /**
     * @param Model $resource
     * @return Folder
     */
    protected function getResourceCategory($resource): Folder
    {
        return $resource->getCategory();
    }


    /**
     * @return string
     */
    protected function getResourceKind(): string
    {
        return LogMapper::KIND_CONTENT;
    }


    /**
     * @return string
     */
    protected function getResourceNameProperty(): string
    {
        return 'heading';
    }


    /**
     * @return string
     */
    protected function getResourceCategoryProperty(): string
    {
        return 'category';
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
