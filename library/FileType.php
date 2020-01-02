<?php
/**
 * FileType
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class FileType extends DeprecatedModel {
    public $_table = 'allowedFileTypes';
    public $_index = 'fileTypeId';

    /**
     * Filename
     * @var string
     */
	private $path;

    /**
     * This is used to determine whether a file is audio or video
     * based, and should be extended if necessary
     *
     * @var string|null
     */
    private $mime;

    /**
	 * Constructor
	 *
	 * @param $path
	 * @param null $mime
	 */
	public function __construct($path, $mime = null) {
		$this->path = $path;
		$this->mime = $mime;

		parent::__construct();
	}

	/**
	 * Determine the file extension for a given filename/path
	 * without dot
	 *
	 * @return string
	 */
	public function getExtension() {
		return strtolower(substr(strrchr($this->path, '.'), 1));
	}

	/**
	 * Determine if a file is audio
	 *
	 * @return bool
	 */
	public function isAudio() {
		if (strstr($this->mime, 'audio')) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if a file is video
	 *
	 * @return bool
	 */
	public function isVideo() {
		if (strstr($this->mime, 'video')) {
			return true;
		}

		return false;
	}

	/**
	 * Determine if a file is an image
	 *
	 * @return bool
	 */
	public function isImage() {
		if (strstr($this->mime, 'image')) {
			return true;
		}

		return false;
	}

	/**
	 * Find out what the iconFile for a particular mime type is
	 *
	 * @param $mimeType
	 * @return mixed
	 */
	public function getIconFile($mimeType) {
		$query = "
			SELECT iconFile
			FROM {$this->_table}
			WHERE mime = '{$mimeType}'
			LIMIT 1
		";

		$mimeType = $this->db->getRow($query);

		return $mimeType['iconFile'];
	}
}
