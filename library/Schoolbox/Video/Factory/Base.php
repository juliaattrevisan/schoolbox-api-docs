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
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper as SubmissionMapper;

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
	 * @param $id
	 * @param $type
	 */
	public function __construct($id, $type) {
		$this->setGroupHandler(new \Group());
		$this->setType($type);
		$this->setId($id);
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

                // For SBFS we must first check submission then resolve the hash
                $file = FileMapper::getInstance()->findById($this->getId());
                $this->permissionCheck($file);

                return $videoMapper->findByStorageHash($file->getHash());

			case QueueMapper::FILE_TYPE_SUBMISSION:
                $submission = SubmissionMapper::getInstance()->findByID($this->getId());
                return $videoMapper->findByStorageHash($submission->getHash());

			case QueueMapper::FILE_TYPE_MYFILE:
                return $videoMapper->findByMyFileId($this->getId());
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