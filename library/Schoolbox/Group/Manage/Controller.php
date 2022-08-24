<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group\Manage;

use Alaress\Web\Controller\Base;
use Schoolbox\GenericCollection;
use Schoolbox\Group\Manage\ArrayToModel;
use Schoolbox\Group\GroupsHelper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class Controller
 *
 * Group management page
 *
 * @package Schoolbox\Group
 */
class Controller extends Base
{
    private GroupsHelper $groupsHelper;

    public function __construct(
        private UserMapper $userMapper,
        private GroupMapper $groupMapper,
        private UserAccessMapper $userAccessMapper,
        private FolderMapper $folderMapper,
        private RoleMapper $roleMapper,
        \Timetable_ColourMapper $timetableColourMapper,
        RerouteOverrideMapper $rerouteOverrideMapper,
    ) {
        $this->groupsHelper = new GroupsHelper(
            $groupMapper,
            $userAccessMapper,
            $folderMapper,
            $timetableColourMapper,
            $rerouteOverrideMapper,
        );
    }

    /**
     * Group management page
     * @param Folder $folder
     * @return void
     * @api   GET /group/modify/{folderId}
     * @since 22.1
     */
    public function getModify(Folder $folder)
    {
        $this->getResponse()->content->set(
            [
                'template' => '@group/modify.twig',
                'title'    => 'Manage Group',
                'data'     => [
                    'folder'      => $folder,
                    'showSetting' => (bool)$this->request->query->get('new'),
                    'breadcrumbs' => [
                        [$folder->getTitle(), "/homepage/{$folder->getId()}"],
                        ['Manage Group'],
                    ],
                ],
            ]
        );
    }

    /**
     * Get group users in json
     * @param Folder $folder
     * @return void
     * @api   GET /group/getData/{folderId}
     * @since 22.1
     */
    public function getData(Folder $folder)
    {
        $group = $folder->getGroup();
        $data  = [
            'groupId'   => $folder->getId(),
            'groupName' => $folder->getNameWithCodes(),
            'settings'  => [
                'new'                    => $this->request->query->get('new') ?? 0,
                'access'                 => $group ? $group->getTypeString() : '',
                'isNotificationsEnabled' => $this->groupsHelper->groupNotificationEnabled($folder->getId()),
                'isForceFollow'          => $group && $group->getForceFollow(),
            ],
            'members'   => $group ? $this->groupsHelper->getGroupUsers($group) : [],
        ];

        $this->getResponse()->content->set(
            $this->buildJsonOutput($data)
        );
    }

    /**
     * Remove members from a group
     * @param Folder $folder
     * @return void
     * @api   DELETE /group/removeMembers/{folderId}
     * @since 22.1
     */
    public function removeMembers(Folder $folder)
    {
        $requestBody = json_decode(json_encode($this->request->content->get()), true);
        $userIds     = array_map('intval', $requestBody['members']);

        $users        = $this->userMapper->findByColumnList(['id' => $userIds]);
        $userAccesses = $this->userAccessMapper->findForFolderUser($folder, $users);
        try {
            $this->userAccessMapper->delete($userAccesses);
        } catch (MapperException $e) {
            $this->sendJsonError('Failed to remove member: ' . $e->getMessage(), 400);
            return;
        }

        $this->getResponse()->content->set(
            $this->buildJsonOutput(['success' => true])
        );
    }

    /**
     * Update group members
     * @param Folder $folder
     * @return void
     * @api   PATCH /group/updateMembers/{folderId}
     * @since 22.1
     */
    public function updateMembers(Folder $folder)
    {
        $requestBody = json_decode(json_encode($this->request->content->get()), true);
        $newAccesses = array_combine(
            array_map('intval', array_column($requestBody['accessTypes'], 'userId')),
            $requestBody['accessTypes']
        );

        $users        = $this->userMapper->findByColumnList(['id' => array_keys($newAccesses)]);
        $userAccesses = $this->userAccessMapper->findForFolderUser($folder, $users);

        $userAccesses->map(function ($userAccess) use ($newAccesses) {
            return (new ArrayToModel\UserAccess())($newAccesses[$userAccess->getUser()->getId()], $userAccess);
        });
        try {
            $this->userAccessMapper->save($userAccesses);
        } catch (MapperException $e) {
            $this->sendJsonError('Failed to update member: ' . $e->getMessage(), 400);
            return;
        }

        $this->getResponse()->content->set(
            $this->buildJsonOutput(['success' => true])
        );
    }

    /**
     * @param Folder $folder
     * @return void
     * @throws MapperException
     * @api   POST /group/addMembers/{folderId}
     * @since 22.1
     */
    public function addMembers(Folder $folder)
    {
        $requestBody = json_decode(json_encode($this->request->content->get()), true);
        $users       = $this->getUsersFromRequest($requestBody);

        // Find users already in the group
        $existingAccesses = $this->userAccessMapper->findForFolderUser($folder, $users)->indexByCallback(
            static function ($ua) {
                /** @var UserAccess $ua */
                return $ua->getUser()->getId();
            }
        );

        // Prepare new access
        $newAccesses = $this->userAccessMapper->createCollection();
        foreach ($users as $user) {
            if ($existingAccesses->offsetExists($user->getId())) {
                continue;
            }
            /** @var UserAccess $userAccess */
            $userAccess = (new ArrayToModel\UserAccess())($requestBody);
            $userAccess->setUser($user)
                       ->setServiceIdentifier($folder->getId());
            $newAccesses->add($userAccess);
        }

        // Persist
        if ($newAccesses->count() > 0) {
            if (!$folder->getGroup()) {
                $group = $this->groupMapper->createModel()
                                           ->setFolder($folder)
                                           ->setNotificationDisabled(false)
                                           ->setForceFollow(false)
                                           ->setType(Group::PRIVATE_GROUP);
                $this->groupMapper->save($group);
            }
            $this->userAccessMapper->save($newAccesses);
        }

        $this->getResponse()->content->set(
            $this->buildJsonOutput(
                [
                    'success'    => true,
                    'newMembers' => $newAccesses->map(new \Schoolbox\Group\Manage\ModelToArray\UserAccess()),
                ]
            )
        );
    }

