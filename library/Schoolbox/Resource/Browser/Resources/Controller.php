<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Resources;

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\RoleAccess;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource;
use Schoolbox\Resource\Browser\AbstractController;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Component_InstanceMapper;
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
     * @var FileMapper
     */
    protected $fileMapper;

    /**
     * @var Component_InstanceMapper
     */
    protected Component_InstanceMapper $componentInstanceMapper;

    /**
     * Constructor, just using this for the showAll files and FilesBuilder class
     * @param FileMapper               $fileMapper
     * @param Component_InstanceMapper $componentInstanceMapper
     */
    public function __construct(FileMapper $fileMapper, Component_InstanceMapper $componentInstanceMapper)
    {
        $this->fileMapper               = $fileMapper;
        $this->componentInstanceMapper  = $componentInstanceMapper;
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

        $server = $this->request->getServerParams();

        $data = [
            'file'       => $link,
            'type'       => 'link',
            'tree'       => $this->getBreadcrumbs($link->getFolder()),
            'host'       => sprintf(
                'http%s://%s',
                ($server['HTTPS'] ?? false) ? 's' : '',
                $server['HTTP_X_FORWARDED_HOST'] ?? $server['HTTP_HOST'] ?? '',
            ),
            'access'     => Access::getInstance()->getFolderAccess($this->getUser(), $folderId),
            'namespace'  => $this->namespace,
        ];

        $this->response = $this->responseFromTemplateUnthemed(
            '@resourceBrowser/showLinkInfo.twig',
            $data,
        );
    }

    /**
     * GUI display of files within a folder
     * @api GET /folder/files/:sourceFolderID
     *
     * @param Folder\Model $sourceFolder
     * @return void
     * @throws Mapper\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function showFolderFiles(Folder\Model $sourceFolder)
    {
        $this->showFiles($sourceFolder);
    }

    /**
     * GUI display of files within a files component source
     * @api GET /folder/:componentID/files/:sourceFolderID
     *
     * @param Folder\Model $sourceFolder
     * @param int|null     $componentID
     * @return void
     * @throws Mapper\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function showFiles(Folder\Model $sourceFolder, ?int $componentID = null)
    {
        $filesBuilder = new FilesBuilder($this->fileMapper, $this->componentInstanceMapper, $this->routeGenerator);

        //check perms, on both the source folder, and the homepage it came from, people could bookmark or send the link
        $accessSource = $this->getAccess($sourceFolder->getId());
        $homepageId   = $componentID ? $filesBuilder->getHomepage($componentID, $sourceFolder->getId()) : null;
        // local component or not from component
        if (!$homepageId || $sourceFolder->getId() === $homepageId) {
            $homepage   = $sourceFolder;
            $accessHome = $accessSource;
            $canRead = $accessSource >= UserAccess::ACCESS_READ;
        } else {
            // alt sourced
            $homepage   = $this->folderMapper->findById($homepageId);
            $accessHome = $this->getAccess($homepage->getId());
            $canRead = $accessSource >= UserAccess::ACCESS_READ && $accessHome >= UserAccess::ACCESS_READ;
        }

        // special case for root, just let that through to the keeper
        if (!$canRead && !$sourceFolder->isRootNode()) {
            $this->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        }

        //grab all files for component folder
        $files = $filesBuilder->getFolderData($sourceFolder->getId(), $accessHome, $componentID);

        if ($componentID) {
            //lets get the config for the component
            $componentConfig = $filesBuilder->getComponentConf($componentID);

            //build the filter object
            $filter        = \Component_Config_Filter::getSelectedFilters(
                (int)$componentConfig['filter']['value'],
                Resource\File\Model::FILTER_FIELDS
            );
            $title         = $componentConfig['componentname']['value'];
            $breadcrumb    = $title;
            $sortDirection = $componentConfig['sortdir']['value'];
            $sortBy        = $componentConfig['sort']['value'];
        } else {
            $filter        = Resource\File\Model::FILTER_FIELDS;
            $breadcrumb    = $accessSource >= UserAccess::ACCESS_WRITE ? 'Manage Files' : 'View Files';
            $title         = $breadcrumb . ' on ' . $sourceFolder->getNameWithCodes();
            $sortDirection = null;
            $sortBy        = null;
        }

        //grab the referrer so the saved filters can be recalled only when returning from the edit page
        $referrer = 'component';
        if (strpos(($_SERVER['HTTP_REFERER'] ?? ''), 'cms/file') !== false) {
            $referrer = 'edit';
        }
        //just dump the lot to the page for front end use.
        //output results
        $this->response = $this->responseFromTemplate(
            '@resourceBrowser/viewAll.twig',
            [
                'referrer'       => $referrer,
                'canEdit'        => $accessSource >= UserAccess::ACCESS_WRITE,
                'homepage'       => $homepage,
                'filter'         => $filter,
                'files'          => $files,
                'breadcrumb'     => $breadcrumb,
                'title'          => $title,
                'sortDir'        => $sortDirection,
                'sortBy'         => $sortBy,
                'componentID'    => $componentID,
                'sourceFolderID' => $sourceFolder->getId(),
            ],
            $title,
        );
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

    /**
     * @param Resource\File\Model $file
     * @api   PATCH /resources/file/:fileId/ajax
     * @since 22.0
     * @throws Mapper\Exception|Resource\Exception|SchoolboxException
     */
    public function patchFileInfo(Resource\File\Model $file)
    {
        $data = $this->request->getParsedBody();
        if (isset($data['hidden'])) {
            $file->setHidden($data['hidden']);
        } else {
            throw new SchoolboxException("Only able to update Hidden option");
        }
        /**
         * save new object
         */
        $this->fileMapper->save($file);

        $data = [
            'title'         => $file->getTitle(),
            'fileName'      => $file->getFilename(),
            'description'   => $file->getDescription(),
            'mimeType'      => $file->getMime(),
            'filesize'      => $file->getSize(),
            'hidden'        => $file->isHidden(),
        ];

        $this->response = $this->responseAsJson($data);
    }
}
