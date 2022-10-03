<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Permission\Access;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\User;

class UserAccessNotifier extends AbstractModuleNotifier
{
    const TRIGGER_JOINED    = 'joined';
    const TRIGGER_LEFT      = 'left';
    const TRIGGER_REQUESTED = 'requested';
    const TRIGGER_APPROVED  = 'approved';

    const USER_JOINED                   = ':user joined :groupname';
    const USER_REQUESTED                = ":user asked to join :groupname";
    const USER_LEFT                     = ':user left :groupname';
    const EFFECTOR_ADDED_USER           = ':effector added :user to :groupname';
    const EFFECTOR_REMOVED_USER         = ':effector removed :user from :groupname';
    const EFFECTOR_ADDED_YOU            = ':effector has added you to :groupname';
    const EFFECTOR_REMOVED_YOU          = ':effector has removed you from :groupname';
    const EFFECTOR_MADE_YOU_A_MEMBER    = ':effector has made you a member of :groupname';
    const EFFECTOR_ALLOWED_YOU_TO_WRITE = ':effector has allowed you to create content for :groupname';
    const EFFECTOR_MADE_YOU_ADMIN       = ':effector has made you an administrator of :groupname';

    private FolderMapper $folderMapper;

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_GROUP;
    }

    /**
     * @inheritDoc
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies['folderMapper'])) {
            $dependencies['folderMapper'] = FolderMapper::getInstance();
        }
        parent::loadDependencies($dependencies);
    }

    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        $method = $bundle->getMethod();

        /** @var UserAccess $userAccess */
        $userAccess = $bundle->getEvent()->getObject();

        // at least at present, we're not interested in anything other than homepage access changes
        if ($userAccess->getService() != UserAccess::SERVICE_HOMEPAGE) {
            return;
        }

        try {
            /** @var Group $group */
            $group = GroupMapper::getInstance()->findOneByColumn('cid', $userAccess->getServiceIdentifier());
        } catch (Exception $e) {
            // no group, do nothing
            return;
        }

        if ($method == Mapper::METHOD_CREATE) {
            if ($group->isFree() && $userAccess->getUser()->getId() == $bundle->getEvent()->getEffector()->getId()) {
                // if user added themselves
                $bundle->attach(self::TRIGGER_JOINED, self::IMMEDIATELY);
            } elseif ($userAccess->getAccessLevel() == UserAccess::ACCESS_DENY) {
                // if this is a request to join a moderated group
                $bundle->attach(self::TRIGGER_REQUESTED, self::IMMEDIATELY);
            } else {
                // if the user was added by someone else
                $bundle->attach(self::TRIGGER_APPROVED, self::IMMEDIATELY);
            }
        } elseif ($method == Mapper::METHOD_DELETE) {
            // useraccess revoked
            $bundle->attach(self::TRIGGER_LEFT, self::IMMEDIATELY);
        } elseif ($method == Mapper::METHOD_UPDATE) {
            // access level changed (usually moderation)
            $bundle->attach(self::TRIGGER_APPROVED, self::IMMEDIATELY);
        }
    }


    /* ASYNC METHODS */

    /**
     * @param string     $body
     * @param string     $label
     * @param Folder     $folder
     * @param UserAccess $userAccess
     * @param User|null  $effector
     * @return Envelope
     */
    protected function notifyAdmin(string $body, string $label, Folder $folder, UserAccess $userAccess, ?User $effector): Envelope
    {
        $envelope = new Envelope();
        $bob      = $this->getBob($body, $label, $folder, $userAccess, $effector, true);
        $envelope->broadcastToCategory($folder, UserAccess::ACCESS_FULL, $bob->build());
        return $envelope;
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $effector
     * @return Envelope
     */
    protected function notifyJoined(UserAccess $userAccess, User $effector): Envelope
    {
        try {
            $folder = $this->folderMapper->findById($userAccess->getServiceIdentifier());
        } catch (Exception $e) {
            return new Envelope();
        }

        if ($userAccess->getUser()->getId() === $effector->getId()) {
            $envelope = $this->notifyAdmin(self::USER_JOINED, self::TRIGGER_JOINED, $folder, $userAccess, $effector);
        } else {
            $envelope = $this->notifyAdmin(self::EFFECTOR_ADDED_USER, self::TRIGGER_JOINED, $folder, $userAccess, $effector);
        }

        $bob = $this->getBob(self::EFFECTOR_ADDED_YOU, self::TRIGGER_APPROVED, $folder, $userAccess, $effector, false);
        $envelope->targetToUser($userAccess->getUser(), $bob->build());

        return $envelope;
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    public function notifyStaffJoined(UserAccess $userAccess, User $user): Envelope
    {
        return $this->notifyJoined($userAccess, $user);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    public function notifyStudentJoined(UserAccess $userAccess, User $user): Envelope
    {
        return $this->notifyJoined($userAccess, $user);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    public function notifyParentJoined(UserAccess $userAccess, User $user): Envelope
    {
        return $this->notifyJoined($userAccess, $user);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $effector
     * @return Envelope
     */
    protected function notifyLeft(UserAccess $userAccess, User $effector): Envelope
    {
        try {
            $folder = $this->folderMapper->findById($userAccess->getServiceIdentifier());
        } catch (Exception $e) {
            return new Envelope();
        }

        if ($userAccess->getUser()->getId() === $effector->getId()) {
            $envelope = $this->notifyAdmin(self::USER_LEFT, self::TRIGGER_LEFT, $folder, $userAccess, $effector);
        } else {
            $envelope = $this->notifyAdmin(self::EFFECTOR_REMOVED_USER, self::TRIGGER_LEFT, $folder, $userAccess, $effector);
        }

        $bob = $this->getBob(self::EFFECTOR_REMOVED_YOU, self::TRIGGER_APPROVED, $folder, $userAccess, $effector, false);
        $bob->setHref('/');
        $envelope->targetToUser($userAccess->getUser(), $bob->build());
        return $envelope;
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $effector
     * @return Envelope
     */
    public function notifyStaffLeft(UserAccess $userAccess, User $effector): Envelope
    {
        return $this->notifyLeft($userAccess, $effector);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $effector
     * @return Envelope
     */
    public function notifyStudentLeft(UserAccess $userAccess, User $effector): Envelope
    {
        return $this->notifyLeft($userAccess, $effector);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $effector
     * @return Envelope
     */
    public function notifyParentLeft(UserAccess $userAccess, User $effector): Envelope
    {
        return $this->notifyLeft($userAccess, $effector);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $effector
     * @return Envelope
     */
    protected function notifyRequested(UserAccess $userAccess, User $effector): Envelope
    {
        try {
            $folder = $this->folderMapper->findById($userAccess->getServiceIdentifier());
        } catch (Exception $e) {
            return new Envelope();
        }
        return $this->notifyAdmin(self::USER_REQUESTED, self::TRIGGER_REQUESTED, $folder, $userAccess, $effector);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $effector
     * @return Envelope
     */
    public function notifyStaffRequested(UserAccess $userAccess, User $effector): Envelope
    {
        return $this->notifyRequested($userAccess, $effector);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $effector
     * @return Envelope
     */
    public function notifyStudentRequested(UserAccess $userAccess, User $effector): Envelope
    {
        return $this->notifyRequested($userAccess, $effector);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $effector
     * @return Envelope
     */
    public function notifyParentRequested(UserAccess $userAccess, User $effector): Envelope
    {
        return $this->notifyRequested($userAccess, $effector);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    protected function notifyApproved(UserAccess $userAccess, User $effector, ?array $diff = null): Envelope
    {
        try {
            $folder = $this->folderMapper->findById($userAccess->getServiceIdentifier());
        } catch (Exception $e) {
            return new Envelope();
        }

        if ($userAccess->getUser()->getId() == $effector->getId()) {
            $envelope = $this->notifyAdmin(self::USER_JOINED, self::TRIGGER_JOINED, $folder, $userAccess, $effector);
        } else {
            $envelope = $this->notifyAdmin(self::EFFECTOR_ADDED_USER, self::TRIGGER_JOINED, $folder, $userAccess, $effector);
        }

        // TODO uncomment these bits of the method to make it more informative once the Mapper can handle composite IDs
//      // no diff in access level, this isn't something we're interested in
//      if(!isset($diff['accessLevel'])) {
//          return $msgr;
//      }
//
//      // new is [0], old is [1]
//      $newAccess = $diff['accessLevel'][0];
//      $oldAccess = $diff['accessLevel'][1];

        // Customize the approval message
        switch ($userAccess->getAccessLevel()) {
            case UserAccess::ACCESS_DENY:
                $messageText = self::EFFECTOR_REMOVED_YOU;
                break;
            case UserAccess::ACCESS_READ:
                $messageText = self::EFFECTOR_MADE_YOU_A_MEMBER;
                break;
            case UserAccess::ACCESS_WRITE:
                $messageText = self::EFFECTOR_ALLOWED_YOU_TO_WRITE;
                break;
            case UserAccess::ACCESS_FULL:
                $messageText = self::EFFECTOR_MADE_YOU_ADMIN;
                break;
            default:
                // can't handle this: bail out and send nothing
                return $envelope;
        }

        $bob = $this->getBob($messageText, self::TRIGGER_APPROVED, $folder, $userAccess, $effector, false);
        $bob->putMeta('time', (\Alaress_Date_Format::now())->getUnixTimeStamp());
        $envelope->targetToUser($userAccess->getUser(), $bob->build());
        return $envelope;
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffApproved(UserAccess $userAccess, User $effector, ?array $diff = null): Envelope
    {
        return $this->notifyApproved($userAccess, $effector, $diff);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentApproved(UserAccess $userAccess, User $effector, ?array $diff = null): Envelope
    {
        return $this->notifyApproved($userAccess, $effector, $diff);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $effector
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentApproved(UserAccess $userAccess, User $effector, ?array $diff = null): Envelope
    {
        return $this->notifyApproved($userAccess, $effector, $diff);
    }

    /**
     * @param string     $body
     * @param string     $label
     * @param Folder     $folder
     * @param UserAccess $userAccess
     * @param User|null  $effector
     * @param bool       $isAdmin
     * @return Builder
     */
    private function getBob(string $body, string $label, Folder $folder, UserAccess $userAccess, ?User $effector, bool $isAdmin): Builder
    {
        $bob = new Builder();

        /** @var ParameterInterface[] $params */
        $params = [
            'groupname' => new CategoryParam($folder, ParameterInterface::STACKING_APPEND),
        ];

        if (str_contains($body, ':user')) {
            $params['user'] = new UserParam($userAccess->getUser(), ParameterInterface::STACKING_APPEND);
        }
        if ($effector && (str_contains($body, ':effector'))) {
            $params['effector'] = new UserParam($effector);
        }

        $href = $isAdmin ? "/group/modify/" . $folder->getId() : "/homepage/" . $folder->getId();

        $bob->setTitle($body)
            ->setHref($href)
            ->setIcon($this->getUserIcon($effector ?? $userAccess->getUser()))
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::NOTICE)
            ->addTopic("group.{$folder->getId()}.$label")
            ->setTimestamp((\Alaress_Date_Format::now())->getUnixTimeStamp())
            ->putMeta('time', $userAccess->getCreatedAt())
            ->setParams($params);

        return $bob;
    }

    /**
     * @param FolderMapper $folderMapper
     * @return static
     */
    protected function setFolderMapper(FolderMapper $folderMapper): static
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }
}
