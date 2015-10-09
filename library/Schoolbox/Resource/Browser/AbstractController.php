<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser;

use Alaress\Web\Controller\Base as BaseController;
use Schoolbox\Collection;
use Schoolbox\Config;
use Schoolbox\Resource\File\IFile as FileInterface;
use Schoolbox\Resource\Folder\IFolder as FolderInterface;
use Schoolbox\Mapper;
use Schoolbox\Resource;
use Schoolbox\UserMapper;

abstract class AbstractController extends BaseController
{

    /** @var AbstractTreeBuilder */
    protected $treeBuilder;

    /** @var \Schoolbox\Mapper|Resource\File\ILocator */
    protected $fileMapper;

    /** @var Config\Loader */
    protected $conf;

    /** @var \Schoolbox\Mapper|Resource\Folder\ILocator */
    protected $folderMapper;

    /** @var UserMapper */
    protected $userMapper;

    /** @var string */
    protected $namespace;

    /** @var NodeFactory */
    protected $nodeFactory;


    /**
     * @param UserMapper $userMapper
     * @return self
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * @param \Schoolbox\Mapper|Resource\Folder\ILocator $folderMapper
     * @return self
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }


    /**
     * @param AbstractTreeBuilder $treeBuilder
     * @return self
     */
    public function setTreeBuilder($treeBuilder)
    {
        $this->treeBuilder = $treeBuilder;
        return $this;
    }


    /**
     * @param Config\Loader $conf
     * @return self
     */
    public function setConfig($conf)
    {
        $this->conf = $conf;
        return $this;
    }


    /**
     * @param \Schoolbox\Mapper|Resource\File\ILocator $fileMapper
     * @return self
     */
    public function setFileMapper($fileMapper)
    {
        $this->fileMapper = $fileMapper;
        return $this;
    }


    /**
     * @param string $namespace
     * @return self
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }


    /**
     * @param NodeFactory $nodeFactory
     * @return self
     */
    public function setNodeFactory($nodeFactory)
    {
        $this->nodeFactory = $nodeFactory;
        return $this;
    }


    /**
     * @param int $folderId
     * @return array
     */
    protected function getFolderData($folderId)
    {
        $folder = $this->getFolder($folderId);

        return [
            'tree'   => $this->getBreadcrumbs($folder->getParent()),
            'folder' => $folder,
            'access' => $this->getAccess([ $folderId ]),
            'user'   => $this->getUser(),
            'conf'   => $this->conf
        ];
    }


    /**
     * @param FolderInterface $folder
     * @return array
     */
    protected abstract function getBreadcrumbs(FolderInterface $folder);


    /**
     * Displays 3-panel browser
     *
     * @api GET /resources(/:folderId)
     * @level 150
     *
     * @param int $folderId
     */
    public function browse($folderId = 0)
    {
        // if a folder ID was supplied, stash that in session then redirect
        if ($folderId) {
            $this->getSession()->set('resourcesID', $folderId);

            $this->getResponse()->redirect->to(
                $this->getRouter()->generate("{$this->namespace}.browse", array())
            );
        }

        $folderId = $this->getSession()->get('resourcesID', 0);

        $data = $this->getFolderData($folderId);

        // add files to that
        $data['files']    = $this->fileMapper->findByFolderId($folderId)->getAll();
        $data['treedata'] = json_encode($this->treeBuilder->buildPartialTree($folderId));
        $data['namespace'] = $this->namespace;

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/browse.twig',
            'data'     => $data,
        ]);
    }


    /**
     * @api GET /resources/:folderId/nodes
     * @level 155
     *
     * @param int $folderId
     */
    public function listNodes($folderId)
    {
        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => $this->treeBuilder->buildChildNodes($folderId)
        ]);
    }


    /**
     * @api GET /resources/:folderId/children
     * @level 150
     *
     * @param int $folderId
     */
    public function listResources($folderId)
    {
        $data = [
            'files' => $this->fileMapper->findByFolderId($folderId)->getAll(),
            'namespace' => $this->namespace
        ];

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/listResources.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
    }


    /**
     * @api GET /resources/:folderId/info
     * @level 150
     *
     * @param int $folderId
     */
    public function showFolderInfo($folderId)
    {
        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/showFolderInfo.twig',
            'format'   => 'nochrome',
            'data'     => $this->getFolderData($folderId),
        ]);
    }


    /**
     * @api GET /resources/file/:fileId/info
     * @level 150
     *
     * @param int $fileId
     */
    public function showFileInfo($fileId)
    {
        $file = $this->getFile($fileId);

        $folderId = $file->getFolder()->getId();

        $server = $this->getRequest()->server;

        $data = [
            'file'   => $file,
            'tree'   => $this->getBreadcrumbs($file->getFolder()),
            'host'   => sprintf('http%s://%s', $server->get('HTTPS') ? 's' : '', $server->get('HTTP_HOST')),
            'access' => $this->getAccess([ $folderId ]),
            'namespace' => $this->namespace
        ];

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/showFileInfo.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
    }


    /**
     * @api GET /resources/file/:fileIds/info
     * @param $fileIds
     */
    public function showMultiFileInfo($fileIds)
    {
        $fileIds   = json_decode("[$fileIds]", true);
        $files     = $this->fileMapper->findByColumn('id', $fileIds);
        $folders   = new Collection($files->getColumnValues('folder'));
        $folderIds = array_unique($folders->getColumnValues('id'));

        $data = [
            'files'  => $fileIds,
            'user'   => $this->getUser(),
            'access' => $this->getAccess($folderIds),
            'namespace' => $this->namespace
        ];

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/showMultiFileInfo.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
    }


    /**
     * @param int $fileId
     * @return FileInterface
     * @throws Mapper\Exception
     */
    protected function getFile($fileId)
    {
        /** @var FileInterface $file */
        $file = $this->fileMapper->findById($fileId);

        // urgh...
        if (method_exists($file, 'setOwner')) {
            $file->setOwner($this->userMapper->findById($file->getOwner()->getId()));
        }

        return $file;
    }


    /**
     * @param int $folderId
     * @return FolderInterface
     */
    protected abstract function getFolder($folderId);

    /**
     * @param array $folderIds
     * @return int
     */
    protected abstract function getAccess(array $folderIds);

}