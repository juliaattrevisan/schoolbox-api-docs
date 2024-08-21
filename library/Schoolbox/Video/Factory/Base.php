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
use Schoolbox\Resource\File\Model;
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
     *
     * @var Access
     */
    protected $access;


    /**
     * The type of video (submission, sbfs, storage etc)
     *
     * @var string
     */
    protected $type;


    /**
     * The identifier of the file (could be a hash or numeric index)
     *
     * @var string
     */
    protected $id;


    /**
     * Constructor
     *
     * @param int $id
     * @param string $type
     */
    public function __construct($id, $type)
    {
        $this->setAccess(Access::getInstance());
        $this->setType($type);
        $this->setId($id);
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
    public function produce()
    {
        $videoMapper = new ResourceVideoMapper();

        switch ($this->getType()) {
            case QueueMapper::FILE_TYPE_STORAGE:
                return $videoMapper->findByStorageHash($this->getId());

            case QueueMapper::FILE_TYPE_SBFS:
                // For SBFS we must first load file, then resolve the hash
                $file = FileMapper::getInstance()->findById($this->getId());
                $this->permissionCheck($file);

                return $videoMapper->findByStorageHash($file->getHash());

            case QueueMapper::FILE_TYPE_SUBMISSION:
                return $videoMapper->findByStorageHash($this->getId());

            case QueueMapper::FILE_TYPE_MYFILE:
                // For my files we must first load myfile, then resolve the hash
                $file = MyFileMapper::getInstance()->findById($this->getId());
                $this->permissionCheck($file, true);

                return $videoMapper->findByStorageHash($file->getHash());
        }

        throw new MapperException\NotFound();
    }


    /**
     * Permission check prior to instantiation
     *
     * @param AbstractFile $file
     * @param bool $isMyFile
     */
    abstract protected function permissionCheck(AbstractFile $file, bool $isMyFile = false);


    /**
     * @return Access
     */
    public function getAccess()
    {
        return $this->access;
    }


    /**
     * @param Access $access
     */
    public function setAccess($access)
    {
        $this->access = $access;
    }


    /**
     * Set id
     *
     * @param string $id
     */
    protected function setId($id)
    {
        $this->id = $id;
    }


    /**
     * Get id
     *
     * @return string
     */
    protected function getId()
    {
        return $this->id;
    }


    /**
     * Set type
     *
     * @param string $type
     */
    protected function setType($type)
    {
        $this->type = $type;
    }


    /**
     * Get type
     *
     * @return string
     */
    protected function getType()
    {
        return $this->type;
    }
}
