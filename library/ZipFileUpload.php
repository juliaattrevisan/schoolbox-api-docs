<?php

/**
 * ZipFileUpload
 *
 * Upload a zip file containing a folder structure with files in it. Extract the
 * files to the specified extractTo directory (usually /tmp by default) with a
 * prefix ('portal_zip_upload')
 *
 * Recursively reads the contents of the zip and also recursively unlinks the
 * stuff that was extracted.
 *
 * Creates Categorys as required (synonymous with folders)
 */

use \Schoolbox\Error\Mapper as ErrorMapper;

class ZipFileUpload extends DeprecatedModel
{

    /**
     * Temporary location
     *
     * @var string
     */
    private $extractTo;

    /**
     * Zip file prefix
     *
     * @var string
     */
    private $prefix = 'portal_zip_upload_';

    /**
     * Path to extract into
     *
     * @var string
     */
    private $path = '';

    /**
     * Show what is being read and processed
     *
     * @var bool
     */
    private $verbose = false;

    /**
     * A list of files which didn't get added due to errors
     *
     * @var array
     */
    private $errorFiles = array();

    /**
     * Constructor
     * Set up paths and clean up any existing junk
     *
     * @param string $extractTo
     */
    public function __construct($extractTo = '')
    {
        parent::__construct();

        $this->extractTo = (string)(trim($extractTo) ?: sys_get_temp_dir());

        if (strlen($this->extractTo) == 0) {
            throw new RuntimeException("No extraction destination available");
        }

        $this->path = $this->extractTo . '/' . $this->prefix . uniqid();

        /* Prepare folder: if folder already exists, unlink all the stuff in it
		 * and start it again
		 */
        $this->cleanup();
    }

    /**
     * Gets the path that stuff will be extracted into
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
    * Use verbose mode
    */
    public function setVerbose()
    {
        $this->verbose = true;
    }


