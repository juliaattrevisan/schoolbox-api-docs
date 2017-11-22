<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Notifactory;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\ScheduleException;
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
     * This version is special for CMS: in addition to the roletypes, also notify the system for logging
     *
     * @param string $label
     * @return array
     */
    public function retrieveCallbacks($label)
    {
        $callbacks = parent::retrieveCallbacks($label);
        $method    = 'notifySystem' . ucfirst($label);
        if (method_exists($this, $method)) {
            $callbacks[] = array($this, $method);
        }

        return $callbacks;
    }


    /**
     * Notify the system that a CMS event occurred (will be sent to cmsLog table)
     *
     * @param string                     $action
     * @param Folder|File|Link|Textbox   $resource
     * @param User                       $effector
     * @throws ScheduleException
     * @return void
     */
    protected function notifySystem($action, $resource, $effector)
    {
        $notif = Notifactory::create(Notif::TOPIC_CMS);

        $notif->notice("CRUD", array(
            'object'   => $this->getResourceKind(),
            'objectId' => $resource->getId(),
            'action'   => $action,
            'user'     => $effector->getId()
        ));
    }


    /**
     * Generate n Feed\Items, from the perspective of casual observation (rather than targeted at a particular user).
     *
     * @param int    $context  - most likely a category ID or ignored
     * @param string $roletype - the roletype of the user for whom this feed is being retrieved
     * @param int    $limit    - how many messages to retrieve
     * @param int    $offset   - by default, most recent
     * @param string $filter   - optionally limit to a particular kind of resource
     * @return Collection|Instance[]
     */
    protected function getFeedInternal($context, $roletype, $limit = self::HARD_LIMIT, $offset = 0, $filter)
    {
        $logs = LogMapper::getInstance()->findForFeed($context, $filter, $limit, $offset);

        $c = new Collection();

        foreach ($logs as $log) {

            try {
                $resource = $log->getObject();
                $user = $log->getUser();
                $time = $log->getTimestamp()->getUnixTimeStamp();

                switch ($log->getAction()) {
                    case LogMapper::ACTION_CREATE:
                        $c = $c->merge(new Collection($this->notifyCreated($roletype, $resource, $user)->getAll($time)));
                        break;
                    case LogMapper::ACTION_UPDATE:
                        $c = $c->merge(new Collection($this->notifyUpdated($roletype, $resource, $user)->getAll($time)));
                        break;
                    case LogMapper::ACTION_DELETE:
                        $c = $c->merge(new Collection($this->notifyDeleted($roletype, $resource, $user)->getAll($time)));
                        break;
                }
            } catch(Mapper\Exception|\Error $e) {
                // mute failures where things were logged about resources that no longer exist
            }

        }
        return $c;
    }


    /**
     * The generic-est of all messengers. Resources follow a pretty rigid pattern.
     *
     * @param string             $kind
     * @param int                $id
     * @param string             $action
     * @param ParameterInterface $param
     * @param User               $effector
     * @param Folder|ISoftDeletable $category
     * @param string             $roletype
     * @return Envelope
     */
    protected function notify($kind, $id, $action, $param, $effector, $category, $roletype)
    {
        $envelope = new Envelope();

        $categoryId    = $category->getId();
        $effectorExtId = $effector->getExternalId();

        // manuel hydration
        if (!$category->getName()) {
            try {
                $category = $this->folderMapper->findById($categoryId);
            } catch (Mapper\Exception\Deleted $e) {
                $category = $e->getDeletedObject();
            }
        }

        $bob = new Builder();
        $bob->setIcon("/portrait.php?userId=$effectorExtId&size={$this->iconSize}")
            ->setHref("/homepage/$categoryId#$kind-$id")
            ->setTitle(":effector $action the $kind :resource in :category")
            ->setParams(array(
                'effector' => new UserParam($effector, ParameterInterface::STACKING_REPLACE),
                'resource' => $param,
                'category' => new CategoryParam($category)
            ))
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addTopic("cms.$kind.$id.$action")
            ->addFilter('roletype', $roletype);

        $envelope->broadcastToCategory($category, UserAccess::ACCESS_READ, $bob->build());

        return $envelope;
    }


    /* ASYNC METHODS */

    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return void
     */
    public function notifySystemCreated($resource, $effector)
    {
        $this->notifySystem(LogMapper::ACTION_CREATE, $resource, $effector);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @param array                    $diff
     * @return void
     */
    public function notifySystemUpdated($resource, $effector, $diff)
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
     * @param User                       $effector
     * @return void
     */
    public function notifySystemDeleted($resource, $effector)
    {
        $this->notifySystem(LogMapper::ACTION_DELETE, $resource, $effector);
    }


    /**
     * @param string                   $roleType
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @param array                    $diff
     * @return Envelope
     */
    public function notifyUpdated($roleType, $resource, $effector, $diff = null)
    {
        $envelope = new Envelope();

        $effectorExtId = $effector->getExternalId();
        $resourceId    = $resource->getId();

        // setup data from the different resource types
        $resourceName  = $this->getResourceName($resource);
        $category      = $this->getResourceCategory($resource);
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
            $category = $this->folderMapper->findById($categoryId);
        }

        $bob = new Builder();

        $bob->setIcon("/portrait.php?userId=$effectorExtId&size={$this->iconSize}")
            ->setHref("/homepage/$categoryId")
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addFilter('roletype', $roleType)
            ->setTopics(array("cms.$resourceKind.$resourceId.updated"));

        $userParam = new UserParam($effector, ParameterInterface::STACKING_REPLACE);
        $catParam  = new CategoryParam($category);

        // if renamed...
        if (isset($diff[$nameField])) {
            $bob->setTitle(":user renamed the $resourceKind :oldname to :newname in :category")
                ->setParams(array(
                    'user'     => $userParam,
                    'oldname'  => new Param($diff[$nameField][1]),
                    'newname'  => new Param($resourceName, $resourceHref),
                    'category' => $catParam
                ));

            $envelope->broadcastToCategory($category, UserAccess::ACCESS_READ, $bob->build());

            unset($diff[$nameField]);
        }

        // if moved elsewhere...
        if (isset($diff[$categoryField])) {
            $bob->setTitle(":user moved the $resourceKind :name into :category")
                ->setParams(array(
                    'user'     => $userParam,
                    'name'     => new Param($resourceName, $resourceHref),
                    'category' => $catParam
                ));

            $envelope->broadcastToCategory($category, UserAccess::ACCESS_READ, $bob->build());

            unset($diff[$categoryField]);
        }

        // if changed in some other way...
        if (count($diff) || is_null($diff)) {
            $instances = $this->notify(
                $resourceKind, $resourceId, self::TRIGGER_UPDATED,
                new Param($resourceName, $resourceHref), $effector, $category, $roleType
            )->getAll();

            foreach ($instances as $inst) {
                $envelope->broadcastToCategory($category, UserAccess::ACCESS_READ, $inst);
            }
        }

        return $envelope;
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @return string
     */
    protected abstract function getResourceName($resource);


    /**
     * @param Folder|File|Link|Textbox $resource
     * @return Folder
     */
    protected abstract function getResourceCategory($resource);


    /**
     * @return string
     */
    protected abstract function getResourceKind();


    /**
     * @return string
     */
    protected abstract function getResourceNameProperty();


    /**
     * @return string
     */
    protected abstract function getResourceCategoryProperty();


    /**
     * @param string                   $roletype
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $author
     * @param array                    $diff
     * @return Envelope
     */
    public abstract function notifyCreated($roletype, $resource, $author, $diff = null);


    /**
     * @param string                   $roletype
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $author
     * @param array                    $diff
     * @return Envelope
     */
    public abstract function notifyDeleted($roletype, $resource, $author, $diff = null);


    /* ASYNC METHODS */

    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return Envelope
     */
    public function notifyStudentCreated($resource, $effector)
    {
        return $this->notifyCreated(Role::STUDENT, $resource, $effector);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @param array                    $diff
     * @return Envelope
     */
    public function notifyStudentUpdated($resource, $effector, $diff = null)
    {
        return $this->notifyUpdated(Role::STUDENT, $resource, $effector, $diff);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return Envelope
     */
    public function notifyStudentDeleted($resource, $effector)
    {
        return $this->notifyDeleted(Role::STUDENT, $resource, $effector);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return Envelope
     */
    public function notifyStaffCreated($resource, $effector)
    {
        return $this->notifyCreated(Role::STAFF, $resource, $effector);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @param array                    $diff
     * @return Envelope
     */
    public function notifyStaffUpdated($resource, $effector, $diff = null)
    {
        return $this->notifyUpdated(Role::STAFF, $resource, $effector, $diff);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return Envelope
     */
    public function notifyStaffDeleted($resource, $effector)
    {
        return $this->notifyDeleted(Role::STAFF, $resource, $effector);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return Envelope
     */
    public function notifyParentCreated($resource, $effector)
    {
        return $this->notifyCreated(Role::PARENT, $resource, $effector);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @param array                    $diff
     * @return Envelope
     */
    public function notifyParentUpdated($resource, $effector, $diff = null)
    {
        return $this->notifyUpdated(Role::PARENT, $resource, $effector, $diff);
    }


    /**
     * @param Folder|File|Link|Textbox $resource
     * @param User                     $effector
     * @return Envelope
     */
    public function notifyParentDeleted($resource, $effector)
    {
        return $this->notifyDeleted(Role::PARENT, $resource, $effector);
    }

}
