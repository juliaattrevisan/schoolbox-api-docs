<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Group;

use Alaress\Web\Controller\Base;
use Schoolbox\Group\ArrayToModel;
use Schoolbox\Groups\GroupsHelper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
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

    private FolderMapper $folderMapper;

    private RerouteOverrideMapper $rerouteOverrideMapper;

    private GroupsHelper $groupsHelper;

    public function __construct(
        UserMapper $userMapper,
        GroupMapper $groupMapper,
        UserAccessMapper $userAccessMapper,
        FolderMapper $folderMapper,
        \Timetable_ColourMapper $timetableColourMapper,
        RerouteOverrideMapper $rerouteOverrideMapper,
    ) {
        $this->userMapper            = $userMapper;
        $this->groupMapper           = $groupMapper;
        $this->userAccessMapper      = $userAccessMapper;
        $this->folderMapper          = $folderMapper;
        $this->rerouteOverrideMapper = $rerouteOverrideMapper;
        $this->groupsHelper          = new GroupsHelper(
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
     * @api   /group/modify/{folderId}
     * @since 22.1
     */
    public function getModify(Folder $folder)
    {
        $this->getResponse()->content->set(
            [
                'template' => '@group/modify.twig',
                'title'    => 'Group',
                'data'     => ['folder' => $folder,],
            ]
        );
    }

    /**
     * Get group users in json
     * @param Folder $folder
     * @return void
     * @api   /group/getData
     * @since 22.1
     */
    public function getData(Folder $folder)
    {
        $group                = $folder->getGroup();
        $groupMessagesEnabled = !$this->rerouteOverrideMapper
            ->isRouteMuted(
                "service." . UserAccess::SERVICE_HOMEPAGE . ".{$folder->getId()}"
            );
        $data['groupId']      = $folder->getId();
        $data['settings']     = [
            'access'                 => $group->getTypeString(),
            'isNotificationsEnabled' => $groupMessagesEnabled,
            'isForceFollow'          => $group->getForceFollow(),
        ];
        $data['members']      = $this->groupsHelper->getGroupUsers($group);

        $this->getResponse()->content->set(
            $this->buildJsonOutput($data)
        );
    }

    /**
     * @param Folder $folder
     * @return void
     * @api   /group/removeMembers/{folderId}
     * @since 22.1
     */
    public function removeMembers(Folder $folder)
    {
        $requestBody = json_decode(json_encode($this->request->content->get()), true);
        $userIds     = array_map('intval', explode(',', $requestBody['members']));

        $users        = $this->userMapper->findByColumnList(['id' => $userIds]);
        $userAccesses = $this->userAccessMapper->findForFolderUser($folder, $users);
        try {
            $this->userAccessMapper->delete($userAccesses);
        } catch (MapperException $e) {
            $this->getResponse()->content->set(
                $this->buildJsonOutput(['success' => false])
            );
            exit;
        }

        $this->getResponse()->content->set(
            $this->buildJsonOutput(['success' => true])
        );
    }

    /**
     * Update group members
     * @param Folder $folder
     * @return void
     * @api   /group/updateMembers/{folderId}
     * @since 22.1
     */
    public function updateMembers(Folder $folder)
    {
        $requestBody = json_decode(json_encode($this->request->content->get()), true);
        $userIds     = array_map('intval', explode(',', $requestBody['members']));

        $users        = $this->userMapper->findByColumnList(['id' => $userIds]);
        $userAccesses = $this->userAccessMapper->findForFolderUser($folder, $users);

        $userAccesses->map(function ($userAccess) use ($requestBody) {
            return (new ArrayToModel\UserAccess())($requestBody, $userAccess);
        });
        try {
            $this->userAccessMapper->save($userAccesses);
        } catch (MapperException $e) {
            $this->getResponse()->content->set(
                $this->buildJsonOutput(['success' => false])
            );
            exit;
        }

        $this->getResponse()->content->set(
            $this->buildJsonOutput(['success' => true])
        );
    }

    public function addMembers(Folder $folder)
    {
//        $requestBody = json_decode(json_encode($this->request->content->get()), true);
//        $userIds     = array_map('intval', explode(',', $requestBody['members']));
//
//        $users        = $this->userMapper->findByColumnList(['id' => $userIds]);
//        $userAccesses = $this->userAccessMapper->findForFolderUser($folder, $users);
//
//        $newAccesses = $userAccesses->map(function ($userAccess) use ($requestBody) {
//            return (new ArrayToModel())($requestBody, $userAccess);
//        });
//        $this->userAccessMapper->save($newAccesses);
//
//        $this->getResponse()->content->set(
//            $this->buildJsonOutput(['success' => true])
//        );
    }

    /**
     * @param Folder $folder
     * @return void
     * @api   /group/updateMembers/{folderId}
     * @since 22.1
     */
    public function updateSettings(Folder $folder)
    {
        // access, isNotificationsEnabled, isForceFollow
        $requestBody = json_decode(json_encode($this->request->content->get()), true);

        $folder->getGroup()->


        $users        = $this->userMapper->findByColumnList(['id' => $userIds]);
        $userAccesses = $this->userAccessMapper->findForFolderUser($folder, $users);

        $newAccesses = $userAccesses->map(function ($userAccess) use ($requestBody) {
            return (new UserAccess())($requestBody, $userAccess);
        });
        $this->userAccessMapper->save($newAccesses);

        $this->getResponse()->content->set(
            $this->buildJsonOutput(['success' => true])
        );
    }

    public function disband(Folder $folder)
    {
    }
}
