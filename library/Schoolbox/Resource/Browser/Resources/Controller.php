<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Resources;

use Schoolbox\Error\Mapper as ErrorMapper;
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
        /**
         * Need to get the 'definations' out of the db for components.
         * ```
         * default config object
         * select component_config_definition.label, component_config_definition.key, component_config_definition.default_value
         * from component_config_definition
         * inner join component_definition
         * on component_definition.id = component_config_definition.component_definition_id
         * where component_definition.class = 'Component_Homepage_FileListController'
         * ```

         * ```
         * instance config object
         * select component_config_definition.label, component_config_definition.key, component_config_instance.value
         * from component_config_definition
         * inner join component_config_instance
         * on component_config_instance.component_config_definition_id = component_config_definition.id
         * where component_config_instance.component_instance_id = 4901
         * ```
         */
        //get all mappers, to build the component config
        $componentInstanceMapper = \Component_InstanceMapper::getInstance();
        $componentDefinitionMapper = \Component_DefinitionMapper::getInstance();
        $componentConfigDefinitionMapper = \Component_ConfigDefinitionMapper::getInstance();
        $componentConfigInstanceMapper = \Component_ConfigMapper::getInstance();

        //find the specific component object and the definition objet for the files component
        //allow errors if they exist, most likely not found, this is fine
        $compInstance = $componentInstanceMapper->findById($componentID);
        $definition = $componentDefinitionMapper->findOneByColumn('class', 'Component_Homepage_FileListController');

        //build the default component config object
        try {
            $cd = $componentConfigDefinitionMapper->findByColumn('component_definition_id', $definition->getId());
        } catch (Mapper\Exception $e) {
            //component not found, error out.
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }
        $confDefinition = [];
        foreach ($cd as $row) {
            $confDefinition[$row->getid()] = [
                'value' => $row->getValue(),
                'key' => $row->getKey(),
                'defaultValue' => $row->getDefaultValue(),
                'label' => $row->getLabel(),
            ];
        }

        // build the component instance config object, with the above default values if required
        try {
            //find the component instance config data, and specifically the component title
            $compInstConf = $componentConfigInstanceMapper->findByColumn('component_instance_id', $componentID);
        } catch (Mapper\Exception\NotFound $e) {
            // component has no config, just set title to default
        }
        $instConf = [];
        foreach ($compInstConf as $row) {
            $instConf[$confDefinition[$row->getConfigDefinitionId()]['key']] = [
                'configDefinitionId' => $row->getConfigDefinitionId(),
                'id' => $row->getId(),
                'value' => $row->getValue() ? $row->getValue() : $confDefinition[$row->getConfigDefinitionId()]['defaultValue'],
            ];
        }

        //just dump the lot to the page for front end use.
        //output results
        $this->getResponse()->content->set([
           'template' => '@resourceBrowser/viewAll.twig',
           'data' => [
               'title' => $instConf['componentname']['value'],
               'componentInstanceConfig' => $instConf,
                'componentID' => $componentID,
                'sourceFolderID' => $sourceFolderID,
                'componentFolderId' => $compInstance->getIdentifier(),
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
        //get the files, for the source folder
        $fileMapper = \Schoolbox\Resource\File\Mapper::getInstance();
        $fileList = $fileMapper->findForFolders(
            [$sourceFolderID],
        );

        //lets build the list folder array, so we dont have to warry about hydration. etc
        $files = [];
        foreach ($fileList as $file) {
            $files[$file->getId()] = [
                'title'                 => $file->getTitle(),
                'file_id'               => $file->getId(),
                'file_mime_image'       => $file->getStorage()->getMimetype()->getIconFile(),
                'file_mime_type'        => $file->getStorage()->getMimetype()->getDescription(),
                'file_posted_date'      => $file->getModifiedAt()->getShort() ?: $file->getCreatedAt()->getShort(),
                'file_posted_relative'  => $file->getModifiedAt()->getRelativeTime() ?: $file->getCreatedAt()->getRelativeTime(),
                'hash'                  => $file->getHash(),
                'size'                  => $file->getSize(),
                'folder'                => ['id' => $file->getFolder()->getId(), 'name' => $file->getFolder()->getTitle()],
            ];
        }

        //dump all as json
        $this->getResponse()->content->set($this->buildJsonOutput([
              'componentID' => $componentID,
              'sourceFolderID' => $sourceFolderID,
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
