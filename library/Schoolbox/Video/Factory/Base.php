<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Video\Factory;

use Component\Eportfolio\FileListMapper;
use Schoolbox\Config\Loader;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\AbstractFile;
use Schoolbox\Video\QueueMapper;
use Schoolbox\Video\ResourceVideo;
use Schoolbox\Video\ResourceVideoMapper;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\My\File\Mapper as MyFileMapper;

/**
 * Class Base
 *
 * Spawn a video object based on its type and identifier
 *
 * @package Schoolbox\Video\Factory
 */
abstract class Base
{
    /**
     * Folder permission access
     */
    protected Access $access;


    /**
     * The current session user
     *
     * @var \Schoolbox\User
     */
    protected \Schoolbox\User $user;


    /**
     * @var ResourceVideoMapper
     */
    protected ResourceVideoMapper $videoMapper;


    /**
     * @var FileMapper
     */
    protected FileMapper $fileMapper;


    /**
     * @var MyFileMapper
     */
    protected MyFileMapper $myFileMapper;


    /**
     * Constructor
     *
     * @param int $id The identifier of the file (could be a hash or numeric index)
     * @param string $type The type of video (submission, sbfs, storage etc)
     */
    public function __construct(protected int $id, protected string $type)
    {
        /* TODO Dependency inject these at some point
         */
        $this->access = Access::getInstance();
        $this->videoMapper = ResourceVideoMapper::getInstance();
        $this->fileMapper = FileMapper::getInstance();
        $this->myFileMapper = MyFileMapper::getInstance();

        /* We're not in Aura, sorry
         */
        $this->user = $_SESSION['user'];
    }


    /**
     * Spawn a video object
     *
     * @return ResourceVideo
     * @throws MapperException
     * @throws Deleted
     * @throws NotFound
     * @throws \PDOException
     * @throws DefinitionException
     */
    public function produce(): ResourceVideo
    {
        switch ($this->type) {
            case QueueMapper::FILE_TYPE_STORAGE:
            case QueueMapper::FILE_TYPE_SUBMISSION:
                return $this->videoMapper->findByStorageHash($this->id);

            case QueueMapper::FILE_TYPE_SBFS:
                // For SBFS we must first load file, then resolve the hash
                $file = $this->fileMapper->findById($this->id);

                /* Check folder access perms
                 */
                if (!$this->permissionCheckFolder($file)) {
                    throw new NotFound('You do not have permission to view this video.');
                }

                return $this->videoMapper->findByStorageHash($file->getHash());

            case QueueMapper::FILE_TYPE_MYFILE:
                // For my files we must first load myfile, then resolve the hash
                $file = $this->myFileMapper->findById($this->id);

                /* Check owner perms
                 * Eportfolio access
                 */
                if (!$this->permissionCheckOwner($file) && !$this->permissionCheckEportfolioAccess($file)) {
                    throw new NotFound('You do not have permission to view this video.');
                }

                /* If $file is a MyFile, then we need to have special treatment about myFile.parent = 0.
                 * This is inferred to be the root level node of the resource browzer which, in Access.php:443,
                 * is assumed to be accessible to everyone with READ access. This isn't correct when your
                 * file is a MyFile (it shouldn't be accessible to any old bro)
                 *
                 * For this reason we skip the permissionCheckFolder() call, which would have let anyone see
                 * this MyFile.
                 */

                return $this->videoMapper->findByStorageHash($file->getHash());
        }

        throw new MapperException\NotFound();
    }


    /**
     * Check whether the current session user is the owner of $file
     * normally by comparing ids
     *
     * @param AbstractFile $file
     * @return bool
     */
    protected function permissionCheckOwner(AbstractFile $file): bool
    {
        /* If you are a superuser,
         * then you can view the content
         */
        if ($this->user->isSuperuser()) {
            return true;
        }

        /* If you are the owner
         * then you can view the content
         */
        if ($file->getOwner()->getId() === $this->user->getId()) {
            return true;
        }

        /* Every other use case: you get kicked out
         */
        return false;
    }


    /**
     * Check whether the current session user has access to an eportfolio
     * page via Eportfolio_Authenticator's existing methods
     *
     * @param AbstractFile $file
     * @return bool
     */
    protected function permissionCheckEportfolioAccess(AbstractFile $file): bool
    {
        /* If the eportfolio feature is globally enabled, dig a little deeper;
         * maybe this lets you see stuff. If it's disabled, skip all this costly
         * investigation entirely and keep going with other checks
         */
        $eportfolioEnabled = (bool)Loader::getInstance()->get('eportfolio_enabled');

        /* Check eportfolio permissions; if you can see a page that shows the video
         * (remembering that multiple eportfolio pages could show a given video)
         * then you can view the content
         */
        if ($eportfolioEnabled) {
            $fileListMapper = FileListMapper::getInstance();

            $files = $fileListMapper->findByColumn('file_id', $file->getId());

            /* If you can view one of the pages that this file is shared on,
             * break out of here
             */
            foreach ($files as $currentFile) {
                if ((new \Eportfolio_Authenticator($this->user, $file->getOwner(), $currentFile->getPageId()))->canView()) {
                    return true;
                }
            }
        }

        /* Every other use case: you get kicked out
         */
        return false;
    }


    /**
     * Check whether the current session user has read+ access to a folder
     *
     * @param AbstractFile $file
     * @return bool
     * @throws MapperException
     */
    protected function permissionCheckFolder(AbstractFile $file): bool
    {
        /* If the video was in a folder where you have read access,
         * then you can view the content
         */
        if ($this->access->getFolderAccess($this->user, $file->getFolder()->getId()) >= UserAccess::ACCESS_READ) {
            return true;
        }

        /* Every other use case: you get kicked out
         */
        return false;
    }
}
