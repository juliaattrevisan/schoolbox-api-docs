<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Permission\Access;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
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
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class UserAccessNotifier extends AbstractModuleNotifier
{

    const TRIGGER_JOINED = 'joined';
    const TRIGGER_LEFT = 'left';
    const TRIGGER_REQUESTED = 'requested';
    const TRIGGER_APPROVED = 'approved';


    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_GROUP;
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
     * @param UserAccess $userAccess
     * @param User|null  $effector
     * @return Envelope
     */
    protected function notifyAdmin(string $body, string $label, UserAccess $userAccess, ?User $effector = null)
    {
        $envelope = new Envelope();

        $bob = new Builder();

        try {
            $cat = FolderMapper::getInstance()->findById($userAccess->getServiceIdentifier());
            $user = UserMapper::getInstance()->findById($userAccess->getUser()->getId());
        } catch (Exception $e) {
            return $envelope;
        }

        /** @var ParameterInterface[] $params */
        $params = [
            'user'      => new UserParam($user),
            'groupname' => new CategoryParam($cat)
        ];

        if ($effector) {
            $params['effector'] = new UserParam($effector);
        }

        $bob->setTitle($body)
            ->setHref("/group/modify/" . $cat->getId())
            ->setIcon($this->getUserIcon($effector ?? $user))
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::NOTICE)
            ->addTopic("group.{$cat->getId()}.$label")
            ->setParams($params);

        $envelope->broadcastToCategory($cat, UserAccess::ACCESS_FULL, $bob->build());

        return $envelope;
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    protected function notifyJoined(UserAccess $userAccess, User $user): Envelope
    {
        try {
            $cat = FolderMapper::getInstance()->findById($userAccess->getServiceIdentifier());
        } catch (Exception $e) {
            return new Envelope();
        }

        if ($userAccess->getUser()->getId() == $user->getId()) {
            $envelope = $this->notifyAdmin(':user joined :groupname', self::TRIGGER_JOINED, $userAccess);
        } else {
            $envelope = $this->notifyAdmin(':effector added :user to :groupname', self::TRIGGER_JOINED, $userAccess, $user);
        }

        $bob = new Builder();
        $bob->setTitle(':moderator has added you to :groupname')
            ->setSeverity(Logger::NOTICE)
            ->setHref('/')
            ->setIcon($this->getUserIcon($user))
            ->setNamespace($this->getSlug())
            ->setParams([
                'moderator' => new UserParam($user),
                'groupname' => new CategoryParam($cat, Param::STACKING_APPEND),
            ])
            ->addTopic("group.{$cat->getId()}." . self::TRIGGER_APPROVED);

        $envelope->targetToUser($userAccess->getUser(), $bob->build());

        return $envelope;
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    public function notifyStaffJoined($userAccess, $user)
    {
        return $this->notifyJoined($userAccess, $user);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    public function notifyStudentJoined($userAccess, $user)
    {
        return $this->notifyJoined($userAccess, $user);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    public function notifyParentJoined($userAccess, $user)
    {
        return $this->notifyJoined($userAccess, $user);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    protected function notifyLeft(UserAccess $userAccess, User $user): Envelope
    {
        try {
            $cat = FolderMapper::getInstance()->findById($userAccess->getServiceIdentifier());
        } catch (Exception $e) {
            return new Envelope();
        }
        if ($userAccess->getUser()->getId() == $user->getId()) {
            $envelope = $this->notifyAdmin(':user left :groupname', self::TRIGGER_LEFT, $userAccess);
        } else {
            $envelope = $this->notifyAdmin(':effector removed :user from :groupname', self::TRIGGER_LEFT, $userAccess, $user);
        }

        $bob = new Builder();

        $bob->setTitle(':moderator has removed you from :groupname')
            ->setSeverity(Logger::NOTICE)
            ->setHref('/')
            ->setIcon($this->getUserIcon($user))
            ->setNamespace($this->getSlug())
            ->setParams([
                'moderator' => new UserParam($user),
                'groupname' => new CategoryParam($cat, Param::STACKING_APPEND),
            ])
            // Adding information of the target user, so that it won't stack the same messages to the different members.
            ->putMeta('member', $userAccess->getUser()->getId())
            ->addTopic("group.{$cat->getId()}." . self::TRIGGER_APPROVED);

        $envelope->targetToUser($userAccess->getUser(), $bob->build());

        return $envelope;
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    public function notifyStaffLeft($userAccess, $user)
    {
        return $this->notifyLeft($userAccess, $user);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    public function notifyStudentLeft($userAccess, $user)
    {
        return $this->notifyLeft($userAccess, $user);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    public function notifyParentLeft($userAccess, $user)
    {
        return $this->notifyLeft($userAccess, $user);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    protected function notifyRequested($userAccess, $user)
    {
        return $this->notifyAdmin(":user asked to join :groupname", self::TRIGGER_REQUESTED, $userAccess);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    public function notifyStaffRequested($userAccess, $user)
    {
        return $this->notifyRequested($userAccess, $user);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    public function notifyStudentRequested($userAccess, $user)
    {
        return $this->notifyRequested($userAccess, $user);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Envelope
     */
    public function notifyParentRequested($userAccess, $user)
    {
        return $this->notifyRequested($userAccess, $user);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @param array|null $diff
     * @return Envelope
     */
    protected function notifyApproved(UserAccess $userAccess, User $user, ?array $diff = null): Envelope
    {
        if ($userAccess->getUser()->getId() == $user->getId()) {
            $envelope = $this->notifyAdmin(':user joined :groupname', self::TRIGGER_JOINED, $userAccess);
        } else {
            $envelope = $this->notifyAdmin(':effector added :user to :groupname', self::TRIGGER_JOINED, $userAccess, $user);
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

        try {
            $cat = FolderMapper::getInstance()->findById($userAccess->getServiceIdentifier());
        } catch (Exception $e) {
            return new Envelope();
        }

        // Customize the approval message
        switch ($userAccess->getAccessLevel()) {
            case UserAccess::ACCESS_DENY:
                $messageText = ':moderator has removed your access to :groupname';
                break;
            case UserAccess::ACCESS_READ:
                $messageText = ':moderator has made you a member of :groupname';
                break;
            case UserAccess::ACCESS_WRITE:
                $messageText = ':moderator has allowed you to create content for :groupname';
                break;
            case UserAccess::ACCESS_FULL:
                $messageText = ':moderator has made you an administrator of :groupname';
                break;
            default:
                // can't handle this: bail out and send nothing
                return $envelope;
        }

        $bob = new Builder();

        $bob->setTitle($messageText)
            ->setSeverity(Logger::NOTICE)
            ->setHref("/homepage/{$cat->getId()}")
            ->setIcon($this->getUserIcon($user))
            ->setNamespace($this->getSlug())
            ->setParams([
                'moderator' => new UserParam($user),
                'groupname' => new CategoryParam($cat)
            ])
            // Adding information of the target user, so that it won't stack the same messages to the different members.
            ->putMeta('member', $userAccess->getUser()->getId())
            ->addTopic("group.{$cat->getId()}.approved")
        ;

        $envelope->targetToUser($userAccess->getUser(), $bob->build());

        return $envelope;
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStaffApproved($userAccess, $user, $diff = null)
    {
        return $this->notifyApproved($userAccess, $user, $diff);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentApproved($userAccess, $user, $diff = null)
    {
        return $this->notifyApproved($userAccess, $user, $diff);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentApproved($userAccess, $user, $diff = null)
    {
        return $this->notifyApproved($userAccess, $user, $diff);
    }
}
