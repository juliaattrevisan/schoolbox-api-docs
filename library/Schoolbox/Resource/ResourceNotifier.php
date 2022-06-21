<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\File\Model as File;
use Schoolbox\Resource\Textbox\Model as Textbox;
use Schoolbox\Role;
use Schoolbox\User;

abstract class ResourceNotifier extends AbstractModuleNotifier implements SourceInterface
{

    const TRIGGER_CREATED = 'created';
    const TRIGGER_UPDATED = 'updated';
    const TRIGGER_DELETED = 'deleted';


    /** @var FolderMapper */
    protected $folderMapper;


    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_CMS;
    }


    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     * @return void
     */
    public function loadDependencies($dependencies = null)
    {
        $this->folderMapper = FolderMapper::getInstance();
        parent::loadDependencies($dependencies);
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
     * Notify the system that a CMS event occurred (will be sent to cmsLog table)
     *
     * @param string                   $action
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return void
     */
    protected function notifySystem($action, $resource, $effector): void
    {
        $notif = Notifactory::create(Notif::TOPIC_CMS);

        $notif->notice("CRUD", [
            'object'   => $this->getResourceKind(),
            'objectId' => $resource->getId(),
            'action'   => $action,
            'user'     => $effector->getId(),
        ]);
    }


    /**
     * Generate n Feed\Items, from the perspective of casual observation (rather than targeted at a particular user).
     *
     * @param int    $context - most likely a category ID or ignored
     * @param User   $currentUser
     * @param int    $limit   - how many messages to retrieve
     * @param int    $offset  - by default, most recent
     * @param string $filter  - optionally limit to a particular kind of resource
     * @return Collection|Instance[]
     */
    protected function getFeedInternal($context, User $currentUser, $limit, $offset, $filter)
    {
        $c = new Collection();
        $roletype = $currentUser->getRole()->getType();
        $access      = Access::getInstance();

        try {
            $currentPermission = $access->getFolderAccess($currentUser, $context);
        } catch (Exception $e) {
            return $c;
        }

        if ($currentPermission < UserAccess::ACCESS_READ) {
            return $c;
        }

        try {
            $logs = LogMapper::getInstance()->findForFeed($context, $filter, $limit, $offset, 0, $currentPermission > UserAccess::ACCESS_READ);
        } catch (Mapper\Exception | \Error $e) {
            return $c;
            // mute failures where things were logged about resources that no longer exist
        }

        foreach ($logs as $log) {
            $resource = $log->getObject();
            $user     = $log->getUser();
            $time     = $log->getTimestamp()->getUnixTimeStamp();

            if ($resource !== null && $resource->getId()) {
                switch ($log->getAction()) {
                    case LogMapper::ACTION_CREATE:
                        $c = $c->merge(new Collection($this->notifyCreated($roletype, $resource, $user)->getAll($time, false)));
                        break;
                    case LogMapper::ACTION_UPDATE:
                        // do not index instances by destination, so that feeds of modifying the same resource
                        // will stack properly in the notification class
                        $c = $c->merge(new Collection($this->notifyUpdated($roletype, $resource, $user)->getAll($time, false)));
                        break;
                    case LogMapper::ACTION_DELETE:
                        $c = $c->merge(new Collection($this->notifyDeleted($roletype, $resource, $user)->getAll($time, false)));
                        break;
                }
            }
        }
        return $c;
    }


    /**
     * The generic-est of all messengers. Resources follow a pretty rigid pattern.
     *
     * @param string                $kind
     * @param int                   $id
     * @param string                $action
     * @param ParameterInterface    $param
     * @param User                  $effector
     * @param Folder                $category
     * @param string                $roletype
     * @param int                   $accessLevel
     * @return Envelope
     */
    protected function notify(
        string $kind,
        int $id,
        string $action,
        ParameterInterface $param,
        User $effector,
        $category,
        string $roletype,
        int $accessLevel
    ): Envelope {
        $envelope = new Envelope();

        $categoryId    = $category->getId();

        // manuel hydration
        if (!$category->getName()) {
            try {
                $category = $this->folderMapper->findById($categoryId);
            } catch (Mapper\Exception\Deleted $e) {
                $category = $e->getDeletedObject();
            } catch (Mapper\Exception $e) {
                return $envelope;
            }
        }

        $bob = new Builder();
        $bob->setIcon($this->getUserIcon($effector))
            ->setHref("/homepage/$categoryId#$kind")
            ->setTitle(":effector $action the $kind :resource in :category")
            ->setParams([
                'effector' => new UserParam($effector),
                'resource' => $param,
                'category' => new CategoryParam($category),
            ])
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addTopic("cms.$kind.$id.$action")
            ->setMeta(['time' => \Alaress_Date_Format::now()->getUnixTimeStamp()])
            ->addFilter('roletype', [$roletype]);

        $envelope->broadcastToCategory($category, $accessLevel, $bob->build());

        return $envelope;
    }


    /* ASYNC METHODS */

    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return void
     */
    public function notifySystemCreated($resource, $effector): void
    {
        $this->notifySystem(LogMapper::ACTION_CREATE, $resource, $effector);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @param array                    $diff
     * @return void
     */
    public function notifySystemUpdated($resource, $effector, $diff): void
    {
        /* We don't need this
         */
        unset($diff['modifiedAt']);

        /* Check if this change included a folder move
         */
        if (isset($diff['folder']) || isset($diff['parent'])) {
            $this->notifySystem(LogMapper::ACTION_MOVE, $resource, $effector);
            unset($diff['folder']);
            unset($diff['parent']);
        }

        /* If this change was more than just a move - log as normal
         */
        if (!empty($diff)) {
            $this->notifySystem(LogMapper::ACTION_UPDATE, $resource, $effector);
        }
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return Envelope
     */
    public function notifySystemDeleted($resource, $effector): Envelope
    {
        $this->notifySystem(LogMapper::ACTION_DELETE, $resource, $effector);
        return new Envelope();
    }


    /**
     * @param string                   $roleType
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @param array                    $diff
     * @return Envelope
     */
    public function notifyUpdated($roleType, $resource, $effector, $diff = null): Envelope
    {
        $envelope = new Envelope();

        $accessLevel = $this->getAccessLevel($resource);

        $resourceId    = $resource->getId();

        // setup data from the different resource types
        $resourceName  = $this->getResourceName($resource);
        try {
            $category = $this->getResourceCategory($resource);
        } catch (Exception $e) {
            return $envelope;
        }
        $categoryId    = $category->getId();
        $resourceKind  = $this->getResourceKind();
        $nameField     = $this->getResourceNameProperty();
        $categoryField = $this->getResourceCategoryProperty();
        $resourceHref  = "/homepage/$categoryId#$resourceKind-$resourceId";

        // remove unnecessary diff fields
        unset($diff['hidden']);
        unset($diff['modified']);

        // manuel hydration
        if (!$category->getName()) {
            try {
                $category = $this->folderMapper->findById($categoryId);
            } catch (Mapper\Exception\Deleted $e) {
                $category = $e->getDeletedObject();
            } catch (Mapper\Exception $e) {
                return $envelope;
            }
        }

        $bob = new Builder();

        $bob->setIcon($this->getUserIcon($effector))
            ->setHref("/homepage/$categoryId")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addFilter('roletype', [$roleType])
            ->setTopics(["cms.$resourceKind.$resourceId.updated"]);

        $userParam = new UserParam($effector);
        $catParam  = new CategoryParam($category);

        // if renamed...
        if (isset($diff[$nameField])) {
            $bob->setTitle(":user renamed the $resourceKind :oldname to :newname in :category")
                ->setParams([
                    'user'     => $userParam,
                    'oldname'  => new Param($diff[$nameField][1]),
                    'newname'  => new Param($resourceName, $resourceHref),
                    'category' => $catParam,
                ]);

            $envelope->broadcastToCategory($category, $accessLevel, $bob->build());

            unset($diff[$nameField]);
        }

        // if moved elsewhere...
        if (isset($diff[$categoryField])) {
            $bob->setTitle(":user moved the $resourceKind :name into :category")
                ->setParams([
                    'user'     => $userParam,
                    'name'     => new Param($resourceName, $resourceHref),
                    'category' => $catParam,
                ]);

            $envelope->broadcastToCategory($category, $accessLevel, $bob->build());

            unset($diff[$categoryField]);
        }

        // if changed in some other way...
        if (!isset($diff) || count($diff) > 0) {
            $instances = $this->notify(
                $resourceKind,
                $resourceId,
                self::TRIGGER_UPDATED,
                new Param($resourceName, $resourceHref),
                $effector,
                $category,
                $roleType,
                $accessLevel
            )->getAll();

            foreach ($instances as $inst) {
                $envelope->broadcastToCategory($category, $accessLevel, $inst);
            }
        }

        return $envelope;
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @return string
     */
    abstract protected function getResourceName($resource): string;


    /**
     * @param Folder|File|Link|Textbox $resource
     * @throws Exception
     * @return Folder
     */
    abstract protected function getResourceCategory($resource): Folder;


    /**
     * @return string
     */
    abstract protected function getResourceKind(): string;


    /**
     * @return string
     */
    abstract protected function getResourceNameProperty(): string;


    /**
     * @return string
     */
    abstract protected function getResourceCategoryProperty(): string;


    /**
     * @param string                   $roletype
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $author
     * @param array|null               $diff
     * @return Envelope
     */
    abstract public function notifyCreated($roletype, $resource, $author, $diff = null): Envelope;


    /**
     * @param string                   $roletype
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $author
     * @param array|null               $diff
     * @return Envelope
     */
    abstract public function notifyDeleted($roletype, $resource, $author, $diff = null): Envelope;


    /* ASYNC METHODS */

    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return Envelope
     */
    public function notifyStudentCreated($resource, $effector): Envelope
    {
        return $this->notifyCreated(Role::STUDENT, $resource, $effector);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @param array|null               $diff
     * @return Envelope
     */
    public function notifyStudentUpdated($resource, $effector, $diff = null): Envelope
    {
        return $this->notifyUpdated(Role::STUDENT, $resource, $effector, $diff);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return Envelope
     */
    public function notifyStudentDeleted($resource, $effector): Envelope
    {
        return $this->notifyDeleted(Role::STUDENT, $resource, $effector);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return Envelope
     */
    public function notifyStaffCreated($resource, $effector): Envelope
    {
        return $this->notifyCreated(Role::STAFF, $resource, $effector);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @param array|null               $diff
     * @return Envelope
     */
    public function notifyStaffUpdated($resource, $effector, $diff = null): Envelope
    {
        return $this->notifyUpdated(Role::STAFF, $resource, $effector, $diff);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return Envelope
     */
    public function notifyStaffDeleted($resource, $effector): Envelope
    {
        return $this->notifyDeleted(Role::STAFF, $resource, $effector);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return Envelope
     */
    public function notifyParentCreated($resource, $effector): Envelope
    {
        return $this->notifyCreated(Role::PARENT, $resource, $effector);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @param array|null               $diff
     * @return Envelope
     */
    public function notifyParentUpdated($resource, $effector, $diff = null): Envelope
    {
        return $this->notifyUpdated(Role::PARENT, $resource, $effector, $diff);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return Envelope
     */
    public function notifyParentDeleted($resource, $effector): Envelope
    {
        return $this->notifyDeleted(Role::PARENT, $resource, $effector);
    }


    /**
     * @return int
     * @param Folder|File|Link|Textbox $resource
     */
    abstract protected function getAccessLevel($resource): int;
}
