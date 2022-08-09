<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group\Manage;

use Alaress\Web\Controller\Base;
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
    private UserMapper $userMapper;

    private GroupMapper $groupMapper;

    private UserAccessMapper $userAccessMapper;

    private GroupsHelper $groupsHelper;

    public function __construct(
        UserMapper $userMapper,
        GroupMapper $groupMapper,
        UserAccessMapper $userAccessMapper,
        FolderMapper $folderMapper,
        \Timetable_ColourMapper $timetableColourMapper,
        RerouteOverrideMapper $rerouteOverrideMapper,
    ) {
        $this->userMapper       = $userMapper;
        $this->groupMapper      = $groupMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->groupsHelper     = new GroupsHelper(
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
        $group             = $folder->getGroup();
        $data['groupId']   = $folder->getId();
        $data['groupName'] = $folder->getNameWithCodes();
        $data['settings']  = [
            'access'                 => $group ? $group->getTypeString() : '',
            'isNotificationsEnabled' => $this->groupsHelper->groupNotificationEnabled($folder->getId()),
            'isForceFollow'          => $group && $group->getForceFollow(),
        ];
        $data['members']   = $group ? $this->groupsHelper->getGroupUsers($group) : [];

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
        $userIds     = array_map('intval', $requestBody['members']);

        $users            = $this->userMapper->findByColumnList(['id' => $userIds]);
        $existingAccesses = $this->userAccessMapper->findForFolderUser($folder, $users)->indexByCallback(
            static function ($ua) {
                /** @var UserAccess $ua */
                return $ua->getUser()->getId();
            }
        );

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
            $this->buildJsonOutput(['success' => true])
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
}
