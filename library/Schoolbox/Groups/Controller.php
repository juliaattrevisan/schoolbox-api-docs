<?php

/**
 *
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Groups;

use Alaress\Color\Color;
use Alaress\Color\ColorFactory;
use Alaress\Web\Controller\Base as BaseController;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Config;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Message\SourceMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Realtime\Message\RerouteOverride;
use Schoolbox\RoleMapper;
use Timetable_ColourMapper;

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
        $groupMapper    = GroupMapper::getInstance();
        $accessMapper   = UserAccessMapper::getInstance();
        $folderMapper   = FolderMapper::getInstance();
        $accessHelper   = Access::getInstance();

        /** @var \Schoolbox\User $user */
        $user = $_SESSION["user"];

        //colours for groups with no colours
        /* Get Timetable colours to use as random colours for null set folders */
        $colourList = Timetable_ColourMapper::getInstance()->findAll();
        $colourIndex = 0;
        $colourFactory = new ColorFactory();
        $colourCache   = new GenericCollection(Color::class);

        //get list of all possible groups
        $groups = $groupMapper->findByColumn('type', [Group::FREE_GROUP, Group::MODERATED_GROUP]);
        //get list of all groups, will filter out the private and not member
        $groups = $groupMapper->findAll();

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
            $folder   = $folderMapper->findById($folderId);
//            $folder = $folderMapper->hydrateCollection($folder);

            if ($group->isPrivate() && !$userPerms[$folderId]) {
                // private group and the user is not a member, don't show.
                continue;
            }

            //will follow by default, check that user is actually in that group when checking if they are following
            $follow = (!(in_array($folderId, $muted['unfollow']) || in_array($folderId, $muted['disabled'])) && $userPerms[$folderId]);

            //favourite
            $isFavourite = in_array($folderId, $favourites);

            //nullable fields cause something have no defaults...
            $imageHash  = null;
            if ($folder->getCoverImage()) {
                $imageHash = $folder->getCoverImage()->getHash();
            }
            if (!$folder->getColour()) {
                /* Apply colour from list if no colour exists */
                $hexColour = $colourList[$colourIndex]->getHexColour();
                if (!$colourCache->offsetExists($hexColour)) {
                    $colourCache->setOffset($hexColour, $colourFactory->build($hexColour));
                }
                $folder->setColour($colourCache->getOffset($hexColour));
                $colourIndex = ($colourIndex + 1) % $colourList->count();
            }
            //get access
            $access = $accessHelper->getFolderAccess($this->getUser(), $folder->getId()) ?: null;
            try {
                $modifyLink = $this->getRouteIfAuthorized(
                    'folder.getFolderModify',
                    ['id' => $folder->getId(), 'folder' => $folder]
                );
            } catch (RouteNotFound $e) {
                $modifyLink = false;
            }
            $tmp                    = [
                'id'                => $folderId,
                'name'              => $group->getFolder()->getName(),
                'enrolmentStatus'   => $this->getEnrollmentText($userPerms[$folderId]),
                'colour'            => $folder->getColour()->getAsHex(),
                'coverImageHash'    => $imageHash,
                'isNotificationsOn' => $follow,
                'isFavourite'       => $isFavourite,
                'type'              => $this->getGroupType($group->getType()),
                'isAccessAdmin'     => ($access === UserAccess::ACCESS_FULL),
                'isAccessWrite'     => ($access === UserAccess::ACCESS_WRITE),
                'isAccessRead'      => ($access === UserAccess::ACCESS_READ),
                'isPrivate'         => $group->isPrivate(),
                'modifyLink'        => $modifyLink,
            ];
            $possibleGroups[] = $tmp;
        }

        //send to frontend
        try {
            $userAccess[$folder->getId()] = Access::getInstance()->getFolderAccess($this->getUser(), $folder->getId());
        } catch (MapperException $e) {
            $userAccess[$folder->getId()] = null;
        }

        //sort the groups
        usort($possibleGroups, function ($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@groups/groups.twig', [
                'groupList'  => $possibleGroups,
                'userAccess' => $userAccess,
                'endpoint'   => $this->getRouteIfAuthorized('groups.newGroups'),
            ])
        );
    }

    /**
     * edit's a users enrolmentStatus into a group
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
//            || !isset($post['isNotificationsOn'])
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
                   'group'              => $post['group'],
                   'user'               => $user->getId(),
                   'isFavourite'        => $userGroup->getFavourite(),
                   'isNotificationsOn'  => 0,
                   'enrolmentStatus'    => $this->getEnrollmentText($userGroup),
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
                'user'              => $user->getId(),
                'group'             => $post['group'],
                'action'            => 'delete',
                'enrolmentStatus'   => 'notEnrolled',
            ]));
        }

        //must be edit functions
        if ($post['action'] === 'edit') {
            if (isset($post['isFavourite'])) {
                //make/remove favourite
                $userGroup->setFavourite((bool)$post['isFavourite']);
            }
            //save group object
            $accessMapper->save($userGroup);

            //subscribe/remove notifications
            $followingList = [];
            if (isset($post['isNotificationsOn'])) {
                //build notifications
                $realtimeConfig = new Config(RoleMapper::getInstance(), SourceMapper::getInstance(), RoutePermissionMapper::getInstance(), RerouteOverrideMapper::getInstance());
                $following      = $post['isNotificationsOn'] ? 1 : 0;
                $followingList  = $realtimeConfig->persistFollow($user, [$post['group'] => $following]);
            } else {
                $followingList = $this->getFollows();
            }

            return $this->getResponse()->content->set(
                $this->buildJsonOutput([
                    'group'             => $post['group'],
                    'user'              => $user->getId(),
                    'isFavourite'       => $userGroup->getFavourite(),
                    'isNotificationsOn' => in_array($post['group'], $followingList),
                    'access'            => $this->getEnrollmentText($userGroup),
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

    /**
     * get the text representation of the users enrolment in a group
     * @param null|UserAccess $userGroup
     * @return string
     */
    protected function getEnrollmentText($userGroup): string
    {
        if ($userGroup === null) {
            return 'notEnrolled';
        }
        //get enrolmentStatus, could be enrolled, can join, or pending
        $enrollment = $userGroup->getAccessLevel() ? 'enrolled' : 'notEnrolled';
        if (($userGroup->getAccessLevel() == UserAccess::ACCESS_DENY)) {
            $enrollment = 'pending';
        }
        return $enrollment;
    }

    /**
     * get the text representaion of the group type (public, private, moderated)
     */
    protected function getGroupType(int $type): string
    {
        switch ($type) {
            case Group::MODERATED_GROUP:
                return 'moderated';
            case Group::FREE_GROUP:
                return 'free';
            case Group::PRIVATE_GROUP:
                return 'private';
            default:
                return 'error';
        }
    }
}
