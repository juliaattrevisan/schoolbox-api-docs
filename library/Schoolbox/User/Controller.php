<?php

/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Schoolbox\User;

use Alaress\Web\Controller\Base;
use Schoolbox\External\Normalise\TupleStack;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Role;
use Schoolbox\User;
use User\Logger;
use External_User;

class Controller extends Base
{
    /**
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /**
     * @var External_User
     */
    private $externalHandler;

    /** @var Logger\Mapper */
    private $assumeUserLogMapper;

    public function __construct(
        UserAccessMapper $userAccessMapper,
        External_User $externalHandler,
        Logger\Mapper $assumeUserLogMapper
    ) {
        $this->userAccessMapper    = $userAccessMapper;
        $this->externalHandler     = $externalHandler;
        $this->assumeUserLogMapper = $assumeUserLogMapper;
    }


    /**
     * Function to transfer to another user (impersonate), stores your previous session for later return
     *
     * @api /user/switch/{userId}
     * @param User $user
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function impersonate(User $user)
    {
        $currentSession  = $_SESSION;
        $initialSession  = $_SESSION['initial'] ?? null;
        $previousSession = $_SESSION['previous'] ?? null;
        $sessionHandler  = new \Session();

        if ($sessionHandler->create($user->getUsername())) {
            // Log the session which began impersonation
            $_SESSION['initial'] = ($initialSession ?? $currentSession);

            // Log the current session in the previous
            $_SESSION['previous'] = $currentSession;

            // Who assumed this session? IMO it is always the initial user
            // (the user who first started impersonating)
            /** @var User $initialUser */
            $initialUser = $_SESSION['initial']['user'] ?? $currentSession['user'];

            $logItem = $this->assumeUserLogMapper->createModel()
                ->setUser($initialUser->getUsername())
                ->setAssumed($user->getUsername())
                ->setIp($_SERVER['REMOTE_ADDR']);

            $this->assumeUserLogMapper->save($logItem);
        } else {
            // Recover old session:
            // should do so by creating a new session for previous user, as that
            // is what `recoverSession()` does.
            $_SESSION = $previousSession;
        }
        $this->response = $this->redirectFound('/');
    }


    /**
     * Backwards-compatible alias for recovering to previous session.
     *
     * @api /user/recoverSession
     */
    public function recoverSession(): void
    {
        $this->recoverSessionPrevious();
    }

    /**
     * Return to your previous session, after impersonation.
     *
     * This is the most recent session you impersonated: this could be different
     * to the initial session, if you have impersonated a user from within
     * another impersonation.
     *
     * @api /user/recoverSession/previous
     */
    public function recoverSessionPrevious(): void
    {
        // Preserve initial session, also previous session = current session
        $currentSession = $_SESSION;
        $initialSession = $_SESSION['initial'] ?? null;
        $this->recoverSessionInternal('previous');
        $_SESSION['initial']  = $initialSession;
        $_SESSION['previous'] = $currentSession;
    }

    /**
     * Return to your initial session, after impersonation.
     *
     * This is the session you originally logged in with.
     *
     * @api /user/recoverSession/initial
     */
    public function recoverSessionInitial(): void
    {
        $this->recoverSessionInternal('initial');
    }

    /**
     * Recover the session found in `$key`.
     *
     * @param string $key
     * @return void
     */
    private function recoverSessionInternal(string $key): void
    {
        if (array_key_exists($key, $_SESSION)) {
            $user = $_SESSION[$key]['user'] ?? null;
            if ($user instanceof User) {
                $session = new \Session();
                if (!$session->create($user->getUsername())) {
                    $session->destroy();
                }
            }
        }
        $this->response = $this->redirectFound('/');
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
        $members = $this->userAccessMapper->findUsersInCategory($folder);

        // Loop through members, storing emails for parents, and ids for students (for use later)
        foreach ($members as $member) {
            if ($member->getRole()->getType() == Role::PARENT) {
                $parentEmails[] = $member->getEmail();
            } elseif ($member->getRole()->getType() == Role::STUDENT) {
                $studentIds[] = $member->getExternalId();
            }
        }

        // Use the student ids to grab an array of parent emails and add it to $parentEmails
        $parentEmails = array_merge($parentEmails, $this->externalHandler->getParentEmails($studentIds) ?: []);

        /* $parentEmails as accumulated from two independent sources so dedupe them again
         */
        $parentEmails = (new TupleStack())->normaliseValues($parentEmails);

        // Return a json encoded array :)
        $this->response = $this->responseAsJson($parentEmails);
    }
}
