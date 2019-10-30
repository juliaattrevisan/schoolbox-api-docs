<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser;

use Alaress\Web\Controller\Base as BaseController;
use Alaress\Web\Output\ThemedHtml;
use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Config;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\GenericZone;
use Schoolbox\Resource\File\IFile as FileInterface;
use Schoolbox\Resource\Folder\IFolder as FolderInterface;
use Schoolbox\Mapper;
use Schoolbox\Resource;
use Schoolbox\Resource\Folder\IFolder;
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

    /** @var string */
    protected $nodeType;

    /**
     * Which tab gets highlighted?
     *
     * @var string
     */
    protected $tab = 'resources';

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
     * @param string $nodeType
     * @return self
     */
    public function setNodeType($nodeType)
    {
        $this->nodeType = $nodeType;
        return $this;
    }


    /**
     *
     * Note this is an abstract method and should only be used for abstract implementation
     *
     * @param int $folderId
     * @return array
     * @throws Resource\Exception
     */
    protected function getFolderData($folderId)
    {
        $access = $this->getAccess($folderId);

        // special case for root, just let that through to the keeper
        if ($access < UserAccess::ACCESS_READ && $folderId != 0) {
            throw new Resource\Exception("No access to requested folder");
        }

        // retrieve folder data
        $folder = $this->getFolder($folderId);

        try {
            // attempt to compile an ancestry for this folder, but if it doesn't work, that's ok
            $tree = $folder->getParent() instanceof IFolder ? $this->getBreadcrumbs($folder->getParent()) : [];
        } catch (Resource\Exception $e) {
            $tree = [];
        }

        return [
            'tree'      => $tree,
            'folder'    => $folder,
            'nodeType'  => $this->treeBuilder->getNodeType($folder),
            'access'    => $access,
            'user'      => $this->getUser(),
            'conf'      => $this->conf,
            'namespace' => $this->namespace,
        ];
    }


    /**
     * Helper function for getting a folder's attachzone
     *
     * @return array
     */
    private function getFolderAttachzoneData(): array
    {
        $zone = new GenericZone('cms', $this->fileMapper);
        $zone->setAttachmentsLabel('')
             ->setAttachPrompt('upload files to this location')
             ->setMimeFilter('*/*')
             ->setMulti(true)
             ->setThumbnailSize(64)
             ->setModalTitle('Select file');
        return $zone->getTwigContext();
    }


    /**
     * @param FolderInterface $folder
     * @return array
     */
    protected function getBreadcrumbs(FolderInterface $folder)
    {
        try {
            $node = $this->nodeFactory->build($this->nodeType, $folder->getId());
            $tree = $node->render();

            return $tree['breadcrumbs'];

        } catch (InvalidArgumentException $e) {
            return [];
        }
    }

    /**
     * Display embeddable file picker
     * @param $sourceSlug
     */
    public function picker($sourceSlug)
    {
        $mime = $this->getRequest()->query['mime'];

        if (empty($mime)) {
            $mime = "*/*";
        }

        $folderId = $this->getSession()->get('resourcesID', 0, $this->namespace);

        try {
            $data = $this->getFolderData($folderId);

            // add files to that
            $data['files'] = $this->fileMapper->findByFolderId($folderId)->getAll();
            $data['treedata'] = json_encode($this->treeBuilder->buildPartialTree($folderId));
            $data['namespace'] = $this->namespace;
        }
        catch (\Exception $e) {
            //Reset folderid to try and avoid error
            $folderId = 0;
            $this->getSession()->set('resourcesID', $folderId, $this->namespace);
            $data = $this->getFolderData($folderId);

            // add files to that
            $data['files'] = $this->fileMapper->findByFolderId($folderId)->getAll();
            $data['treedata'] = json_encode($this->treeBuilder->buildPartialTree($folderId));
            $data['namespace'] = $this->namespace;

        }

        $data['site_location'] = \Config::get('site_location');
        $data['site_version'] = \Config::get('site_version');
        $data['source_slug'] = $sourceSlug;
        $data['mime'] = $mime;

        $html = $this->buildUnthemedHtmlOutput('@resourceBrowser/picker.twig', $data);
        $this->getResponse()->content->set($html);
    }

    /**
     * Displays 3-panel browser
     *
     * @api GET /resources(/:folderId)
     * @since 150
     *
     * @param int $folderId
     */
    public function browse($folderId = 0)
    {
        // if a folder ID was supplied, stash that in session then redirect
        if ($folderId) {
            $this->getSession()->set('resourcesID', $folderId, $this->namespace);

            $this->getResponse()->redirect->to(
                $this->getRouter()->generate("{$this->namespace}.browse", array())
            );
            return;
        }

        $folderId = $this->getSession()->get('resourcesID', 0, $this->namespace);

        try {
            $data = $this->getFolderData($folderId);

            // add files to that
            $data['files'] = $this->fileMapper->findByFolderId($folderId)->getAll();
            $data['treedata'] = json_encode($this->treeBuilder->buildPartialTree($folderId));
            $data['namespace'] = $this->namespace;
        }
        catch (\Exception $e) {
            //Reset folderid to try and avoid error
            $folderId = 0;
            $this->getSession()->set('resourcesID', $folderId, $this->namespace);
            $data = $this->getFolderData($folderId);

            // add files to that
            $data['files'] = $this->fileMapper->findByFolderId($folderId)->getAll();
            $data['treedata'] = json_encode($this->treeBuilder->buildPartialTree($folderId));
            $data['namespace'] = $this->namespace;

        }

        $data['attachzone'] = $this->getFolderAttachzoneData();

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@resourceBrowser/browse.twig', $data)->setTab($this->tab)
        );
    }


    /**
     * @api GET /resources/:folderId/nodes
     * @since 155
     *
     * @param int $folderId
     */
    public function listNodes($folderId)
    {
        $mime = $this->getRequest()->query["mime"];

        if (empty($mime)) {
            $mime = "*/*";
        }

        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => $this->treeBuilder->buildChildNodes($folderId, [], $mime),
        ]);
    }


    /**
     * @api GET /resources/:folderId/children
     * @since 150
     *
     * @param int $folderId
     */
    public function listResources($folderId)
    {
        $data = [
            'files'     => $this->fileMapper->findByFolderId($folderId)->getAll(),
            'namespace' => $this->namespace,
        ];

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/listResources.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
    }


    /**
     * @api GET /resources/:folderId/info
     * @since 150
     *
     * @param int $folderId
     */
    public function showFolderInfo($folderId)
    {
        $data = $this->getFolderData($folderId);
        $data['attachzone'] = $this->getFolderAttachzoneData();
        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/showFolderInfo.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
    }


    /**
     * @api GET /resources/file/:fileId/info
     * @since 150
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
            'type'   => 'file',
            'tree'   => $this->getBreadcrumbs($file->getFolder()),
            'host'   => sprintf('http%s://%s', $server->get('HTTPS') ? 's' : '', $server->get('HTTP_HOST')),
            'access' => $this->getAccess($folderId),
            'namespace' => $this->namespace,
            'RETURN_URL'=> Redirect::RETURN_URL . '=' . Redirect::encode("/resources/$folderId"),
        ];

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/showFileInfo.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
    }

    /**
     * @api GET /resources/file/:fileId/ajax
     * @since 150
     *
     * @param int $fileId
     */
    public function getFileInfo($fileId)
    {
        $file = $this->getFile($fileId);

        $data = [
            'title' => $file->getFilename(),
            'mimeType' => $file->getMime(),
            'filesize' => $file->getSize(),
        ];

        $this->getResponse()->content->set(
            $this->buildJsonOutput($data)
        );
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
            'access' => Access::getInstance()->getMinFolderAccess($this->getUser(), $folderIds),
            'namespace' => $this->namespace,
        ];

        $this->getResponse()->content->set([
            'template' => '@resourceBrowser/showMultiFileInfo.twig',
            'format'   => 'nochrome',
            'data'     => $data,
        ]);
    }


    /**
     * Post a whole set of new files into the folder.
     *
     * @api POST /resources/folder/:folderId/files
     * @version 19.1
     *
     * @param int $folderId
     */
    public function postFiles($folderId): void
    {
        $folder  = $this->folderMapper->findById($folderId);
        $factory = new Resource\Attachment\Collector\CollectorFactory($this->fileMapper);
        $results = $factory->build($this->request->post->get('attachment'))->getAll();

        /** @var Resource\AbstractFile[] $results */
        foreach ($results as $file) {
            // Do something with the file
            // Different files have different attributes
            $file
                ->setOwner($this->getUser())
                ->setFolder($folder)
                ->setTitle($file->getStorage()->getFilename());
        }
        $this->fileMapper->save($results);

        // Output something: I guess whatever the contents of the folder are now
        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => $this->treeBuilder->buildChildNodes($folderId),
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
            try {
                $file->setOwner($this->userMapper->findById($file->getOwner()->getId()));
            } catch (Mapper\Exception $e) {
                $file->setOwner($this->userMapper->createModel());
            }
        }

        return $file;
    }

    /**
     * @param int $folderId
     * @return FolderInterface
     */
    protected function findFolder($folderId)
    {
        try {
            return $this->getFolder($folderId);

        } catch (Mapper\Exception\Deleted $e) {
            // Deleted: load the parent (recursively)
            return $this->findFolder($e->getDeletedObject()->getParent()->getId());

        } catch (Mapper\Exception $e) {
            return $this->folderMapper->createModel()->setId(0);

        }
    }


    /**
     * @param int $folderId
     * @return FolderInterface
     * @throws Mapper\Exception
     */
    protected function getFolder($folderId)
    {
        return $this->folderMapper->findById($folderId);
    }

    /**
     * Implements access right checks for the given folder type
     * @param int $folderId
     * @return int
     */
    abstract protected function getAccess($folderId);

    /**
     * @param  string $tab
     * @return $this
     */
    public function setTab($tab)
    {
        $this->tab = $tab;
        return $this;
    }
}
