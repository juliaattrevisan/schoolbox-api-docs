<?php

/**
 *
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Groups;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Realtime\Message\RerouteOverride;

use function in_array;

class Controller extends BaseController
{
    /**
     * Displays all available new groups, and allows the user to join / leave groups
     *
     * @api GET /resource/newgroups
     */
    public function newGroups(): void
    {
        //grab list of all possibly groups the user can choose to be part of, and list them.
        //displaying included information (notification / favorite)

        //helpers
        $groupMapper      = GroupMapper::getInstance();
        $accessMapper     = UserAccessMapper::getInstance();
        $userAccessMapper = UserAccessMapper::getInstance();

        /** @var \Schoolbox\User $user */
        $user = $_SESSION["user"];

        //get list of all possible groups
        $groups = $groupMapper->findByColumn('type', [Group::FREE_GROUP, Group::MODERATED_GROUP]);

        //check if user is currently member
        // Get the perms for user - including DENY (semantically PENDING) access
        /** @var GenericCollection|UserAccess[] $userPerms */
        $userPerms = $accessMapper->findByColumnList([
                                                         'uid'     => $user->getId(),
                                                         'service' => UserAccess::SERVICE_HOMEPAGE,
                                                     ])->indexBy('serviceIdentifier');

        // Fetch the logged in user's write access groups to exclude from the lists.
        //user who has write access, is already a member of the group?
        $userWriteGroupIds = [];
        $userGroups        = $accessMapper->findAllForUser($user);
        $userWriteGroups   = $userGroups->filter(function (UserAccess $element) {
            return $element->getAccessLevel() >= UserAccess::ACCESS_WRITE;
        });

        foreach ($userWriteGroups as $userGroup) {
            $userWriteGroupIds[] = $userGroup->getServiceIdentifier();
        }

        //check if notifications or favourites
        $favourites = $userAccessMapper->getFavourites($user);

        //build notifications
        $followmentHelp = new \Schoolbox\Realtime\Controller();
        $service        = UserAccess::SERVICE_HOMEPAGE;
        $muted          = [
            'unfollow' => $this->getUser()->getId(),
            'disabled' => 0,
        ];

        $routeKeyRegex = "/service\\.$service\\.([0-9]+)/";
        foreach ($muted as $k => $id) {
            $muted[$k] = $followmentHelp->getRerouteOverrideMapper()
                                        ->getMatchingRoutes("service.$service", RoutePermission::MUTE, $id)
                                        ->map(
                                            function ($override) use ($routeKeyRegex) {
                                                /** @var RerouteOverride $override */
                                                preg_match($routeKeyRegex, $override->getRouteKey(), $matches);
                                                return is_array($matches) ? $matches[1] : null;
                                            },
                                            false
                                        )
                                        ->getAll();
        }

        // build output array
        $userAccess     = [];
        $possibleGroups = [];
        foreach ($groups as $group) {
            $folderId = $group->getFolder()->getId();
            $folder   = $group->getFolder();
            if (!in_array($folderId, $userWriteGroupIds)) {
                //will follow by default, check that user is actually in that group when checking if they are following
                $follow = (!(in_array($folderId, $muted['unfollow']) || in_array($folderId, $muted['disabled'])) && $userPerms[$folderId]);

                //get enrolment, could be enrolled, can join, or pending
                $enrollment = $userPerms[$folderId] ? 'enrolled' : 'not enrolled';
                if ($userPerms[$folderId] && ($userPerms[$folderId]->getAccessLevel() == UserAccess::ACCESS_DENY)) {
                    $enrollment = 'pending';
                }

                //favourite
                $favourite = in_array($folderId, $favourites);

                $tmp              = [
                    'id'         => $folderId,
                    'name'       => $group->getFolder()->getName(),
                    'enrolment'  => $enrollment,
                    'colour'     => $folder->getColour(),
                    'coverImage' => $folder->getCoverImage(),
                    'follow'     => $follow,
                    'favourite'  => $favourite,
                ];
                $possibleGroups[] = $tmp;
                try {
                    $userAccess[$folder->getId()] = Access::getInstance()->getFolderAccess($this->getUser(), $folder->getId());
                } catch (MapperException $e) {
                    $userAccess[$folder->getId()] = null;
                }
            }
        }

        //send to frontend

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@groups/groups.twig', [
                'groupList'  => $possibleGroups,
                'userAccess' => $userAccess,
            ])
        );
    }

    /**
     * edit's a users enrolment into a group
     *
     * @api POST /mygroup/{groupId:\d+}/edit
     */
    public function postMyGroup(int $groupId)
    {
        //get group object

        //add / remove user from group

        //make/remove favourite

        //subscribe/remove notifications

        return $groupId;
    }
}
