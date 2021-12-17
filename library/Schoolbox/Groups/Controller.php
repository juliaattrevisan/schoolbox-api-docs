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
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Realtime\Message\RerouteOverride;
use Schoolbox\User;
use Timetable_ColourMapper;

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
        $user = $this->getUser();

        //colours for groups with no colours
        /* Get Timetable colours to use as random colours for null set folders */
        $colourList = Timetable_ColourMapper::getInstance()->findAll();
        $colourIndex = 0;
        $colourFactory = new ColorFactory();
        $colourCache   = new GenericCollection(Color::class);

        //check if user is currently member
        // Get the groups the user is currently a member, their enrolment type and favourite status
        /** @var GenericCollection|UserAccess[] $userPerms */
        $userPerms = $accessMapper->findByColumnList([
            'uid'     => $user->getId(),
            'service' => UserAccess::SERVICE_HOMEPAGE,
        ])->indexBy('serviceIdentifier');
        $groupIds = $userPerms->keys();

        //get list of all possible public groups
        $groups = $groupMapper->findByColumnList([
            'type' => [Group::FREE_GROUP, Group::MODERATED_GROUP],
        ]);
        $groupIds = array_unique(array_merge($groupIds, $groups->keys()));

        //build notifications
        $notificationsList = $this->getNotifications($user);

        //build the folder list object with hydration
        $folders = $folderMapper->findByColumnList(['id' => $groupIds]);

        // build output array
        $userAccess     = [];
        $possibleGroups = [];
        /* @var UserAccess|Group $group */
        foreach ($folders as $folder) {
            //favourite
            $isFavourite = false;
            if ($userPerms[$folder->getId()]) {
                $isFavourite = $userPerms[$folder->getId()]->getFavourite();
            }

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
            $access = $accessHelper->getFolderAccess($user, $folder->getId()) ?: null;
            if (UserAccess::ACCESS_WRITE <= $access) {
                $modifyLink = $this->getRouter()->generate(
                    'folder.getFolderModify',
                    ['id' => $folder->getId(), 'folder' => $folder]
                );
            } else {
                $modifyLink = false;
            }
            $tmp                    = [
                'id'                => $folder->getId(),
                'name'              => $folder->getName(),
                'enrolmentStatus'   => $this->getEnrollmentText($userPerms[$folder->getId()]),
                'colour'            => $folder->getColour()->getAsHex(),
                'coverImageHash'    => $imageHash,
                'isNotificationsOn' => $notificationsList[$folder->getId()]['follow'] ?: false,
                'isForceFollow'     => $notificationsList[$folder->getId()]['force'] ?: false,
                'isFavourite'       => $isFavourite,
                'type'              => $this->getGroupType($folder->getGroup()->getType()),
                'isAccessAdmin'     => ($access === UserAccess::ACCESS_FULL),
                'isAccessWrite'     => ($access === UserAccess::ACCESS_WRITE),
                'isAccessRead'      => ($access === UserAccess::ACCESS_READ),
                'isPrivate'         => $folder->getGroup()->isPrivate(),
                'modifyLink'        => $modifyLink,
            ];
            $possibleGroups[] = $tmp;
        }

        //sort the groups
        usort($possibleGroups, function ($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@groups/groups.twig', [
                'groupList'  => $possibleGroups,
                'endpoint'   => $this->getRouter()->generate('groups.newGroups'),
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
        ) {
            return $this->getResponse()->content->set($this->buildJsonOutput(['error' => 'Missing attributes']));
        }

        //helpers
        $accessMapper   = UserAccessMapper::getInstance();
        $groupMapper    = GroupMapper::getInstance();

        /** @var \Schoolbox\User $user */
        $user = $this->getUser();

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
                   'isNotificationsOn'  => true,
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
            if (isset($post['isNotificationsOn'])) {
                //check if the user can actually unsubscribe from notifications
                if ($group->getForceFollow()) {
                    return $this->getResponse()->content->set($this->buildJsonOutput(['error' => 'No Permission to unfollow group']));
                }
                $notificationsList = $this->setNotification($group->getFolder()->getId(), $user, (bool)$post['isNotificationsOn']);
            }
            $notificationsList = $this->getNotifications($user);

            return $this->getResponse()->content->set(
                $this->buildJsonOutput([
                    'group'             => $post['group'],
                    'user'              => $user->getId(),
                    'isFavourite'       => $userGroup->getFavourite(),
                    'isNotificationsOn' => $notificationsList[$group->getFolder()->getId()]['follow'] ?: false,
                    'isForceFollow'     => $notificationsList[$group->getFolder()->getId()]['force'] ?: false,
                    'access'            => $this->getEnrollmentText($userGroup),
                ])
            );
        }
        return $this->getResponse()->content->set($this->buildJsonOutput(['error' => 'empty state, something went wrong.']));
    }

    /**
     * grabs all groups the user is part of and are following
     * @param \Schoolbox\User $user
     * @return array
     */
    protected function getNotifications(\Schoolbox\User $user): array
    {
        $con = new \Schoolbox\Realtime\Controller();
        $con->setUser($user);
        return $con->calculateFollowment();
    }

    /**
     * follows / unfollows a group
     * @param int $group
     * @param User $user
     * @param bool $isNotificationOn
     */
    protected function setNotification(int $group, \Schoolbox\User $user, bool $isNotificationOn): array
    {
        //helpers
        $reRouteOverideMapper = RerouteOverrideMapper::getInstance();
        $serviceKey =  'service.' . UserAccess::SERVICE_HOMEPAGE . ".$group";

        //see if the object already exists in the db, if so grab it to edit.
        $muteObj = $reRouteOverideMapper->findByColumnList([
            'msg_route_key' => $serviceKey,
            'user_id'       => $user->getId(),
            'delivery'      => RoutePermission::MUTE
        ]);

        ///if user does not want notifications/follow for the group, create the mute object, and none exists currently
        if (false === $isNotificationOn && 0 === $muteObj->count()) {
            //create the mute object
            $muteObj = new RerouteOverride();
            $muteObj->setUser($user);
            $muteObj->setRouteKey($serviceKey);
            $muteObj->setDelivery([ RoutePermission::MUTE ]);
            $reRouteOverideMapper->save($muteObj);
        }

        //if the user does want notifications/follows and there is an muteobject entry, lets remove that now.
        if (true === $isNotificationOn && 0 < $muteObj->count()) {
            //delete the mute
            $reRouteOverideMapper->delete($muteObj);
        }

        return $this->getNotifications($user);
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
