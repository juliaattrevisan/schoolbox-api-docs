<?php

/**
 *
 * @author      Justin Clarke <justin.clarke@justinclarke.me>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Resources;

use Aura\Router\Router;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Component_InstanceMapper;

class FilesBuilder
{
    private int $componentId;
    protected FileMapper $fileMapper;
    protected Component_InstanceMapper $componentInstanceMapper;
    protected Router $router;

    /**
     * FilesBuilder constructor.
     *
     * @param FileMapper               $fileMapper
     * @param Component_InstanceMapper $componentInstanceMapper
     */
    public function __construct(FileMapper $fileMapper, Component_InstanceMapper $componentInstanceMapper, Router $router)
    {
        $this->fileMapper               = $fileMapper;
        $this->componentInstanceMapper  = $componentInstanceMapper;
        $this->router = $router;
    }

    /**
     * get homepageId from the componentID
     *
     * @param int $componentId
     * @returns int
     * @throws Mapper\Exception\NotFound
     */
    public function getHomepage(int $componentId): int
    {
        $this->componentId = $componentId;
        $component = $this->componentInstanceMapper->findByInstanceId($componentId);

        if ($component && $component->getDefinition()->getClass() === 'Component_Homepage_FileListController') {
            return $component->getIdentifier();
        }
        throw new Mapper\Exception\NotFound();
    }

    /**
     * Get the list of files from a folder, not children just the ones in this folder.
     * We will assume the user has access to this folder
     * and lets build a nice array of the files
     * @param int $folderId
     * @param int $folderAccess
     * @return array of files
     */
    public function getFolderData(int $folderId, int $folderAccess = UserAccess::ACCESS_READ): array
    {
        //get the files, for the source folder
        $fileList = $this->fileMapper->findForFolders(
            [$folderId],
            [
                'getHidden' => $folderAccess >= UserAccess::ACCESS_WRITE,
            ]
        );

        //lets build the list folder array, so we don't have to worry about hydration. etc
        $files = [];
        foreach ($fileList as $file) {
            $files[] = $this->buildFile($file, $this->componentId, $folderId);
        }
        return $files;
    }

    /**
     * find the supplied component config, or build from the default if required
     * returns array (sourceId, title, sorting, direction, filter)
     * @param int $componentId
     * @param int $homepageId
     * @return array componentConfig
     */
    public function getComponentConf(int $componentId, int $homepageId): array
    {
        //get the instance config.
        $instanceMapper = $this->componentInstanceMapper;
        $instanceConfig = $instanceMapper->findByInstanceId($componentId)->getConfigArray();

        //if there is no source, update to the current homepage
        $instanceConfig['src']['value'] = $instanceConfig['src']['value'] ?: $homepageId;
        if ('' === trim($instanceConfig['componentname']['value'])) {
            $instanceConfig['componentname']['value'] =  $instanceConfig['componentname']['defaultValue'];
        }

        //return instance / default config values
        return $instanceConfig;
    }

    /**
     * Build the return file object, so it is the same for both the getting all files and the uploading a file
     * @param \Schoolbox\Resource\File\Model $file
     * @param int                            $componentId
     * @param int                            $folderId
     * @return array
     */
    private function buildFile(\Schoolbox\Resource\File\Model $file, int $componentId, int $folderId): array
    {
        //get return url, for edit page
        $returnURL = Redirect::encode("/resources/folder/{$componentId}/files/{$folderId}");

        return [
            'name'                  => $file->getTitle(),
            'id'                    => $file->getId(),
            'url'                   => '/send.php?id=' . $file->getId(),
            'mime'                  => [
                'mimeType'          => $file->getStorage()->getMimetype()->getMime(),
                'iconFile'          => $file->getStorage()->getMimetype()->getIconFile(),
                'description'       => $file->getStorage()->getMimetype()->getDescription(),
                'url'               => '/images/mime/' . $file->getStorage()->getMimetype()->getIconFile(),
            ],
            'hidden'                => $file->isHidden(),
            'type'                  => $file->getMimetype()->getType(),
            'hash'                  => $file->getHash(),
            'size'                  => $file->getSize(),
            'dateModified'          => [
                'timestamp' => $file->getModifiedAt()->getUnixTimeStamp(),
                'formatted' => $file->getModifiedAt()->getShort(),
            ],
            'editUrl'               => '/cms/file/' . $file->getId() . "?" . Redirect::RETURN_URL . "={$returnURL}",
            'visibilityUrl'         => $this->router->generate('resourceBrowser.patchFileInfo', ['fileId' => $file->getId()])
        ];
    }
}
