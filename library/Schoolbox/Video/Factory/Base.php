<?php
/**
 * Factory
 *
 * Spawn a video object based on its type and identifier
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Video\Factory;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use \Schoolbox\Video\QueueMapper;
use Schoolbox\Video\ResourceVideo;
use \Schoolbox\Video\ResourceVideoMapper;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\My\File\Mapper as MyFileMapper;

abstract class Base {

	/**
	 * Folder permission access
	 *
	 * @var Access
	 */
	protected $access = null;

	/**
	 * The type of video (submission, sbfs, storage etc)
	 *
	 * @var string
	 */
	protected $type = '';

	/**
	 * The identifier of the file (could be a hash or numeric index)
	 *
	 * @var string
	 */
	protected $id = '';

	/**
	 * Constructor
	 *
	 * @param $id
	 * @param $type
	 */
	public function __construct($id, $type) {
		$this->setAccess(Access::getInstance());
		$this->setType($type);
		$this->setId($id);
	}

	/**
	 * Spawn a video object
	 *
	 * @return ResourceVideo
	 * @throws MapperException
	 */
	public function produce() {
		$videoMapper = new ResourceVideoMapper();

		switch($this->getType()) {
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

                return $videoMapper->findByStorageHash($file->getHash());
		}

        throw new MapperException('Instance of object not found');
	}

	/**
	 * Permission check prior to instantiation
	 */
	abstract protected function permissionCheck($video);

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
	protected function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return string
	 */
	protected function getId() {
		return $this->id;
	}

	/**
	 * Set type
	 *
	 * @param string $type
	 */
	protected function setType($type) {
		$this->type = $type;
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	protected function getType() {
		return $this->type;
	}
}
