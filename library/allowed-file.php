<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/model.php';

class AllowedFile extends Model {

	function AllowedFile () {
	
		$this->_table = "allowedFileTypes";
		$this->_index = "fileTypeID";
		
		parent::Model();
	}
	
	function checkError($error) {
		switch($error)
		{
			case 0: //no error
				break;
			case 1: //uploaded file exceeds the upload_max_filesize directive in php.ini
				return array('code' => 10);
					break;
			case 2: //uploaded file exceeds the Cfg::get('max_file_size') directive that was specified in the html form
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

        function getExtension($filename) {
            return substr(strrchr($filename, "."), 1);
        }

        function isAllowed($filename) {

            $extension = $this->getExtension($filename);

            $query = "SELECT mime FROM allowedFileExtension afe
                        JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                        WHERE extension = '$extension' AND enable = 'true'";
            if(!$this->db->getOne($query)) {
		return array('code' => 11, 'message' => "Your file, ".$file['name']." with the extension '$extension'");
            }
        }

	function getMime($extension) {

                $query = "SELECT mime FROM allowedFileExtension afe JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID WHERE extension = '$extension'";
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
		if ($file['size'] <= 0) {
			return array('code' => 10);
		}
		
		if ($typeCheck) {
			return $this->isAllowed($file['name']);
		}
	}

	function getClass ($iconFile) {
		return convertToURL(substr($iconFile, 0, strrpos($iconFile, ".")));
	}

	/**
	 * Decouple error handling from error checking methods, so we can reuse the
	 * error checking methods in another context
	 *
	 * @param <type> $errorArray
	 */
	function handleError($errorArray) {

		/* Show error associated with error code, and optional message
		 */
		if (!empty($errorArray['code'])) {
			error($errorArray['code'], $errorArray['message']);
		}

		/* No error - do nothing
		 */
	}
}

?>