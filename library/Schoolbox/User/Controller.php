<?php

/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

namespace Schoolbox\User;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\External\Normalise\TupleStack;
use Schoolbox\Groups\GroupsHelper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Timetable_ColourMapper;
use User\Logger;
use External_User;

class Controller extends Base
{
    private GroupsHelper $groupsHelper;
    protected UserMapper $userMapper;

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
        Logger\Mapper $assumeUserLogMapper,
        UserMapper $userMapper,
        GroupMapper $groupMapper,
        FolderMapper $folderMapper,
        Access $accessHelper,
        Timetable_ColourMapper $timetableColourMapper,
        RerouteOverrideMapper $reRouteOverrideMapper
    ) {
        $this->userAccessMapper    = $userAccessMapper;
        $this->externalHandler     = $externalHandler;
        $this->assumeUserLogMapper = $assumeUserLogMapper;
        $this->userMapper           = $userMapper;
        $this->groupsHelper = new GroupsHelper(
            $groupMapper,
            $userAccessMapper,
            $folderMapper,
            $accessHelper,
            $timetableColourMapper,
            $reRouteOverrideMapper,
        );
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
        $previous = $_SESSION;
        $session = new \Session();

        if ($session->create($user->getUsername())) {
            $_SESSION['previous'] = $previous;

            /** @var User $previousUser */
            $previousUser = $previous['user'];

            $logItem = $this->assumeUserLogMapper->createModel()
                ->setUser($previousUser->getUsername())
                ->setAssumed($user->getUsername())
                ->setIp($_SERVER['REMOTE_ADDR']);

            $this->assumeUserLogMapper->save($logItem);
        } else {
            $_SESSION = $previous; //Recover old session
        }
        $this->getResponse()->redirect->found('/');
    }


    /**
     * Return to your previous session, after impersonation
     *
     * @api /user/recoverSession
     */
    public function recoverSession(): void
    {
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
        $this->getResponse()->content->set($this->buildJsonOutput($parentEmails));
    }

    /**
     * Returns a JSON array of users based on keyword
     *
     * @api /user/search
     */
    public function search()
    {
        $keyword = $this->request->query['keyword'];

        if ($keyword !== null) {
            try {
                /** @var IConnector $db */
                $db = $this->di->get('databaseInternal');
                $userSearchService = new User\Search\Query($db, $this->getConfigLoader());
                $result = $userSearchService->fetchResults($keyword, $this->getUser());
            } catch (\Exception $exception) {
                // noop
                $result = [
                    'message' => $exception->getMessage()
                ];
            }
        }

        $this->getResponse()->content->set(
            $this->buildJsonOutput(
                $result
            )
        );
    }

    /**
     * @api /user/{userId}/groupMembership
     * @param string $userId
     */
    public function apiMembership(string $userId)
    {
        //check perms, if none exit
        if (!$this->hasApiAccess()) {
            return;
        }
        //check for invalid / deleted users?
        try {
            /* Check that the selected user exists in schoolbox
             */
            $user = $this->userMapper->findById($userId);
        } catch (MapperException $e) {
            /* If they weren't found in either, exit
             */
            $problem = (new ApiProblem('User does not exist'))
                ->setStatus(404)
                ->asArray();
            $this->getResponse()->status->setCode(404);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the user is part of the group.
        }

        $users_groups = $this->groupsHelper->getAllGroups($user);

        $this->getResponse()->content->set(
            $this->buildJsonOutput(
                $users_groups
            )
        );
    }

    /**
     * check requesting user has correct perms, and return API error if not
     */
    private function hasApiAccess(): bool
    {
        if ($this->getUser()->isSuperuser()) {
            return true;
        } else {
            $problem = (new ApiProblem('User does not have permissions to view the requested user'))
                ->setStatus(403)
                ->asArray();
            $this->getResponse()->status->setCode(403);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return false; //exit early the user is part of the group.
        }
    }
}