    /**
     * Extract files to the path specified. Returns a dirhandle if successfully
     * extracted files.
     *
     * @param ZipArchive $archive
     * @return resource
     */
    public function extract(ZipArchive $archive)
    {
        @$extractedFiles = $archive->extractTo($this->getPath());

        if (!$extractedFiles) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_ZIP_EXTRACT);
        }

        return opendir($this->getPath());
    }


    /**
     * Traverse directory structure. Create Categorys and Files accordingly,
     * with the permissions specified
     *
     * @param string $dir
     * @param int    $parentID
     * @param array  $perms
     * @param int    $inherit
     * @throws \Schoolbox\Mapper\Exception
     */
    public function traverse($dir, $parentID, array $perms, $inherit = 0)
    {
        if (!$dh = @opendir($dir)) {
            return;
        }

        $storageMapper    = \Schoolbox\Resource\Storage\Mapper::getInstance();
        $fileMapper       = \Schoolbox\Resource\File\Mapper::getInstance();
        $folderMapper     = \Schoolbox\Resource\Folder\Mapper::getInstance();
        $roleMapper       = \Schoolbox\RoleMapper::getInstance();
        $roleAccessMapper = \Schoolbox\Permission\Access\RoleAccessMapper::getInstance();

        /* Begin traversal
		 */
        while (false !== ($obj = readdir($dh))) {
            if (($obj == '.') || ($obj == '..')) {
                continue;
            }

            $currentPath = $dir . '/' . $obj;

            $parent = $folderMapper->createModel();
            $parent->setId($parentID);

            /* Create category for the current folder, with POSTed
             * permissions
             */
            if (is_dir($currentPath)) {

                $category = $folderMapper->createModel();

                if ($parent->getId() != 0 && $inherit == '1') {
                    $category->setInherit(true);
                }
                $category->setName($obj);
                $category->setParent($parent);
                $folderMapper->save($category);

                foreach ($perms as $id => $perm) {
                    try {
                        $roleAccess = $roleAccessMapper->createModel();
                        $roleAccess->setCategory($category);
                        $roleAccess->setRole($roleMapper->findById($id));
                        $roleAccess->setType($perm);
                        $roleAccessMapper->save($roleAccess);

                    } catch (\Exception $e) {
                        // noop
                    }
                }

                if ($this->verbose) {
                    echo "Creating $currentPath with {$category->getId()}, corresponding to $obj<br>";
                }

                /* If it's a directory, create a Category for it and remember
                 * the new id corresponding to the path. Enter it and deal with
                 * any stuff inside
                 *
                 * Otherwise, it's a file, and we add it to the parent Category
                 */
                $this->traverse($currentPath, $category->getId(), $perms);

            } else {
                // Ignore filenames starting with a . (these are usually
                // hidden or system files, and should be ignored)
                if (substr($obj, 0, 1) === '.') {
                    continue;
                }

                try {
                    // accept file, with perms checking
                    $storage = $storageMapper->accept($obj, $currentPath, false);

                    /* For the File table
                     */
                    $file = $fileMapper->createModel()
                        ->setFolder($parent)
                        ->setOwner($_SESSION['user']) // we dun need full hydr8
                        ->setTitle($obj)
                        ->setFilename(pathinfo($obj, PATHINFO_FILENAME))
                        ->setStorage($storage);

                    if ($this->verbose) {
                        echo "Creating <strong>$obj</strong> in $parentID<br>";
                    }

                    $fileMapper->save($file);

                } catch (\Schoolbox\File\Exception $e) {
                    $this->errorFiles[] = array(
                        'file'    => $obj,
                        'code'    => $e->getCode(),
                        'message' => $e->getMessage()
                    );

                    if ($this->verbose) {
                        echo "Error: could not make <strong>$obj</strong> in $parentID<br>";
                    }

                }
                catch (\RuntimeException $e) {
                    // because common interfaces would make this too easy...
                    $this->errorFiles[] = array(
                        'file'    => $obj,
                        'code'    => $e->getCode(),
                        'message' => $e->getMessage()
                    );

                    if ($this->verbose) {
                        echo "Error: could not make <strong>$obj</strong> in $parentID<br>";
                    }

                }
            }
        }

        closedir($dh);

        return;
    }


    /**
     * Remove temp folder that was extracted. Or you can feed in a list of
     * folders to remove. It will prefix this list with the extractTo value for
     * a bit of safety.
     *
     * @param bool $existing
     */
    public function cleanup($existing = false)
    {
        if (is_array($existing)) {
            foreach ($existing as $currentPath) {
                $this->unlinkRecursive($this->extractTo . '/' . $currentPath, true);
            }
        } else {
            if (is_dir($this->getPath())) {
                $this->unlinkRecursive($this->getPath(), true);
            }
        }
    }

    /**
    * Recursively delete a directory
    *
    * @param string $dir           Directory name
    * @param bool   $deleteRootToo Delete specified top-level directory as well
    *
    * Taken from php.net
    */
    public function unlinkRecursive($dir, $deleteRootToo)
    {
        if (!$dh = @opendir($dir)) {
            return;
        }

        while (false !== ($obj = readdir($dh))) {
            if ($obj == '.' || $obj == '..') {
                continue;
            }

            if (!@unlink($dir . '/' . $obj)) {
                $this->unlinkRecursive($dir . '/' . $obj, true);
            }
        }

        closedir($dh);

        if ($deleteRootToo) {
            @rmdir($dir);
        }

        return;
    }

    /**
    * Get error data for files which couldn't be uploaded
    */
    public function getErrors()
    {
        $errorMessages = array();
        $query = 'SELECT message FROM error WHERE id = 9';
        $error = $this->db->getRow($query);

        if (count($this->errorFiles)) {
            foreach ($this->errorFiles as $currentFile) {
                $errorMessages[] = array(
                    'file' => $currentFile['file'],
                    'message' => str_replace('%MESSAGE%', $currentFile['message'], $error['message']),
                );
            }
        }

        return $errorMessages;
    }
}
