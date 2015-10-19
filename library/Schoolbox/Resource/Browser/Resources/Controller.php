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
use Schoolbox\User;

class Controller extends AbstractController
{
    /** @var RoleAccessMapper */
    protected $roleAccessMapper;

    /** @var UserAccessMapper */
    protected $userAccessMapper;

    /**
     * @var Resource\LinkMapper
     */
    protected $linkMapper;


    // Injectables


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
     * @param Resource\LinkMapper $linkMapper
     * @return self
     */
    public function setLinkMapper($linkMapper)
    {
        $this->linkMapper = $linkMapper;
        return $this;
    }


    // API methods


    /**
     * @api GET /resources/link/:linkId/info
     * @level 150
     *
     * @param int $linkId
     */
    public function showLinkInfo($linkId)
    {
        $link = $this->linkMapper->findById($linkId);

        $folderId = $link->getFolder()->getId();

        $server = $this->getRequest()->server;

        $data = [
            'file'      => $link,
            'type'      => 'link',
            'tree'      => $this->getBreadcrumbs($link->getFolder()),
            'host'      => sprintf('http%s://%s', $server->get('HTTPS') ? 's' : '', $server->get('HTTP_HOST')),
            'access'    => $this->getAccess([ $folderId ]),
            'namespace' => $this->namespace
        ];

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/showLinkInfo.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
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
     * @param int $folderId
     * @return Resource\Folder\IFolder|Resource\Folder\Model
     * @throws Mapper\Exception
     */
    protected function getFolder($folderId)
    {
        $folder = $this->folderMapper->findById($folderId);
        $folder->loadTeachers();

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