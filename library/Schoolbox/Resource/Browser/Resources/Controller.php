<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Resources;

use Alaress\Database\Connector\IConnector;
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

class Controller extends AbstractController
{
    /**
     * @var Resource\LinkMapper
     */
    protected $linkMapper;

    /**
     * @var IConnector
     */
    protected $database;

    /**
     * Constructor, just using this for the showAll files and FilesBuilder class
     * @param IConnector $database
     */
    public function __construct(IConnector $database)
    {
        $this->database = $database;
    }

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
     * GUI display of files within a files component source
     * @api GET /folder/:componentID/files/:sourceFolderID
     *
     * @param int $componentID
     * @param int $sourceFolderID
     * @throws Mapper\Exception\NotFound
     * @throws Mapper\Exception
     */
    public function showFiles(int $componentID, int $sourceFolderID)
    {
        $filesBuilder = new FilesBuilder($this->database);
        $homepage_id = $filesBuilder->getHomepage($componentID);

        //check perms, on both the source folder, and the homepage it came from, people could bookmark or send the link
        $accessSource = $this->getAccess($sourceFolderID);
        $accessHome = $this->getAccess($homepage_id['identifier']);

        // special case for root, just let that through to the keeper
        if (($accessSource < UserAccess::ACCESS_READ || $accessHome < UserAccess::ACCESS_READ ) && $sourceFolderID != 0) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        }

        //lets get the config for the component
        $componentConfig = $filesBuilder->getComponentConf($componentID);

        //grab all files for component folder
        $files = $filesBuilder->getFolderData($componentConfig['src']['value']);

        //build the filter object
        //weird magic, from the file list component.
        $filterFields = ['document', 'audio', 'video', 'image', 'other'];
        $filter            = [];
        $countFilterFields = count($filterFields);
        for ($i = 0; $i < $countFilterFields; $i++) {
            $j = $componentConfig['filter']['value'] % 2;
            if ($j) {
                $filter[] = $filterFields[$i];
            }
            $componentConfig['filter']['value'] = (int)$componentConfig['filter']['value'] / 2;
        }

        //just dump the lot to the page for front end use.
        //output results
        $this->getResponse()->content->set([
           'template' => '@resourceBrowser/viewAll.twig',
           'title' => $componentConfig['componentname']['value'],
           'data' => [
                    'homepage' => $homepage_id,
                    'filter' => $filter,
                    'files' => $files,
                    'title' => $componentConfig['componentname']['value'],
                    'componentInstanceConfig' => $componentConfig,
                    'componentID' => $componentID,
                    'sourceFolderID' => $sourceFolderID,
                    'componentFolderId' => $componentConfig['homepage'],
           ],
        ]);
    }

    /**
     * AJAX grab of all files within a files component source
     * @api GET /folder/:componentID/files/:sourceFolderID/all
     *
     * @param int $componentID
     * @param int $sourceFolderID
     * @throws Mapper\Exception\NotFound
     * @throws Mapper\Exception
     */
    public function showAllFiles(int $componentID, int $sourceFolderID)
    {
        $filesBuilder = new FilesBuilder($this->database);
        $homepage_id = $filesBuilder->getHomepage($componentID);

        //check perms, on both the source folder, and the homepage it came from, people could bookmark or send the link
        $accessSource = $this->getAccess($sourceFolderID);
        $accessHome = $this->getAccess($homepage_id['identifier']);

        // special case for root, just let that through to the keeper
        if (($accessSource < UserAccess::ACCESS_READ || $accessHome < UserAccess::ACCESS_READ ) && $sourceFolderID != 0) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        }

        //grab all files from files component
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
