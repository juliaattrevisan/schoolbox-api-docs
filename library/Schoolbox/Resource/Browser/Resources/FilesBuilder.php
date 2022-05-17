<?php

/**
 *
 * @author      Justin Clarke <justin.clarke@justinclarke.me>
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\Resources;

use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Router;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Component_InstanceMapper;
use Schoolbox\Resource\File\Model as File;

class FilesBuilder
{
    protected FileMapper $fileMapper;

    protected Component_InstanceMapper $componentInstanceMapper;

    protected Router $router;

    /**
     * FilesBuilder constructor.
     *
     * @param FileMapper               $fileMapper
     * @param Component_InstanceMapper $componentInstanceMapper
     * @param Router                   $router
     */
    public function __construct(FileMapper $fileMapper, Component_InstanceMapper $componentInstanceMapper, Router $router)
    {
        $this->fileMapper              = $fileMapper;
        $this->componentInstanceMapper = $componentInstanceMapper;
        $this->router                  = $router;
    }

    /**
     * get homepageId from the componentID
     *
     * @param int $componentId
     * @return int
     * @throws Mapper\Exception\NotFound
     */
    public function getHomepage(int $componentId): int
    {
        $component = $this->componentInstanceMapper->findByInstanceId($componentId);

        if ($component && $component->getDefinition()->getClass() === \Component_Homepage_FileListController::class) {
            return $component->getIdentifier();
        }
        throw new Mapper\Exception\NotFound();
    }

    /**
     * Get the list of files from a folder, not children just the ones in this folder.
     * We will assume the user has access to this folder
     * and lets build a nice array of the files
     * @param int      $folderId
     * @param int|null $componentId
     * @param int      $folderAccess
     * @return array of files
     * @throws RouteNotFound
     */
    public function getFolderData(int $folderId, ?int $componentId, int $folderAccess = UserAccess::ACCESS_READ): array
    {
        //get the files, for the source folder
        $fileList = $this->fileMapper->findForFolders(
            [$folderId],
            [
                'getHidden' => $folderAccess >= UserAccess::ACCESS_WRITE,
            ]
        );

        //lets build the list folder array, so we don't have to worry about hydration. etc
        $files     = [];
        $returnUrl = $componentId ?
            $this->router->generate('resourceBrowser.showFiles', [
                'componentID'    => $componentId,
                'sourceFolderID' => $folderId,
            ])
            : $this->router->generate('resourceBrowser.showFolderFiles', ['sourceFolderID' => $folderId]);

        $encodedReturnUrl = Redirect::encode($returnUrl);

        foreach ($fileList as $file) {
            $files[] = $this->buildFile($file, $encodedReturnUrl);
        }
        return $files;
    }

    /**
     * find the supplied component config, or build from the default if required
     * returns array (sourceId, title, sorting, direction, filter)
     * @param int $componentId
     * @return array componentConfig
     */
    public function getComponentConf(int $componentId): array
    {
        //get the instance config.
        $instanceMapper = $this->componentInstanceMapper;
        $instanceConfig = $instanceMapper->findByInstanceId($componentId)->getConfigArray();

        //if there is no source, update to the current homepage
        if ('' === trim($instanceConfig['componentname']['value'])) {
            $instanceConfig['componentname']['value'] = $instanceConfig['componentname']['defaultValue'];
        }

        //return instance / default config values
        return $instanceConfig;
    }

    /**
     * Build the return file object, so it is the same for both the getting all files and the uploading a file
     * @param File   $file
     * @param string $returnURL
     * @return array
     * @throws RouteNotFound
     */
    private function buildFile(File $file, string $returnURL): array
    {
        return [
            'name'          => $file->getTitle(),
            'id'            => $file->getId(),
            'url'           => '/send.php?id=' . $file->getId(),
            'mime'          => [
                'mimeType'    => $file->getStorage()->getMimetype()->getMime(),
                'iconFile'    => $file->getStorage()->getMimetype()->getIconFile(),
                'description' => $file->getStorage()->getMimetype()->getDescription(),
                'url'         => '/images/mime/' . $file->getStorage()->getMimetype()->getIconFile(),
            ],
            'hidden'        => $file->isHidden(),
            'type'          => $file->getMimetype()->getType(),
            'hash'          => $file->getHash(),
            'size'          => $file->getSize(),
            'dateModified'  => [
                'timestamp' => $file->getModifiedAt()->getUnixTimeStamp(),
                'formatted' => $file->getModifiedAt()->getShort(),
            ],
            'editUrl'       => '/cms/file/' . $file->getId() . "?" . Redirect::RETURN_URL . "={$returnURL}",
            'visibilityUrl' => $this->router->generate('resourceBrowser.patchFileInfo', ['fileId' => $file->getId()]),
        ];
    }
}
