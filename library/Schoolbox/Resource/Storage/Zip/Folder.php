<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Zip;

use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder as ResourceFolder;
use Schoolbox\Resource\File as ResourceFile;
use Schoolbox\User;
use ZipStream;

/**
 * Load all resources from a folder into a ZIP file, and then stream that ZIP to
 * standard output.
 */
class Folder
{
    /**
     * Allowable characters in file and folder names.
     */
    private const ALLOWED_CHARS_REGEX = '/[^0-9\s\\.a-zA-Z()_-]/';

    /**
     * Max folder depth of zip that this class may create.
     */
    private const MAX_DEPTH = 5;

    /**
     * Folder mapper: this class needs to load sub-folders of `$this->folder`.
     */
    private ResourceFolder\Mapper $folderMapper;

    /**
     * File mapper: this class needs to load files within `$this->folder`.
     */
    private ResourceFile\Mapper $fileMapper;

    /**
     * The folder to download.
     */
    private ResourceFolder\Model $folder;

    /**
     * The currently logged-in user.
     */
    private User $currentUser;

    /**
     * The folder permission access class.
     */
    private Access $access;

    /**
     * Constructor
     *
     * @param ResourceFolder\Model  $folder
     * @param User                  $currentUser
     * @param Access                $access
     * @param ResourceFolder\Mapper $folderMapper
     * @param ResourceFile\Mapper   $fileMapper
     */
    public function __construct(
        ResourceFolder\Model $folder,
        User $currentUser,
        Access $access,
        ResourceFolder\Mapper $folderMapper,
        ResourceFile\Mapper $fileMapper
    ) {
        $this->folder       = $folder;
        $this->currentUser  = $currentUser;
        $this->access       = $access;
        $this->folderMapper = $folderMapper;
        $this->fileMapper   = $fileMapper;
    }

    /**
     * Recursively adds files and sub-folders in `$this->folder` to the ZIP, and
     * then streams that ZIP to the browser.
     *
     * @throws ZipStream\Exception\OverflowException
     */
    public function sendZip(): void
    {
        $zipOptions = new ZipStream\Option\Archive();
        $zipOptions->setSendHttpHeaders(true);
        $zipStream = new ZipStream\ZipStream($this->cleanName($this->folder->getName()) . '.zip', $zipOptions);

        // Iterate the folder, its sub-folders and its files
        $this->iterateFolder($zipStream, $this->folder, []);

        $zipStream->finish();
    }


    /**
     * Iterate the given folder, and add any files and sub-folders which the
     * user has permission to view into the ZIP stream.
     *
     * @param ZipStream\ZipStream  $zipStream The ZIP to add files to
     * @param ResourceFolder\Model $folder    The folder to add files from
     * @param array                $pathParts The path within the ZIP to add
     *                                        `$folder`'s files and sub-folders
     */
    private function iterateFolder(ZipStream\ZipStream $zipStream, ResourceFolder\Model $folder, array $pathParts)
    {
        $path        = implode(DIRECTORY_SEPARATOR, $pathParts);
        $uniqueNames = [];

        // Load all files
        $permission = $this->getPermission($folder->getId());
        if ($permission < UserAccess::ACCESS_READ) {
            return;
        }
        $fileList = $this->fileMapper->findForFolders(
            [$folder->getId()],
            ['getHidden' => $permission >= UserAccess::ACCESS_WRITE]
        );
        foreach ($fileList as $file) {
            // Ensure filenames are unique
            if ($file->getStorage()->exists()) {
                $uniqueName    = $this->uniqueFileName($this->cleanName($file->getBasename()), $uniqueNames);
                $uniqueNames[] = $uniqueName;
                $zipStream->addFileFromStream(
                    $path . DIRECTORY_SEPARATOR . $uniqueName,
                    $file->readStream()
                );
            }
        }

        // dont recurse deeper than max depth
        if (self::MAX_DEPTH >= count($pathParts)) {
            // get sub-folders and recurse through them
            foreach ($this->folderMapper->findByParent($folder->getId()) as $childFolder) {
                $permission = $this->getPermission($childFolder->getId());
                if ($permission > UserAccess::ACCESS_DENY) {
                    $uniqueName    = $this->uniqueFileName($this->cleanName($childFolder->getName()), $uniqueNames);
                    $uniqueNames[] = $uniqueName;
                    $this->iterateFolder(
                        $zipStream,
                        $childFolder,
                        array_merge($pathParts, [$uniqueName])
                    );
                }
            }
        }
    }

    /**
     * Gets a Group permission level for the given folder
     *
     * @param int $folderId
     * @return int One of the `UserAccess::ACCESS_*` constants
     */
    private function getPermission(int $folderId): int
    {
        return $this->access->getFolderAccess($this->currentUser, $folderId);
    }

    /**
     * Ensures that file and folder names contain only characters in
     * `self::ALLOWED_CHARS`.
     *
     * TODO determine if name even needs to be cleaned: the previous version of
     *      this class actually created temp files, this one doesn't so this
     *      may not be needed
     *
     * @param string $name
     * @return string
     */
    private function cleanName(string $name): string
    {
        return preg_replace(self::ALLOWED_CHARS_REGEX, '_', $name);
    }

    /**
     * Create a filename that is guaranteed to not be in `$uniqueFileNames`.
     *
     * @param string $basename
     * @param array  $uniqueFileNames
     * @param int    $depth
     * @return string
     */
    private function uniqueFileName(string $basename, array $uniqueFileNames, int $depth = 1): string
    {
        if (in_array($basename, $uniqueFileNames)) {
            $pathParts = pathinfo($basename);
            $newName   = $pathParts['filename'] . '_' . $depth;
            if (isset($pathParts['extension'])) {
                $newName .= '.' . $pathParts['extension'];
            }
            return $this->uniqueFileName($newName, $uniqueFileNames, ++$depth);
        }
        return $basename;
    }
}
