<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

namespace Schoolbox\User;

use Alaress\Web\Controller\Base;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Role;
use \Schoolbox\User;


class Controller extends Base
{
    /**
     * @var UserAccessMapper
     */
    protected $userAccessMapper;


    /**
     * @param UserAccessMapper $userAccessMapper
     */
    public function __construct(UserAccessMapper $userAccessMapper)
    {
        $this->setUserAccessMapper($userAccessMapper);
    }


    /**
     * Function to transfer to another user (impersonate), stores your previous session for later return
     *
     * @param User $user
     */
    public function impersonate(User $user) {
        $previous = $_SESSION;
        $session = new \Session();

        if ($session->create($user->getUsername())) {
            $_SESSION['previous'] = $previous;

            $logger = new \User_Logger_AssumeUser($previous['user'], $user);
            $logger->notify();
        }
        else {
            $_SESSION = $previous; //Recover old session
        }
        $this->getResponse()->redirect->found('/');
    }

    /**
     * Return to your previous session, after imper
     */
    public function recoverSession() {
        $_SESSION = $_SESSION['previous'];
        $this->getResponse()->redirect->found('/');
    }


    /**
     * Return a json array of parent emails from a folder
     * This will include parents directly in the group, and parents of students in the group
     *
     * @param FolderModel $folder
     */
    public function getParentEmailsByFolder($folder)
    {
        $parentEmails = [];
        $members = $this->getUserAccessMapper()->findUsersInCategory($folder);

        foreach ($members as $member) {
            if ($member->getRole()->getType() == Role::PARENT) {
                $parentEmails[] = $member->getEmail();
            } elseif ($member->getRole()->getType() == Role::STUDENT) {
                $parents = $member->loadParents()->getParents();
                foreach ($parents as $parent) {
                    if (strlen($parent->getEmail())) {
                        $parentEmails[] = $parent->getEmail();
                    }
                }
            }
        }

        $parentEmails = array_unique($parentEmails);

        $this->getResponse()->content->set($this->buildJsonOutput($parentEmails));
    }


    /**
     * @return UserAccessMapper
     */
    protected function getUserAccessMapper(): UserAccessMapper
    {
        return $this->userAccessMapper;
    }


    /**
     * @param UserAccessMapper $userAccessMapper
     * @return static
     */
    protected function setUserAccessMapper($userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
        return $this;
    }

} 