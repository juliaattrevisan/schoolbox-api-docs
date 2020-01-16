<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Resources;

use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccess;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource;
use Schoolbox\Resource\Browser\AbstractController;
use Schoolbox\Mapper;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\Resource\Folder;

class Controller extends AbstractController
{
    /**
     * @var Resource\LinkMapper
     */
    protected $linkMapper;

    // Injectables

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
     * @since 150
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
            'access'    => Access::getInstance()->getFolderAccess($this->getUser(), $folderId),
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

        // Redirect to this folder next time page gets reloaded
        $this->getSession()->set('resourcesID', $folderId, $this->namespace);

        /** @var Folder\Model $folder */
        $folder = $data['folder'];
        if ($folderId !== Folder\Model::ROOT_ID) {
            Folder\Log\Mapper::getInstance()->incrementHitcount($this->getUser(), $folder);
            Folder\Mapper::getInstance()->logPageView($folder);
            $data['parent'] = Folder\Mapper::getInstance()->findCheaplyById($folder->getParent()->getId());
            $data['roles']  = RoleAccessMapper::getInstance()->findAllForFolder($folder);
            $admins         = UserAccessMapper::getInstance()->findAdminsInCategory($folder);
            if ($admins->count()) {
                $data['admin'] = $this->userMapper->findById($admins->first()->getId());
            } else {
                $data['admin'] = new User();
                $data['admin']->setFirstname('Nobody');
            }
        } else {
            $access       = Access::getInstance();
            $roleAccesses = RoleAccess::createCollection();
            foreach (RoleMapper::getInstance()->findAll() as $role) {
                $roleAccess = (new RoleAccess())
                    ->setRole($role)
                    ->setCategory($folder)
                    ->setType($access->getFolderAccessForRole($role->getId(), $folderId));
                $roleAccesses->setOffset($role->getId(), $roleAccess);
            }
            $data['roles'] = $roleAccesses;
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
        // eaten by l10ns
        if ($folderId == 0) {
            $folder->setName($this->getDi()->get('l10n')->translate('resource', true, true));
        }
        $folder->loadTeachers();

        return $folder;
    }


    /**
     * @inheritdoc
     */
    protected function getAccess($folderId)
    {
        // find out if we have access to the requested folder
        return Access::getInstance()->getFolderAccess($this->getUser(), (int)$folderId);
    }
}
