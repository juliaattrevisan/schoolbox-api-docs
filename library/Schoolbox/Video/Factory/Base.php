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

use \Schoolbox\Video\QueueMapper;
use Schoolbox\Video\ResourceVideo;
use \Schoolbox\Video\ResourceVideoMapper;

abstract class Base {

	/**
	 * An instance of group handler
	 *
	 * @var null
	 */
	protected $groupHandler = null;

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
	 * @param $type
	 * @param $hash
	 */
	public function __construct($hash, $type) {
		$this->setGroupHandler(new \Group());
		$this->setType($type);
		$this->setId($hash);
	}

	/**
	 * Spawn a video object
	 *
	 * @return ResourceVideo
	 * @throws \Exception
	 */
	public function produce() {
		$videoMapper = new ResourceVideoMapper();

		switch($this->getType()) {
			case QueueMapper::FILE_TYPE_STORAGE:
				return $videoMapper->findByStorageHash($this->getId());

			case QueueMapper::FILE_TYPE_SBFS:
                return $videoMapper->findByStorageHash($this->getId());
				$this->permissionCheck($video);
				return $video;

			case QueueMapper::FILE_TYPE_SUBMISSION:
                return $videoMapper->findByStorageHash($this->getId());
				return $video;

			case QueueMapper::FILE_TYPE_MYFILE:
                return $videoMapper->findByStorageHash($this->getId());
				return $video;
		}

        throw new \Schoolbox\Mapper\Exception('Instance of object not found');
	}

	/**
	 * Permission check prior to instantiation
	 */
	abstract protected function permissionCheck($video);

	/* Getters/Setters
	 */

	/**
	 * Set group handler
	 *
	 * @param null $groupHandler
	 */
	protected function setGroupHandler($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group handler
	 *
	 * @return \Group
	 */
	protected function getGroupHandler() {
		return $this->groupHandler;
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