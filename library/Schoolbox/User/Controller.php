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
     * @var \External_User
     */
    private $externalHandler;


    /**
     * @param UserAccessMapper $userAccessMapper
     * @param \External_User   $externalHandler
     */
    public function __construct(UserAccessMapper $userAccessMapper, \External_User $externalHandler)
    {
        $this->userAccessMapper = $userAccessMapper;
        $this->externalHandler = $externalHandler;
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
     * Known inconsistency: this will return user emails from the SIS for parents of students in the group,
     * and Schoolbox emails for the parents directly in the group
     * This is intended functionality based on our standard of using the SIS when we only know the student, and using Schoolbox when we know the parent
     *
     * @param FolderModel $folder
     */
    public function getParentEmailsByFolder($folder)
    {
        $parentEmails = [];
        $studentIds = [];

        // Get list of members in the folder
        $members = $this->getUserAccessMapper()->findUsersInCategory($folder);

        // Loop through members, storing emails for parents, and ids for students (for use later)
        foreach ($members as $member) {
            if ($member->getRole()->getType() == Role::PARENT) {
                $parentEmails[] = $member->getEmail();
            } elseif ($member->getRole()->getType() == Role::STUDENT) {
                $studentIds[] = $member->getExternalId();
            }
        }

        // Use the student ids to grab an array of parent emails and add it to $parentEmails
        $parentEmails = array_merge($parentEmails, $this->getExternalHandler()->getParentEmails($studentIds) ?: []);

        // Reset the array with no keys (getParentEmails() returns student ids as array keys)
        $parentEmails = array_values($parentEmails);

        // Dedupe emails
        $parentEmails = array_unique($parentEmails);

        // Reset the array with no keys again https://stackoverflow.com/questions/17876414/json-encode-after-array-unique-returning-associative-array
        $parentEmails = array_values($parentEmails);

        // Return a json encoded array :)
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


    /**
     * @return \External_User
     */
    public function getExternalHandler(): \External_User
    {
        return $this->externalHandler;
    }


    /**
     * @param \External_User $externalHandler
     * @return static
     */
    public function setExternalHandler($externalHandler)
    {
        $this->externalHandler = $externalHandler;
        return $this;
    }

}
