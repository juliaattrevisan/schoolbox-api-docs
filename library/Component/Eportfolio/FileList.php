<?php
/**
 * Component_Eportfolio_FileList
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Component\Eportfolio;

class FileList extends ListItem {

	/**
	 * Primary key
	 *
	 * @var
	 */
	public $id;

	/**
	 * List to which this list item belongs
	 *
	 * @var
	 */
	public $component_instance_id;

	/**
	 * Id of a published file
	 *
	 * @var
	 */
	public $fileId;

	/**
	 * A Schoolbox\MyFile instance
	 *
	 * @var
	 */
	public $file;

	/**
	 * When the File List item was created
	 *
	 * @var
	 */
	public $created_at;

	/**
	 * When the File List item was updated
	 * @var
	 */
	public $updated_at;

	/**
	 * When the File List item was softdeleted
	 * @var
	 */
	public $deleted_at;

	/* Getters/Setters
	 */

	/**
	 * Set list item id
	 *
	 * @param  $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get list item id
	 *
	 * @return
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set component instance id
	 *
	 * @param  $component_instance_id
	 */
	public function setComponentInstanceId($component_instance_id) {
		$this->component_instance_id = $component_instance_id;
	}

	/**
	 * Get component instance id
	 *
	 * @return
	 */
	public function getComponentInstanceId() {
		return $this->component_instance_id;
	}

	/**
	 * Set file id
	 *
	 * @param  $fileId
	 */
	public function setFileId($fileId) {
		$this->fileId = $fileId;
	}

	/**
	 * Get file id
	 *
	 * @return
	 */
	public function getFileId() {
		return $this->fileId;
	}

	/**
	 * Set created at
	 *
	 * @param  $created_at
	 */
	public function setCreatedAt($created_at) {
		$this->created_at = $created_at;
	}

	/**
	 * Get created at
	 *
	 * @return
	 */
	public function getCreatedAt() {
		return $this->created_at;
	}

	/**
	 * Set deleted at
	 *
	 * @param  $deleted_at
	 */
	public function setDeletedAt($deleted_at) {
		$this->deleted_at = $deleted_at;
	}

	/**
	 * Get deleted at
	 *
	 * @return
	 */
	public function getDeletedAt() {
		return $this->deleted_at;
	}

	/**
	 * Set updated at
	 *
	 * @param  $updated_at
	 */
	public function setUpdatedAt($updated_at) {
		$this->updated_at = $updated_at;
	}

	/**
	 * Get updated at
	 *
	 * @return
	 */
	public function getUpdatedAt() {
		return $this->updated_at;
	}

	/**
	 * Set file
	 *
	 * @param  $file
	 */
	public function setFile($file) {
		$this->file = $file;
	}

	/**
	 * Get file
	 *
	 * @return
	 */
	public function getFile() {
		return $this->file;
	}


}