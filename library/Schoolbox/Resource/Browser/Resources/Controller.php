<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Resources;

use _HumbugBox243b3a4ed02c\Nette\Neon\Exception;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\UserAccess;
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
use Schoolbox\Resource\Browser\Resources\FilesBuilder;

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
            'file'       => $link,
            'type'       => 'link',
            'tree'       => $this->getBreadcrumbs($link->getFolder()),
            'host'       => sprintf('http%s://%s', $server->get('HTTPS') ? 's' : '', $server->get('HTTP_X_FORWARDED_HOST') ?? $server->get('HTTP_HOST')),
            'access'     => Access::getInstance()->getFolderAccess($this->getUser(), $folderId),
            'namespace'  => $this->namespace,
        ];

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/showLinkInfo.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
    }

    /**
     * @api GET /folder/:componentID/files/:sourceFolderID
     *
     * @param int $componentID
     * @param int $sourceFolderID
     */
    public function showFiles($componentID, $sourceFolderID)
    {
        $filesBuilder = new FilesBuilder($this->getDi()->get('databaseInternal'));
        $homepage_id = $filesBuilder->getHomepage($componentID);

        //check perms, on both the source folder, and the homepage it came from, people could bookmark or send the link
        $accessSource = $this->getAccess($sourceFolderID);
        $accessHome = $this->getAccess($homepage_id);

        // special case for root, just let that through to the keeper
        if (($accessSource < UserAccess::ACCESS_READ || $accessHome < UserAccess::ACCESS_READ ) && $sourceFolderID != 0) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        }

        $componentConfig = $filesBuilder->getComponentConf($componentID);

        $files = $filesBuilder->getFolderData($componentConfig['src']['value']);

        //just dump the lot to the page for front end use.
        //output results
        $this->getResponse()->content->set([
           'template' => '@resourceBrowser/viewAll.twig',
           'data' => [
               'files' => $files,
               'title' => $componentConfig['componentname']['value'],
               'componentInstanceConfig' => $componentConfig,
                'componentID' => $componentID,
                'sourceFolderID' => $sourceFolderID,
                'componentFolderId' => $componentConfig['homepage'],
                'breadcrumbs' => [
                    $sourceFolderID => "/homepage/${sourceFolderID}",
                ],
           ],
        ]);
    }

    /**
     * @api GET /folder/:componentID/files/:sourceFolderID/all
     *
     * @param int $componentID
     * @param int $sourceFolderID
     */
    public function showAllFiles($componentID, $sourceFolderID)
    {
        $filesBuilder = new FilesBuilder($this->getDi()->get('databaseInternal'));
        $homepage_id = $filesBuilder->getHomepage($componentID);

        //check perms, on both the source folder, and the homepage it came from, people could bookmark or send the link
        $accessSource = $this->getAccess($sourceFolderID);
        $accessHome = $this->getAccess($homepage_id);

        // special case for root, just let that through to the keeper
        if (($accessSource < UserAccess::ACCESS_READ || $accessHome < UserAccess::ACCESS_READ ) && $sourceFolderID != 0) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        }

        $files = $filesBuilder->getFolderData($sourceFolderID);

        //dump all as json
        $this->getResponse()->content->set($this->buildJsonOutput([
              'componentID' => $componentID,
              'sourceFolderID' => $sourceFolderID,
              'homepageID' => $homepage_id,
              'file_list' => $files,
          ]));
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
