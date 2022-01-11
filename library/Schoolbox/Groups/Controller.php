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
use Crell\ApiProblem\ApiProblem;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Realtime\Message\RerouteOverride;
use Schoolbox\User;
use Timetable_ColourMapper;

class Controller extends BaseController
{

    //sting const's for enrolment text
    const ENROLLED    = 'enrolled';
    const NOTENROLLED = 'notEnrolled';
    const PENDING     = 'pending';

    private GroupMapper $groupMapper;
    private UserAccessMapper $userAccessMapper;
    private FolderMapper $folderMapper;
    private Access $accessHelper;
    private Timetable_ColourMapper $timetable_ColourMapper;
    private RerouteOverrideMapper $reRouteOverrideMapper;

    /**
     * constructor for dependency injection
     */
    public function __construct($groupMapper, $userAccessMapper, $folderMapper, $accessHelper, $timetable_ColourMapper, $reRouteOverrideMapper)
    {
        $this->groupMapper            = $groupMapper;
        $this->userAccessMapper       = $userAccessMapper;
        $this->folderMapper           = $folderMapper;
        $this->accessHelper           = $accessHelper;
        $this->timetable_ColourMapper = $timetable_ColourMapper;
        $this->reRouteOverrideMapper  = $reRouteOverrideMapper;
    }

    /**
     * Displays all available new groups, and allows the user to join / leave groups
     *
     * @api GET /resource/newgroups
     */
    public function getNewGroups(): void
    {
        //grab list of all possibly groups the user can choose to be part of, and list them.
        //displaying included information (notification / favorite)

        $user = $this->getUser();

        //colours for groups with no colours
        /* Get Timetable colours to use as random colours for null set folders */
        $colourList    = $this->timetable_ColourMapper->findAll();
        $colourIndex   = 0;
        $colourFactory = new ColorFactory();
        $colourCache   = new GenericCollection(Color::class);

        //check if user is currently member
        // Get the groups the user is currently a member, their enrolment type and favourite status
        /** @var GenericCollection|UserAccess[] $userPerms */
        $userPerms = $this->userAccessMapper->findByColumnList([
                                                                   'uid'     => $user->getId(),
                                                                   'service' => UserAccess::SERVICE_HOMEPAGE,
                                                               ])->indexBy('serviceIdentifier');

        //get list of all possible public groups
        $groups   = $this->groupMapper->findByColumnList([
                                                             'type' => [Group::FREE_GROUP, Group::MODERATED_GROUP],
                                                         ]);
        //ids of all groups, both available to the user, and those they are enrolled in
        $groupIds = array_unique(array_merge($userPerms->keys(), $groups->keys()));

        //build notifications
        $notificationsList = $this->getNotifications($user);

        //build the folder list object with hydration
        $folders = $this->folderMapper->findByColumnList(['id' => $groupIds]);

        // build output array
        $possibleGroups = [];
        /* @var UserAccess|Group $group */
        foreach ($folders as $folder) {
            //favourite
            $isFavourite = false;
            if ($userPerms[$folder->getId()]) {
                $isFavourite = $userPerms[$folder->getId()]->getFavourite();
            }

            //nullable fields cause something to have no defaults...
            $imageHash = null;
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
                //add colour to folder, and continue the cycle of colour infices
                $colourIndex = ($colourIndex + 1) % $colourList->count();
            }

            //get group edit access, for the edit link
            $access = $this->accessHelper->getFolderAccess($user, $folder->getId()) ?: UserAccess::ACCESS_DENY;
            if (UserAccess::ACCESS_WRITE <= $access) {
                $modifyLink = $this->getRouter()->generate(
                    'folder.getFolderModify',
                    ['id' => $folder->getId(), 'folder' => $folder]
                );
            } else {
                $modifyLink = false;
            }
            $possibleGroups[] = [
                'id'                => $folder->getId(),
                'name'              => $folder->getName(),
                'enrolmentStatus'   => $this->getEnrollmentText($userPerms[$folder->getId()]),
                'colour'            => $folder->getColour()->getAsHex(),
                'coverImageHash'    => $imageHash,
                'isNotificationsOn' => $notificationsList[$folder->getId()]['follow'] ?: false,
                'isForceFollow'     => $notificationsList[$folder->getId()]['force'] ?: false,
                'isFavourite'       => $isFavourite,
                'type'              => $folder->getGroup()->getTypeString(),
                'isAccessAdmin'     => ($access === UserAccess::ACCESS_FULL),
                'isAccessWrite'     => ($access === UserAccess::ACCESS_WRITE),
                'isAccessRead'      => ($access === UserAccess::ACCESS_READ),
                'isPrivate'         => $folder->getGroup()->isPrivate(),
                'modifyLink'        => $modifyLink,
            ];
        }

