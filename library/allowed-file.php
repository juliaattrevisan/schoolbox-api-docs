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
				error(10);
					break;
			case 2: //uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the html form
				error(10);
				break;
			case 3: //uploaded file was only partially uploaded
				error(43);
				break;
			case 4: //no file was uploaded
				error(43);
				break;
			default: //a default error, just in case!  :)
				break;
		}
	}
	
	function checkMime($file) {
		
		$ext = substr(strrchr($file['name'], "."), 1);
		
		if(!$mime = $this->getMime($file['tmp_name']))
			$mime = strtolower($file['type']);

        /* Extension column doesn't exist anymore?
         *
		$query = "SELECT t.fileTypeID FROM allowedFileTypes t 
				LEFT JOIN allowedFileExtension e on t.fileTypeID = e.fileTypeID 
				WHERE mime = '$mime' AND extension = '$ext' AND enable = 'true'";
         *
         */

        /* Mime types now have some kind of metadata, so trim it out.
         * Mime types now include stuff like 'charset=us-ascii' and may not
         * match when using the original query
         */
        $mime = explode(';', $mime);
        $mime = $mime[0];

        /* New query
         */
		$query = "SELECT t.fileTypeID FROM allowedFileTypes t
				LEFT JOIN allowedFileExtension e on t.fileTypeID = e.fileTypeID
				WHERE mime = '$mime' AND enable = 'true'";

		if(!$this->db->getOne($query))
			error(11, "Your file, ".$file['name']." with the extension '$ext' and type of '$mime'");
	}
	

	
	function getMime($file) {
		//make sure we can read the magic file and fileinfo functions are defined
		if(is_readable(MIME_magic) && function_exists('finfo_open') ) {
			//update to check against magic mime file on server rather than trust browser interpretation
			$handle = finfo_open(FILEINFO_MIME, MIME_magic);
			return finfo_file($handle,$file);
		}
	}
	
	
	function fileSecurityCheck($file, $typeCheck = true)
	{
		$this->checkError($file['error']);
	
		//check file is in upload dir
		if(!is_uploaded_file($file['tmp_name']))
			error(9, "Security infringement, possible file upload attack.");
		
		//file size test
		if( $file['size'] <= 0)
			error(10);
		
		if($typeCheck)
			$this->checkMime($file);
	}
	
	function getClass ($iconFile) {
		return convertToURL(substr($iconFile, 0, strrpos($iconFile, ".")));
	}

}

?>