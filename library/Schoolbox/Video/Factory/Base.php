<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Video\Factory;

use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\Access;
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

                /* TODO Check Folder Perms prior to this PR
                 *  (whatever that was - check that)
                 */
                $this->permissionCheckFolder($file);

                return $this->videoMapper->findByStorageHash($file->getHash());

            case QueueMapper::FILE_TYPE_MYFILE:
                // For my files we must first load myfile, then resolve the hash
                $file = $this->myFileMapper->findById($this->id);

                /* Check owner perms
                 * Eportfolio access
                 */
                $this->permissionCheckOwner($file);
                $this->permissionCheckEportfolioAccess($file);

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
    abstract protected function permissionCheckOwner(AbstractFile $file): bool;


    /**
     * Check whether the current session user has access to an eportfolio
     * page via Eportfolio_Authenticator's existing methods
     *
     * @param AbstractFile $file
     * @return bool
     */
    abstract protected function permissionCheckEportfolioAccess(AbstractFile $file): bool;


    /**
     * Check whether the current session user has read+ access to a folder
     *
     * @param AbstractFile $file
     * @return bool
     */
    abstract protected function permissionCheckFolder(AbstractFile $file): bool;
}
