<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Component\Learning;
use Schoolbox;

class SubmissionBox {
	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var int
	 */
	protected $category;

	/**
	 * @var int
	 */
	protected $type;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var string
	 */
	protected $description;

	/**
	 * @var int
	 */
	protected $legacy_file_id;

	/**
	 * @var SubmissionBoxFile
	 */
	protected $storage;

	/**
	 * @param int $category
	 */
	public function setCategory($category) {
		$this->category = $category;
	}

	/**
	 * @return int
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * @param string $description
	 */
	public function setDescription($description) {
		$this->description = $description;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * @param int $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param int $legacy_file_id
	 */
	public function setLegacyFileId($legacy_file_id) {
		$this->legacy_file_id = $legacy_file_id;
	}

	/**
	 * @return int
	 */
	public function getLegacyFileId() {
		return $this->legacy_file_id;
	}

	/**
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param int $type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param SubmissionBoxFile $storage
	 */
	public function setStorage(SubmissionBoxFile $storage) {
		$this->storage = $storage;
	}

	/**
	 * @return SubmissionBoxFile
	 */
	public function getStorage() {
		return $this->storage;
	}
}
