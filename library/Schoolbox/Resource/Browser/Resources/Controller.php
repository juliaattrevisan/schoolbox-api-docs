<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\Resources;


use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource;
use Schoolbox\Resource\Browser\AbstractController;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder\IFolder as FolderInterface;
use Schoolbox\User;

class Controller extends AbstractController
{
    /** @var RoleAccessMapper */
    protected $roleAccessMapper;

    /** @var UserAccessMapper */
    protected $userAccessMapper;


    /**
     * @param RoleAccessMapper $roleAccessMapper
     * @return self
     */
    public function setRoleAccessMapper($roleAccessMapper)
    {
        $this->roleAccessMapper = $roleAccessMapper;
        return $this;
    }


    /**
     * @param UserAccessMapper $userAccessMapper
     * @return self
     */
    public function setUserAccessMapper($userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
        return $this;
    }


    /**
     * @param int $folderId
     * @return array
     */
    protected function getFolderData($folderId)
    {
        $data = parent::getFolderData($folderId);

        $data['roles']  = $this->roleAccessMapper->findAllForFolder($data['folder']);
        $data['access'] = $this->getAccess([ $folderId ]);

        $admins = $this->userAccessMapper->findAdminsInCategory($data['folder']);

        if ($admins->count()) {
            $data['admin'] = $this->userMapper->findById($admins->first()->getId());
        } else {
            $data['admin'] = new User();
            $data['admin']->setFirstname('Nobody');
        }

        return $data;
    }


    /**
     * @param FolderInterface $folder
     * @return array
     */
    protected function getBreadcrumbs(FolderInterface $folder)
    {
        try {
            $node = $this->nodeFactory->build(Resource\Browser\NodeFactory::SBFS, $folder->getId());
            $tree = $node->render();

            return $tree['breadcrumbs'];

        } catch (\InvalidArgumentException $e) {
            return [];
        }
    }


    /**
     * @param int $folderId
     * @return Resource\Folder\IFolder|Resource\Folder\Model
     * @throws Mapper\Exception
     */
    protected function getFolder($folderId)
    {
        try {
            $folder = $this->folderMapper->findById($folderId);
            $folder->loadTeachers();

        } catch (Mapper\Exception\Deleted $e) {
            // Deleted: load the parent (recursively)
            return $this->getFolder($e->getDeletedObject()->getParent()->getId());

        } catch (Mapper\Exception $e) {
            $folder = $this->folderMapper->createModel();
            $folder->setId(0);
        }

        return $folder;
    }


    /**
     * @param array $folderIds
     * @return int
     */
    protected function getAccess(array $folderIds)
    {
        if ($this->getUser()->isSuperuser()) {
            return UserAccess::ACCESS_FULL;
        }

        if (count($folderIds) != 1) {
            return $this->userAccessMapper
                        ->userHasMinimumAccessToAll($this->getUser()->getId(), $folderIds, UserAccess::ACCESS_WRITE)
                ? UserAccess::ACCESS_WRITE
                : UserAccess::ACCESS_DENY;
        }

        try {
            return $this->userAccessMapper
                ->getAccess($this->getUser(), $folderIds[0])
                ->getAccessLevel();

        } catch (Mapper\Exception $e) {
            try {
                return $this->roleAccessMapper
                    ->getAccess($this->getUser(), $folderIds[0])
                    ->getType();

            } catch (Mapper\Exception $e) {
                // no accesseses found...you get nothin'
                return null;
            }
        }
    }

} 