        //sort the groups
        usort($possibleGroups, function ($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@groups/groups.twig', [
                'groupList' => $possibleGroups,
                'endpointAdd'  => $this->getRouter()->generate('groups.postMyGroupAdd'),
                'endpointDelete'  => $this->getRouter()->generate('groups.postMyGroupDelete'),
                'endpointNotify'  => $this->getRouter()->generate('groups.postMyGroupNotify'),
                'endpointFavourite'  => $this->getRouter()->generate('groups.postMyGroupFavourite'),
            ])
        );
    }

    /**
     * @api POST /groups/add
     */
    public function postMyGroupAdd(): void
    {
        //grab the post / user and group objects to make things easier
        $post  = $this->getRequest()->post->getArrayCopy();
        $user  = $this->getUser();
        $group = $this->getGroup($post['group']);
        if (null === $group) {
            return; //exit early the group doesn't exist
        }

        try {
            $userGroup = $this->userAccessMapper->findOneByColumnList([
                                                                          'uid' => $user->getId(),
                                                                          'sid' => $post['group'],
                                                                      ]);
            //user is already part of the group, error out.
            $problem = (new ApiProblem('User is already part of the group'))
                ->setStatus(400)
                ->asArray();
            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return; //exit early the user is part of the group.
        } catch (MapperException\NotFound $e) {
            //add the user to the group
            $userGroup = $this->userAccessMapper->createModel();
            $userGroup->setServiceIdentifier($post['group'])
                      ->setUser($user)
                      ->setService(UserAccess::SERVICE_HOMEPAGE)
                      ->setAccessLevel(UserAccess::ACCESS_AWAITING_AUTHORISATION)
                      ->setFavourite(false); //default to no favourite groups.
            if ($group->isFree()) {
                $userGroup->setAccessLevel(UserAccess::ACCESS_READ);
            }
            //save group object
            $this->userAccessMapper->save($userGroup);

            $this->getResponse()->content->set(
                $this->buildJsonOutput([
                                           'group'             => $post['group'],
                                           'user'              => $user->getId(),
                                           'isFavourite'       => $userGroup->getFavourite(),
                                           'isNotificationsOn' => true,
                                           //notifications default to on, you have to opt out in a separate request
                                           'enrolmentStatus'   => $this->getEnrollmentText($userGroup),
                                       ])
            );
        }
    }

    /**
     * remove the user from the group
     * @api POST /groups/delete
     */
    public function postMyGroupDelete(): void
    {
        //grab the post / user and group objects to make things easier
        $post  = $this->getRequest()->post->getArrayCopy();
        $user  = $this->getUser();
        $group = $this->getGroup($post['group']);
        if (null === $group) {
            return; //exit early the group doesn't exist
        }
        $userGroup = $this->getUserGroup($user, $post['group']);
        if (null === $userGroup) {
            return; //exit early the user is not part of this group
        }

        if ($group->isPrivate()) {
            $problem = (new ApiProblem('No Permission to leave group'))
                ->setStatus(400)
                ->asArray();
            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return;
        }
        //remove the user group / favourite
        $this->userAccessMapper->delete($userGroup);
        $this->getResponse()->content->set(
            $this->buildJsonOutput([
                                       'user'            => $user->getId(),
                                       'group'           => $post['group'],
                                       'action'          => 'delete',
                                       'enrolmentStatus' => self::NOTENROLLED,
                                   ])
        );
    }

    /**
     * edit the user notification preferences
     * @api POST /groups/notify
     */
    public function postMyGroupNotify(): void
    {
        //grab the post / user and group objects to make things easier
        $post  = $this->getRequest()->post->getArrayCopy();
        $user  = $this->getUser();
        $group = $this->getGroup($post['group']);
        if (null === $group) {
            return; //exit early the group doesn't exist
        }
        $userGroup = $this->getUserGroup($user, $post['group']);
        if (null === $userGroup) {
            return;  //exit early the user is not part of this group
        }

        //subscribe/remove notifications
        //check if the user can actually unsubscribe from notifications
        if ($group->getForceFollow()) {
            $problem = (new ApiProblem('No Permission to unfollow group'))
                ->setStatus(400)
                ->asArray();
            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return;
        }

        //update the notification to what the user sets.
        $notificationsList = $this->setNotification($group->getFolder()->getId(), $user, (bool)$post['isNotificationsOn']);

        $this->getResponse()->content->set(
            $this->buildJsonOutput([
                                       'group'             => $post['group'],
                                       'user'              => $user->getId(),
                                       'isNotificationsOn' => $notificationsList[$group->getFolder()->getId()]['follow'] ?: false,
                                       'isForceFollow'     => $notificationsList[$group->getFolder()->getId()]['force'] ?: false,
                                       'access'            => $this->getEnrollmentText($userGroup),
                                   ])
        );
    }

    /**
     * edit group favourite
     */
    public function postMyGroupFavourite(): void
    {
        //grab the post / user and group objects to make things easier
        $post  = $this->getRequest()->post->getArrayCopy();
        $user  = $this->getUser();
        $group = $this->getGroup($post['group']);
        if (null === $group) {
            return; //exit early the group doesn't exist
        }
        $userGroup = $this->getUserGroup($user, $post['group']);
        if (null === $userGroup) {
            return;  //exit early the user is not part of this group
        }

        //make/remove favourite
        $userGroup->setFavourite((bool)$post['isFavourite']);

        //save group object
        $this->userAccessMapper->save($userGroup);

        $this->getResponse()->content->set(
            $this->buildJsonOutput([
                                       'group'             => $post['group'],
                                       'user'              => $user->getId(),
                                       'isFavourite'       => $userGroup->getFavourite(),
                                       'access'            => $this->getEnrollmentText($userGroup),
                                   ])
        );
    }

    /**
     * grabs all groups the user is part of and are following\
     * originally from `calculateFollowment` in library/Schoolbox/Realtime/Controller.php
     * I am now the only place using this, and cleaned it up.
     *
     * @param \Schoolbox\User $user
     * @return array
     */
    protected function getNotifications(\Schoolbox\User $user): array
    {
        // as much as we'd like get this as cached in session, we can't include subjects here
        $accesses = $this->userAccessMapper->findAllGroupsForUser($this->getUser());

        $folderIds = $accesses->getPropertyValues('serviceIdentifier');

        /** @var \Schoolbox\Collection|FolderModel[] $folders */
        $folders = $this->folderMapper->findByColumn('id', $folderIds)->sortBy('name', 'asc');
        $service = UserAccess::SERVICE_HOMEPAGE;

        $muted = [
            'unfollow' => $this->getUser()->getId(),
            'disabled' => 0,
        ];

        $routeKeyRegex = "/service\\.$service\\.([0-9]+)/";
        foreach ($muted as $k => $id) {
            $muted[$k] = $this->reRouteOverrideMapper
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

        $followment = [];

        // following
        foreach ($folders as $folder) {
            $grp    = $folder->getGroup();
            $forced = $grp->getForceFollow() || in_array($folder->getId(), $muted['disabled']);

            $follow = !(in_array($folder->getId(), $muted['unfollow']) || in_array($folder->getId(), $muted['disabled']));

            $followment[$folder->getId()] = [
                'name'   => $folder->getName(),
                'follow' => $follow,
                'force'  => $forced,
            ];
        }

        return $followment;
    }

    /**
     * follows / unfollows a group
     * @param int  $group
     * @param User $user
     * @param bool $isNotificationOn
     */
    protected function setNotification(int $group, \Schoolbox\User $user, bool $isNotificationOn): array
    {
        //helpers
        $serviceKey = 'service.' . UserAccess::SERVICE_HOMEPAGE . ".$group";

        //see if the object already exists in the db, if so grab it to edit.
        $muteObj = $this->reRouteOverrideMapper->findByColumnList([
                                                                      'msg_route_key' => $serviceKey,
                                                                      'user_id'       => $user->getId(),
                                                                      'delivery'      => RoutePermission::MUTE,
                                                                  ]);

        ///if user does not want notifications/follow for the group, create the mute object, and none exists currently
        if (false === $isNotificationOn && 0 === $muteObj->count()) {
            //create the mute object
            $muteObj = $this->reRouteOverrideMapper->createModel()
                                                   ->setUser($user)
                                                   ->setRouteKey($serviceKey)
                                                   ->setDelivery([RoutePermission::MUTE]);
            $this->reRouteOverrideMapper->save($muteObj);
        }

        //if the user does want notifications/follows and there is an muteobject entry, lets remove that now.
        if (true === $isNotificationOn && 0 < $muteObj->count()) {
            //delete the mute
            $this->reRouteOverrideMapper->delete($muteObj);
        }

        return $this->getNotifications($user);
    }

    /**
     * get the text representation of the users enrolment in a group
     * @param null|UserAccess $userGroup
     * @return string
     */
    protected function getEnrollmentText(?UserAccess $userGroup): string
    {
        if ($userGroup === null) {
            return self::NOTENROLLED;
        }
        //get enrolmentStatus, could be enrolled, can join, or pending
        $enrollment = $userGroup->getAccessLevel() ? self::ENROLLED : self::NOTENROLLED;
        if (($userGroup->getAccessLevel() === UserAccess::ACCESS_DENY)) {
            $enrollment = self::PENDING;
        }
        return $enrollment;
    }

    /**
     * grab user/group objects with error checking, if the user has not specified a group, or they are not part of.
     *
     * @param null|int $groupId
     * @return null | Group
     */
    protected function getGroup(?int $groupId): ?Group
    {
        try {
            /** @var Group $group */
            $group = $this->groupMapper->findById($groupId);
            return $group;
        } catch (MapperException\NotFound $e) {
            $problem = (new ApiProblem('Group does not exist'))
                ->setStatus(400)
                ->asArray();
            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return null;
        }
    }

    /**
     * get the user group object
     * @param User $user
     * @param int  $groupId
     * @return null|UserAccess
     */
    protected function getUserGroup(User $user, int $groupId): ?UserAccess
    {
        try {
            return $this->userAccessMapper->findOneByColumnList([
                                                                    'uid' => $user->getId(),
                                                                    'sid' => $groupId,
                                                                ]);
        } catch (MapperException\NotFound $e) {
            $problem = (new ApiProblem('User is not part of the group'))
                ->setStatus(400)
                ->asArray();
            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
            return null;
        }
    }
}
