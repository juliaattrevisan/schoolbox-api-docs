<?php

class AllowedFile extends DeprecatedModel {

	function __construct() {

		$this->_table = "allowedFileTypes";
		$this->_index = "fileTypeID";

		parent::__construct();
	}

	function checkError($error) {
		switch($error)
		{
			case 0: //no error
				break;
			case 1: //uploaded file exceeds the upload_max_filesize directive in php.ini
				return array('code' => 10);
					break;
			case 2: //uploaded file exceeds the Config::get('max_file_size') directive that was specified in the html form
				return array('code' => 10);
				break;
			case 3: //uploaded file was only partially uploaded
				return array('code' => 43);
				break;
			case 4: //no file was uploaded
				return array('code' => 43);
				break;
			default: //a default error, just in case!  :)
				break;
		}
	}

	/**
	 * Determine the file extension for a given filename/path
	 * TODO: Refactor into FileType
	 *
	 * @param  $filename
	 * @return string
	 */
	public function getExtension($filename) {
		return substr(strrchr($filename, "."), 1);
	}

    function isAllowed ($filename) {

        $extension = $this->getExtension($filename);

        $query = "SELECT mime FROM allowedFileExtension afe
                        JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                        WHERE extension = {$this->db->smartQuote($extension)} AND enable = 'true'";

        if ( !$this->db->getOne($query) ) {
            return array(
                'code' => 11,
                'message' => "Your file, with the extension '$extension'"
            );
        }
    }

	/**
	 * Gets the maximum file size for this file's type, or the default max file
	 * size if that isn't set.
	 *
	 * @param string $filename
	 *
	 * @return int
	 */
	public function getMaxFileSize($filename) {
		$query = "
			SELECT max_file_size
			FROM allowedFileExtension afe
			INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
			WHERE extension = {$this->db->smartQuote($this->getExtension($filename))} AND enable = 'true'";
		$size = $this->db->getOne($query);
		return ($size > 0) ? $size : Config::get('max_file_size');
	}

	/**
	 * Get mime type for a particular file extension
	 *
	 * @param $extension
	 * @return mixed
	 */
	public function getMime($extension) {
		$query = "
			SELECT mime
			FROM allowedFileExtension afe
			LEFT JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
			WHERE extension = {$this->db->smartQuote($extension)}
		";

		$mime = $this->db->getOne($query);

		return $mime;
	}


	function fileSecurityCheck($file, $typeCheck = true) {
		$this->checkError($file['error']);

		/* Check file is in upload dir, or that it exists on the file system
		 * in the case of zipFileUpload()
		 */
		if (!is_uploaded_file($file['tmp_name']) && !is_file($file['tmp_name'])) {
			return array('code' => 9, 'message' => "Security infringement, possible file upload attack.");
		}

		//file size test
		if ($file['size'] <= 0 || $file['size'] > $this->getMaxFileSize($file['name'])) {
			return array('code' => 10, 'message' => 'The size of the uploaded file exceeds the file size restrictions.');
		}

		if ($typeCheck) {
			return $this->isAllowed($file['name']);
		}
	}

	function getClass ($iconFile) {
		$url = new Url(substr($iconFile, 0, strrpos($iconFile, ".")));

		return $url->getSlug();
	}

	/**
	 * Decouple error handling from error checking methods, so we can reuse the
	 * error checking methods in another context
	 *
	 * @param <type> $errorArray
	 */
	public function handleError($errorArray) {

		/* Show error associated with error code, and optional message
		 */
		if (!empty($errorArray['code'])) {
			error($errorArray['code'], $errorArray['message']);
		}

		/* No error - do nothing
		 */
	}

        function getAll($order = '') {
		$query = "SELECT *, GROUP_CONCAT(extension SEPARATOR ',') as extension FROM `".$this->_table."` a JOIN allowedFileExtension b ON a.fileTypeID=b.fileTypeID GROUP BY a.fileTypeID";
		if($order)
			$query .= " ORDER BY `$order`";

		return  $this->db->getAll($query);
        }

	function getIconList() {
		//get a list of filenames from mime
		$dir = $_SERVER['DOCUMENT_ROOT'].'/images/mime/';
		$path = opendir($dir);
		while (false !== ($file = readdir($path))) {
			if($file!="." && $file!="..") {
				if(is_file($dir."/".$file))
					$files[]=$file;
			}
		}

		if($files) {
			natcasesort($files);
		}
		closedir($path);

		return $files;
	}
}

?>