    /**
     * @param Folder $folder
     * @return void
     * @throws MapperException
     * @since 22.1
     * @api   PATCH /group/updateMembers/{folderId}
     */
    public function updateSettings(Folder $folder)
    {
        // access, isNotificationsEnabled, isForceFollow
        $requestBody = json_decode(json_encode($this->request->content->get()), true);

        /** @var Group $group */
        $group = (new ArrayToModel\Group())($requestBody, $folder->getGroup());
        $folder->setGroup($group);
        $this->groupMapper->save($folder->getGroup());

        $this->groupsHelper->setNotification(
            $folder->getId(),
            $this->userMapper->createModel()->setId(0),
            !$folder->getGroup()->getNotificationDisabled()
        );

        $this->getResponse()->content->set(
            $this->buildJsonOutput(['success' => true])
        );
    }

    /**
     * @param Folder $folder
     * @return void
     * @api   POST /group/disband/{folderId}
     * @since 22.1
     */
    public function disband(Folder $folder)
    {
        $this->groupMapper->deleteByColumn('cid', $folder->getId());
    }

    /**
     * Find groups which name contains given search query string
     * @return void
     * @api   POST /searchGroups
     * @since 22.1
     */
    public function searchGroups()
    {
        $query  = trim($this->request->query->get('query'));
        $return = [];
        foreach ($this->folderMapper->searchByName($query, true) as $group) {
            $return[] = [
                'name' => $group->getName(),
                'id'   => $group->getId(),
            ];
        }
        $this->getResponse()->content->set(
            $this->buildJsonOutput($return)
        );
    }

    /**
     * Find roles which name contains given search query string
     * @return void
     * @api   POST /searchRoles
     * @since 22.1
     */
    public function searchRoles()
    {
        $query  = trim($this->request->query->get('query'));
        $return = [];
        foreach ($this->roleMapper->findByName($query, ['order' => 'sequence']) as $role) {
            $return[] = [
                'name' => $role->getName(),
                'id'   => $role->getId(),
            ];
        }
        $this->getResponse()->content->set(
            $this->buildJsonOutput($return)
        );
    }

    /**
     * Find users from comma separated list of usernames and external ids
     * @return void
     * @api   POST /searchRoles
     * @since 22.1
     */
    public function searchUsers()
    {
        $query    = trim($this->request->query->get('query'));
        $searches = $query ? array_map('trim', explode(',', $query)) : [];
        $users    = $this->userMapper->findByColumnList(['synergy_id' => $searches]);
        $users    = $users->mergeUnique($this->userMapper->findByColumnList(['username' => $searches]), 'id');
        $return   = [];
        /** @var User $user */
        foreach ($users as $user) {
            $meta = '';
            if ($user->getYear()) {
                $meta .= $user->getYear()->getLongName() . ', ';
            }
            $meta .= $user->getRole()->getName();
            if ($user->getExternalId() !== null) {
                $meta .= "({$user->getExternalId()})";
            }

            $return[] = [
                'name'        => sprintf("%s - %s", $user->formatName('{firstname} {lastname}'), $meta),
                'id'          => $user->getId(),
                'first_name'  => $user->getFirstname(),
                'last_name'   => $user->getLastname(),
                'role'        => $user->getRole()->getName(),
                'role_id'     => $user->getRole()->getId(),
                'roleType'    => $user->getRole()->getType(),
                'external_id' => $user->getExternalId(),
            ];
        }
        $this->getResponse()->content->set(
            $this->buildJsonOutput($return)
        );
    }

    /**
     * Get users from requested values
     * @param array $requestBody
     * @return GenericCollection
     */
    private function getUsersFromRequest(array $requestBody): GenericCollection
    {
        // $requestBody['members'] => 'id' => id, 'fromType' => Group/Manage/vue/addMembers/mgAddMembersFromTypes.js
        $userIds        = [];
        $groupFolderIds = [];
        $roleIds        = [];
        foreach ($requestBody['members'] as $memberRequest) {
            switch ($memberRequest['fromType']) {
                case 'name':
                    $userIds[] = (int)$memberRequest['id'];
                    break;
                case 'group':
                    $groupFolderIds[] = (int)$memberRequest['id'];
                    break;
                case 'role':
                    $roleIds[] = (int)$memberRequest['id'];
                    break;
                case 'list':
            }
        }

        // Gather all the users
        $users = $this->userMapper->createCollection();
        if (count($userIds)) {
            $users = $users->mergeUnique($this->userMapper->findByColumnList(['id' => $userIds]), 'id');
        }
        if (count($groupFolderIds)) {
            $folders = $this->folderMapper->findByColumnList(['id' => $groupFolderIds]);
            foreach ($folders as $folder) {
                // should be a group
                if (!$folder->isGroup()) {
                    continue;
                }
                try {
                    $users = $users->mergeUnique($this->userAccessMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_READ, false), 'id');
                } catch (MapperException $e) {
                    // ignore
                }
            }
        }
        if (count($roleIds)) {
            $users = $users->mergeUnique($this->userMapper->findByColumnList(['group_id' => $roleIds]), 'id');
        }
        return $users;
    }
}
