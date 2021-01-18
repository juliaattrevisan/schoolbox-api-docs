<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Zip;

use Config;
use Group;
use RuntimeException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder as ResourceFolder;
use Schoolbox\Resource\File as ResourceFile;
use UnexpectedValueException;
use ZipStream;

/**
 * Load all resources from a folder into a ZIP file, and then stream that ZIP to
 * standard output.
 */
class Folder
{
    /**
     * @var false|string
     */
    private $tmpName;


    /**
     * @var string
     */
    private $tmpFolder;


    /**
     * @var string
     */
    private $tmpZip;


    /**
     * @var string
     */
    private $allowedChars = '[^0-9a-zA-Z()_-]';


    /**
     * Max folder depth of zip
     * @var int
     */
    private $maxDepth = 5;


    /**
     * @var Group
     */
    private $groupHandler;


    /**
     * @var ResourceFolder\Mapper
     */
    private $folderMapper;


    /**
     * @var ResourceFile\Mapper
     */
    private $fileMapper;


    /**
     * @var ResourceFolder\Model|null
     */
    private $folderDetails;


    /**
     * ZipFolder constructor.
     *
     * @param int $folder
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function __construct(int $folder)
    {
        $this->tmpName   = tempnam(Config::get('zip_dir'), 'zip');
        $this->tmpFolder = $this->tmpName . 'dir';
        $this->tmpZip    = $this->tmpName . '.zip';

        $this->groupHandler  = new Group;
        $this->folderMapper  = new ResourceFolder\Mapper();
        $this->fileMapper    = new ResourceFile\Mapper();
        $this->folderDetails = $this->getFolderDetails($folder);

        /* If you can't get details about $folder (it might not exist/be deleted)
         * then folderDetails will be null; prevent NPE in following statements
         */
        if (null === $this->folderDetails) {
            error(13);
        }

        if (is_numeric($this->folderDetails->getId())) {
            if ($this->getPermission($this->folderDetails->getId()) < UserAccess::ACCESS_READ) {
                error(5);
            }
        } else {
            error(13);
        }
    }


    /**
     * @param int $folder
     * @param int $permission
     * @return \Schoolbox\Collection|ResourceFile\Model[]
     */
    private function getResources($folder, $permission)
    {
        return $this->fileMapper->findForFolders(
            [$folder],
            ['getHidden' => ($permission >= UserAccess::ACCESS_WRITE)]
        );
    }


    /**
     * @param int $folderId
     * @return \Schoolbox\GenericCollection|ResourceFolder\Model[]
     */
    private function getFolders($folderId)
    {
        return $this->folderMapper->findByParent($folderId);
    }


    /**
     * @param string $name
     * @return string|string[]|null
     */
    private function cleanName($name)
    {
        return preg_replace("/{$this->allowedChars}/", '_', $name);
    }


    /**
     * @param string $fileName
     * @param int    $depth
     * @return mixed
     */
    private function uniqueFileName($fileName, $depth = 1)
    {
        if (is_file($fileName)) {
            $pathParts = pathinfo($fileName);
            $newName   = $pathParts['dirname'] . '/' . $pathParts['filename'] . '_' . $depth . '.' . $pathParts['extension'];
            return $this->uniqueFileName($newName, ++$depth);
        } else {
            return $fileName;
        }
    }


    /**
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function sendZip()
    {
        $zipOptions = new ZipStream\Option\Archive();
        $zipOptions->setSendHttpHeaders(true);
        $zipStream = new ZipStream\ZipStream($this->cleanName($this->folderDetails->getName()) . '.zip', $zipOptions);

        // Iterate the folder, its sub-folders and its files
        $this->iterateFolder($zipStream, $this->folderDetails, []);

        $zipStream->finish();
    }


    /**
     * Iterate the given folder, and add any files and sub-folders which the
     * user has permission to view into the ZIP stream.
     *
     * @param ZipStream\ZipStream       $zipStream The ZIP to add files to
     * @param ResourceFolder\Model|null $folder    The Schoolbox folder to add
     *                                             files from
     * @param array                     $pathParts The path in the ZIP to add
     *                                             files to
     */
    private function iterateFolder(ZipStream\ZipStream $zipStream, ?ResourceFolder\Model $folder, array $pathParts)
    {
        $path = implode(DIRECTORY_SEPARATOR, $pathParts);

        // Load all files
        $fileList = $this->getResources($folder->getId(), $this->getPermission($folder->getId()));
        foreach ($fileList as $file) {
            if ($file->getStorage()->exists()) {
                $zipStream->addFileFromStream(
                    $path . DIRECTORY_SEPARATOR . $file->getBasename(),
                    $file->readStream()
                );
            }
        }

        // dont recurse deeper than max depth
        if ($this->maxDepth >= count($pathParts)) {
            // get subfolders and recurse through them
            $childFolderList = $this->getFolders($folder->getId());
            foreach ($childFolderList as $childFolder) {
                $permission = $this->getPermission($childFolder->getId());
                if ($permission > UserAccess::ACCESS_DENY) {
                    $this->iterateFolder(
                        $zipStream,
                        $childFolder,
                        array_merge($pathParts, [$this->cleanName($childFolder->getName())])
                    );
                }
            }
        }
    }


    /**
     * Gets a db row representing the given category id
     *
     * @param int $folder_id
     * @return ResourceFolder\Model
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getFolderDetails(int $folder_id): ?ResourceFolder\Model
    {
        try {
            return $this->folderMapper->findById($folder_id);
        } catch (\Schoolbox\Mapper\Exception $e) {
        }

        return null;
    }

    /**
     * Gets a Group permission level for the given folder
     *
     * @param int $folder_id
     * @return int UserAccess::ACCESS_DENY|UserAccess::ACCESS_READ|UserAccess::ACCESS_WRITE|UserAccess::ACCESS_FULL
     */
    protected function getPermission($folder_id)
    {
        return $this->groupHandler->getPermission($folder_id);
    }


    /**
     * Gets a target path the file should be copied to
     *
     * @param string $currentDir Parent directory the file should be copied to
     * @param string $file       Real full filename
     * @return string
     */
    protected function getTargetPath($currentDir, $file)
    {
        $fileName = $currentDir . '/' . $file;
        return $this->uniqueFileName($fileName); //ensure filename is unique else puts numbers in filename
    }
}
