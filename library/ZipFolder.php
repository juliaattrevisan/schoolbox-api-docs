<?php
// Copyright 2012 Alaress Pty Ltd. http://alaress.com.au

/**
 * Puts all resources from a folder into a zip
 */
class ZipFolder
{

    function ZipFolder($folder)
    {
        $this->db = DeprecatedDatabase::getInstance();
        $this->folder = $folder;
        $this->tmpName = tempnam(Cfg::get('zip_dir'), 'zip');
        $this->tmpFolder = $this->tmpName . 'dir';
        $this->tmpZip = $this->tmpName . '.zip';
        $this->allowedChars = "[^0-9a-zA-Z()_-]";
        $this->size = 0;
        $this->maxDepth = 5;
        $this->maxSize = 1073741824; //1GB

        $this->groupHandler = new Group;
        $this->folderMapper = new \Schoolbox\Resource\Folder\Mapper();
        $this->fileMapper = new \Schoolbox\Resource\File\Mapper();



        $this->folderDetails = $this->getFolderDetails($folder);

        if (is_numeric($this->folderDetails->getId())) {
            if ($this->getPermission($this->folderDetails->getId()) < READ_ACCESS) {
                error(5);
            }
        } else {
            error(13);
        }
    }


    function getResources($folder, $permission)
    {
        return $this->fileMapper->findForFolders(
            array($folder),
            array('getHidden' => ($permission >= WRITE_ACCESS))
        );
    }

    function getFolders($folder)
    {
        return $this->folderMapper->findByParent($folder);
    }

    function cleanName($name)
    {
        $filename = preg_replace("/{$this->allowedChars}/", "_", $name);
        return $filename;
    }

    function uniqueFileName($fileName, $depth = 1)
    {
        if (is_file($fileName)) {
            $pathParts = pathinfo($fileName);
            $newName = $pathParts['dirname'] . '/' . $pathParts['filename'] . '_' . $depth . '.' . $pathParts['extension'];
            return $this->uniqueFileName($newName, ++$depth);
        } else {
            return $fileName;
        }
    }

    function sendZip()
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

    function createDirectory($currentDir, $parentFolder, $permission, $depth = 0)
    {
        //populate dir with files
        $fileList = $this->getResources($parentFolder->getId(), $permission);

        foreach ($fileList as $file) {
            if ($file->getStorage()->exists()) {
                $this->size = $this->size + $file->getSize();
                $fileName = $this->getTargetPath($currentDir, $file->getBasename());
                $fp = fopen($fileName, 'wb');
                stream_copy_to_stream($file->getStorage()->readStream(), $fp);
                fclose($fp);
            }
        }


        //dont recurse deeper than max depth or max size
        if ($this->maxDepth > $depth && $this->maxSize > $this->size) {
            //get subfolders and recurse through them
            $folderList = $this->getFolders($parentFolder->getId());
            foreach ($folderList as $folder) {
                $permission = $this->getPermission($folder->getId());
                if ($permission > DENY_ACCESS) {
                    $folderName = $this->cleanName($folder->getName());
                    $folderLocation = $currentDir . '/' . $folderName;
                    mkdir($folderLocation);
                    $this->createDirectory($folderLocation, $folder, $permission, $depth+1);
                }
            }
        }
    }

    function createZip()
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
            if ($i++ % $ulimit === ($ulimit-1)) {
                $archive->close();
                $archive->open($this->tmpZip);
            }

            /** @var SplFileInfo $file */
            // Chop parts of path that match $this->tmpFolder
            $filePath = $file->getPath() . '/' . $file->getFilename();
            $zipPath  = substr($filePath, strlen($this->tmpFolder)+1);
            if ($file->isDir()) {
                $archive->addEmptyDir($zipPath);
            } elseif ($file->isFile()) {
                $archive->addFile($filePath, $zipPath);
            }
        }
        $archive->close();
        return true;

    }

    function cleanUp()
    {
        unlink($this->tmpName);
        unlink($this->tmpZip);
        $this->removeDir($this->tmpFolder);
    }

    function removeDir($path)
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
     * @param $folder_id
     * @return array|bool
     */
    protected function getFolderDetails($folder_id)
    {
        try {
            $category = $this->folderMapper->findById($folder_id);
            return $category;
        } catch (\Exception $e) {
        }
    }

    /**
     * Gets a Group permission level for the given folder
     *
     * @param $folder_id
     * @return DENY_ACCESS|READ_ACCESS|WRITE_ACCESS|FULL_ACCESS
     */
    protected function getPermission($folder_id)
    {
        return $this->groupHandler->getPermission($folder_id);
    }


    /**
     * Gets a target path the file should be copied to
     *
     * @param string $currentDir Parent directory the file should be copied to
     * @param string $file Real full filename
     * @return string
     */
    protected function getTargetPath($currentDir, $file)
    {
        $fileName = $currentDir . '/' . $file;
        return $this->uniqueFileName($fileName); //ensure filename is unique else puts numbers in filename
    }
}
