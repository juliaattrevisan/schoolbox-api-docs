<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Permission\Access;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Rerouter\RerouterCommander;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class UserAccessNotifier extends AbstractModuleNotifier
{

    const TRIGGER_JOINED    = 'joined';
    const TRIGGER_LEFT      = 'left';
    const TRIGGER_REQUESTED = 'requested';
    const TRIGGER_APPROVED  = 'approved';


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


    /**
     * @param string $label
     * @return array
     */
    public function retrieveCallbacks($label)
    {
        $callbacks = parent::retrieveCallbacks($label);
        // append system one too
        $method = 'notifySystem' . ucfirst($label);
        if (method_exists($this, $method)) {
            $callbacks[] = array($this, $method);
        }
        return $callbacks;
    }


    /* ASYNC METHODS */

    /**
     * @param string     $body
     * @param string     $label
     * @param UserAccess $userAccess
     * @param User       $effector
     * @return Messenger
     */
    protected function notifyAdmin($body, $label, $userAccess, $effector = null)
    {
        $envelope = new Envelope();

        $bob = new Builder();

        /** @var Category $cat */
        $cat = CategoryMapper::getInstance()->findById($userAccess->getServiceIdentifier());

        /** @var User $user */
        $user = UserMapper::getInstance()->findById($userAccess->getUser()->getId());

        /** @var ParameterInterface[] $params */
        $params = array(
            'user'      => new UserParam($user),
            'groupname' => new CategoryParam($cat)
        );

        if ($effector) {
            $params['effector'] = new UserParam($effector, ParameterInterface::STACKING_REPLACE);
        }

        $bob->setTitle($body)
            ->setHref("/group/modify/" . $cat->getId())
            ->setIcon("/portrait.php?size={$this->iconSize}&userId=" . $user->getExternalId())
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
     * @return Messenger
     */
    public function notifyStaffJoined($userAccess, $user)
    {
        if ($userAccess->getUser()->getId() == $user->getId()) {
            return $this->notifyAdmin(':user joined :groupname', self::TRIGGER_JOINED, $userAccess);
        } else {
            return $this->notifyAdmin(':effector added :user to :groupname', self::TRIGGER_JOINED, $userAccess, $user);
        }
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Messenger
     */
    public function notifyStaffLeft($userAccess, $user)
    {
        if ($userAccess->getUser()->getId() == $user->getId()) {
            return $this->notifyAdmin(':user left :groupname', self::TRIGGER_LEFT, $userAccess);
        } else {
            return $this->notifyAdmin(':effector removed :user from :groupname', self::TRIGGER_LEFT, $userAccess, $user);
        }
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @return Messenger
     */
    public function notifyStaffRequested($userAccess, $user)
    {
        return $this->notifyAdmin(":user asked to join :groupname", self::TRIGGER_REQUESTED, $userAccess);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @param array      $diff
     * @return Messenger
     */
    protected function notifyApproved($userAccess, $user, $diff = null)
    {
        $msgr = new Messenger();


        // TODO uncomment these bits of the method to make it more informative once the Mapper can handle composite IDs
//		// no diff in access level, this isn't something we're interested in
//		if(!isset($diff['accessLevel'])) {
//			return $msgr;
//		}
//
//		// new is [0], old is [1]
//		$newAccess = $diff['accessLevel'][0];
//		$oldAccess = $diff['accessLevel'][1];

        /** @var Category $cat */
        $cat = CategoryMapper::getInstance()
                             ->findById($userAccess->getServiceIdentifier());

        // Customize the approval message
        $messageText = '';
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
        }
        $msg = new Message(
            $messageText,
            "/portrait.php?size={$this->iconSize}&userId=" . $user->getExternalId(),
            "/homepage/" . $cat->getId()
        );

        $msgr->targetToUser($userAccess->getUser()->getId(), $msg, "group.{$cat->getId()}.approved", Logger::NOTICE,
            array(
                'moderator' => array($user->formatName(), '/search/user/' . $user->getId()),
                'groupname' => array($cat->getName(), '/homepage/' . $cat->getId())
            )
        );

        return $msgr;
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @param array      $diff
     * @return Messenger
     */
    public function notifyStaffApproved($userAccess, $user, $diff = null)
    {
        return $this->notifyApproved($userAccess, $user, $diff);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @param array      $diff
     * @return Messenger
     */
    public function notifyStudentApproved($userAccess, $user, $diff = null)
    {
        return $this->notifyApproved($userAccess, $user, $diff);
    }


    /**
     * @param UserAccess $userAccess
     * @param User       $user
     * @param array      $diff
     * @return Messenger
     */
    public function notifyParentApproved($userAccess, $user, $diff = null)
    {
        return $this->notifyApproved($userAccess, $user, $diff);
    }


    /**
     * Tells the scheduler commander that the user in $userAccess needs to be
     * rebound.
     *
     * @param UserAccess $userAccess
     */
    protected function adviseRerouterCommander($userAccess)
    {
        $cmdr = new RerouterCommander(UserMapper::getInstance());
        $cmdr->adviseBindUser($userAccess->getUser()->getId());
    }


    /**
     * System notifiers intentionally don't return a Messenger - instead, they
     * tell the scheduler commander to rebind the user whose access was changed.
     *
     * @param UserAccess $userAccess
     * @param User       $user
     * @param array      $diff
     */
    public function notifySystemJoined($userAccess, $user, $diff = null)
    {
        $this->adviseRerouterCommander($userAccess);
    }


    /**
     * System notifiers intentionally don't return a Messenger - instead, they
     * tell the scheduler commander to rebind the user whose access was changed.
     *
     * @param UserAccess $userAccess
     * @param User       $user
     * @param array      $diff
     */
    public function notifySystemLeft($userAccess, $user, $diff = null)
    {
        $this->adviseRerouterCommander($userAccess);
    }


    /**
     * System notifiers intentionally don't return a Messenger - instead, they
     * tell the scheduler commander to rebind the user whose access was changed.
     *
     * @param UserAccess $userAccess
     * @param User       $user
     * @param array      $diff
     */
    public function notifySystemApproved($userAccess, $user, $diff = null)
    {
        $this->adviseRerouterCommander($userAccess);
    }


    /**
     * System notifiers intentionally don't return a Messenger - instead, they
     * tell the scheduler commander to rebind the user whose access was changed.
     *
     * @param UserAccess $userAccess
     * @param User       $user
     * @param array      $diff
     */
    public function notifySystemRejected($userAccess, $user, $diff = null)
    {
        $this->adviseRerouterCommander($userAccess);
    }

}
