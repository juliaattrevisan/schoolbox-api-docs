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
use Schoolbox\Realtime\Config;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Message\SourceMapper;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Realtime\Message\RerouteOverride;
use Schoolbox\RoleMapper;

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

        /** @var \Schoolbox\User $user */
        $user = $_SESSION["user"];

        //get list of all possible groups
        $groups = $groupMapper->findByColumn('type', [Group::FREE_GROUP, Group::MODERATED_GROUP]);
        //get list of all groups, will filter out the private and not member
//        $groups = $groupMapper->findAll();

        //check if user is currently member
        // Get the perms for user - including DENY (semantically PENDING) access
        /** @var GenericCollection|UserAccess[] $userPerms */
        $userPerms = $accessMapper->findByColumnList([
                                                         'uid'     => $user->getId(),
                                                         'service' => UserAccess::SERVICE_HOMEPAGE,
                                                     ])->indexBy('serviceIdentifier');

        //check if notifications or favourites
        $favourites = $accessMapper->getFavourites($user);

        //build notifications
        $muted = $this->getFollows();

        // build output array
        $userAccess     = [];
        $possibleGroups = [];
        /* @var Group $group */
        foreach ($groups as $group) {
            $folderId = $group->getFolder()->getId();
            $folder   = $group->getFolder();

            if ($group->isPrivate() && !$userPerms[$folderId]) {
                // private group and the user is not a member, don't show.
                continue;
            }

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
                'coverImageHash' => $folder->getCoverImage()->getHash(),
                'follow'     => $follow,
                'favourite'  => $favourite,
                'type'       => $group->getType(),
            ];
            $possibleGroups[] = $tmp;
            try {
                $userAccess[$folder->getId()] = Access::getInstance()->getFolderAccess($this->getUser(), $folder->getId());
            } catch (MapperException $e) {
                $userAccess[$folder->getId()] = null;
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
     * @api POST /my
     */
    public function postMyGroup()
    {
        /**
         * post should contain:
         * group id
         * action (add, remove, edit)
         * subscribe/notifications (true, false)
         * favourite (true, false)
         *
         * need to check for the existence of the user/group relationship then modify it, or create a new entry
         */
        //check post to confirm everything is there
        $post = $this->getRequest()->post->getArrayCopy();

        if (
            !isset($post['group'])
//            || !isset($post['action'])
//            || !isset($post['follow'])
//            || !isset($post['favourite'])
        ) {
            return $this->getResponse()->content->set($this->buildJsonOutput(['error' => 'Missing attributes']));
        }

        //helpers
        $accessMapper   = UserAccessMapper::getInstance();
        $groupMapper    = GroupMapper::getInstance();

        /** @var \Schoolbox\User $user */
        $user = $_SESSION["user"];

        /* @var Group $group */
        $group = $groupMapper->findById($post['group']);

        //get group object
        $userGroup = false;

        try {
            $userGroup = $accessMapper->findOneByColumnList([
                'uid' => $user->getId(),
                'sid' => $post['group'],
            ]);
        } catch (MapperException\NotFound $e) {
            //don't place the user into the group unless we are specifically requested to add the user.
            if ($post['action'] !== 'add') {
                return $this->getResponse()->content->set($this->buildJsonOutput(['error' => 'User is not a member of this group']));
            }
            $userGroup = new UserAccess();
            $userGroup->setServiceIdentifier($post['group'])
                ->setUser($user)
                ->setService(UserAccess::SERVICE_HOMEPAGE)
                ->setAccessLevel(UserAccess::ACCESS_AWAITING_AUTHORISATION)
                ->setFavourite(false);
            if ($group->isFree()) {
                $userGroup->setAccessLevel(UserAccess::ACCESS_READ);
            }
            //save group object
            $accessMapper->save($userGroup);
            return $this->getResponse()->content->set(
                $this->buildJsonOutput([
                   'group'     => $post['group'],
                   'user'      => $user->getId(),
                   'favourite' => $userGroup->getFavourite(),
                   'following' => 0,
                   'access'    => $userGroup->getService(),
                ])
            );
        }
        //add / remove user from group
        if ('delete' === $post['action']) {
            if ($group->isPrivate()) {
                return $this->getResponse()->content->set($this->buildJsonOutput(['error' => 'No Permission to leave group']));
            }
            $accessMapper->delete($userGroup);
            return $this->getResponse()->content->set($this->buildJsonOutput([
                'group'     => $post['group'],
                'user'      => $user->getId(),
                'action'    => 'delete'
            ]));
        }

        //must be edit functions
        if ($post['action'] === 'edit') {
            if (isset($post['favourite'])) {
                //make/remove favourite
                $userGroup->setFavourite((bool)$post['favourite']);
            }
            //save group object
            $accessMapper->save($userGroup);

            //subscribe/remove notifications
            $followingList = [];
            if (isset($post['follow'])) {
                //build notifications
                $realtimeConfig = new Config(RoleMapper::getInstance(), SourceMapper::getInstance(), RoutePermissionMapper::getInstance(), RerouteOverrideMapper::getInstance());
                $following      = $post['follow'] ? 1 : 0;
                $followingList  = $realtimeConfig->persistFollow($user, [$post['group'] => $following]);
            } else {
                $followingList = $this->getFollows();
            }

            return $this->getResponse()->content->set(
                $this->buildJsonOutput([
                                           'group'     => $post['group'],
                                           'user'      => $user->getId(),
                                           'favourite' => $userGroup->getFavourite(),
                                           'following' => in_array($post['group'], $followingList),
                                           'access'    => $userGroup->getService(),
                                       ])
            );
        }
        return $this->getResponse()->content->set($this->buildJsonOutput(['error' => 'empty state, something went wrong.']));
    }
    protected function getFollows()
    {
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
        return $muted;
    }
}
