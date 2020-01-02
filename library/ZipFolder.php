<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;
use \Schoolbox\Resource\Folder;
use \Schoolbox\Resource\File;

/**
 * Puts all resources from a folder into a zip
 */
class ZipFolder
{
    /**
     * @var DeprecatedDatabase
     */
    private $db;
    /**
     * @var int
     */
    private $folder;
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
    private $allowedChars = "[^0-9a-zA-Z()_-]";
    /**
     * @var int
     */
    private $size = 0;
    /**
     * Max folder depth of zip
     * @var int
     */
    private $maxDepth = 5;
    /**
     * Max size of zip file 1GB
     *
     * @var int
     */
    private $maxSize = 1073741824;
    /**
     * @var Group
     */
    private $groupHandler;
    /**
     * @var Folder\Mapper
     */
    private $folderMapper;
    /**
     * @var File\Mapper
     */
    private $fileMapper;
    /**
     * @var Folder\Model|null
     */
    private $folderDetails;


    /**
     * ZipFolder constructor.
     * @param $folder
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function __construct($folder)
    {
        $this->db        = DeprecatedDatabase::getInstance();
        $this->folder    = $folder;
        $this->tmpName   = tempnam(Config::get('zip_dir'), 'zip');
        $this->tmpFolder = $this->tmpName . 'dir';
        $this->tmpZip    = $this->tmpName . '.zip';

        $this->groupHandler  = new Group;
        $this->folderMapper  = new Folder\Mapper();
        $this->fileMapper    = new File\Mapper();
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

    public function __destruct()
    {
        // Might be a bit redundant, but can't hurt anything

        $this->cleanUp();
    }

    private function getResources($folder, $permission)
    {
        return $this->fileMapper->findForFolders(
            [$folder],
            ['getHidden' => ($permission >= UserAccess::ACCESS_WRITE)]
        );
    }

    private function getFolders($folderId)
    {
        return $this->folderMapper->findByParent($folderId);
    }

    private function cleanName($name)
    {
        $filename = preg_replace("/{$this->allowedChars}/", "_", $name);
        return $filename;
    }

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

    public function sendZip()
    {
        if ($this->createZip()) {
            $filename = $this->cleanName($this->folderDetails->getName());

            $filesize = filesize($this->tmpZip);
            header("Pragma: ");
            header("Cache-control: ");
            header("Content-Type: application/x-zip-compressed");
            header("Content-Disposition: attachment; filename=\"" . $filename . ".zip\"");
            header("Content-Length: $filesize");
            readfile($this->tmpZip);

            $this->cleanUp();
        } else {
            error(49);
        }
    }

    private function createDirectory($currentDir, $parentFolder, $permission, $depth = 0)
    {
        //populate dir with files
        $fileList = $this->getResources($parentFolder->getId(), $permission);

        foreach ($fileList as $file) {
            if ($file->getStorage()->exists()) {
                $nextSize = $this->size + $file->getSize();
                if ($this->maxSize > $nextSize) {
                    $this->size = $this->size + $file->getSize();
                    $fileName   = $this->getTargetPath($currentDir, $file->getBasename());
                    $fp         = fopen($fileName, 'wb');
                    stream_copy_to_stream($file->getStorage()->readStream(), $fp);
                    fclose($fp);
                } else {
                    error(Schoolbox\Error\Mapper::ERROR_FILE_SIZE, 'The maximum download size is ' . \Alaress_Byte_Format::bytesToString($this->maxSize) . '.');
                }
            }
        }


        //dont recurse deeper than max depth
        if ($this->maxDepth > $depth) {
            //get subfolders and recurse through them
            $folderList = $this->getFolders($parentFolder->getId());
            foreach ($folderList as $folder) {
                $permission = $this->getPermission($folder->getId());
                if ($permission > UserAccess::ACCESS_DENY) {
                    $folderName     = $this->cleanName($folder->getName());
                    $folderLocation = $currentDir . '/' . $folderName;
                    mkdir($folderLocation);
                    $this->createDirectory($folderLocation, $folder, $permission, $depth + 1);
                }
            }
        }
    }

    public function createZip()
    {
        $archive = new ZipArchive();
        $archive->open($this->tmpZip, ZipArchive::CREATE);
        mkdir($this->tmpFolder);
        $this->createDirectory(
            $this->tmpFolder,
            $this->folderDetails,
            $this->getPermission($this->folderDetails->getId())
        );
        $dir = new RecursiveDirectoryIterator($this->tmpFolder, FilesystemIterator::SKIP_DOTS);

        $i      = 0;
        $ulimit = 250;
        foreach (new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST) as $file) {
            // Close/open archive after 250 additions, to avoid ulimit issues
            if ($i++ % $ulimit === ($ulimit - 1)) {
                $archive->close();
                $archive->open($this->tmpZip);
            }

            /** @var SplFileInfo $file */
            // Chop parts of path that match $this->tmpFolder
            $filePath = $file->getPath() . '/' . $file->getFilename();
            $zipPath  = substr($filePath, strlen($this->tmpFolder) + 1);
            if ($file->isDir()) {
                $archive->addEmptyDir($zipPath);
            } elseif ($file->isFile()) {
                $archive->addFile($filePath, $zipPath);
            }
        }
        $archive->close();
        return true;

    }

    public function cleanUp()
    {
        if (file_exists($this->tmpName)) {
            unlink($this->tmpName);
        }

        if (file_exists($this->tmpZip)) {
            unlink($this->tmpZip);
        }

        if (file_exists($this->tmpFolder)) {
            $this->removeDir($this->tmpFolder);
        }
    }

    private function removeDir($path)
    {
        // Add trailing slash to $path if one is not there
        if (substr($path, -1, 1) != "/") {
            $path .= "/";
        }
        foreach (glob($path . "*") as $file) {
            if (is_file($file) === true) {
                // Remove each file in this Directory
                unlink($file);
            } elseif (is_dir($file) === true) {
                // If this Directory contains a Subdirectory, run this Function on it
                $this->removeDir($file);
            }
        }
        // Remove Directory once Files have been removed (If Exists)
        if (is_dir($path) === true) {
            rmdir($path);
        }
    }

    /**
     * Gets a db row representing the given category id
     *
     * @param int $folder_id
     * @return Folder\Model
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getFolderDetails($folder_id): ?\Schoolbox\Resource\Folder\Model
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
     * @param $folder_id
     * @return UserAccess::ACCESS_DENY|UserAccess::ACCESS_READ|UserAccess::ACCESS_WRITE|UserAccess::ACCESS_FULL
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
