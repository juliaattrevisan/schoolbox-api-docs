<?php

class AllowedFile extends DeprecatedModel
{
    public function __construct()
    {

        $this->_table = "allowedFileTypes";
        $this->_index = "fileTypeID";

        parent::__construct();
    }

    public function checkError($error)
    {
        switch ($error) {
            case 0: //no error
                break;
            case 1: //uploaded file exceeds the upload_max_filesize directive in php.ini
                return ['code' => 10];
            case 2: //uploaded file exceeds the Config::get('max_file_size') directive that was specified in the html form
                return ['code' => 10];
            case 3: //uploaded file was only partially uploaded
                return ['code' => 43];
            case 4: //no file was uploaded
                return ['code' => 43];
            default: //a default error, just in case!  :)
                break;
        }
        return null;
    }

    /**
     * Determine the file extension for a given filename/path
     * TODO: Refactor into FileType
     *
     * @param  string $filename
     * @return string
     */
    private function getExtension($filename)
    {
        return substr(strrchr($filename, "."), 1);
    }

    private function isAllowed($filename)
    {

        $extension = $this->getExtension($filename);

        $query = "SELECT mime FROM allowedFileExtension afe
                        JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
                        WHERE extension = ? AND enable = 'true'";

        if (!$this->db->query($query, [$extension])->fetchColumn()) {
            return [
                'code'    => 11,
                'message' => "Your file, with the extension '$extension'",
            ];
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
    private function getMaxFileSize($filename)
    {
        $query = "
			SELECT max_file_size
			FROM allowedFileExtension afe
			INNER JOIN allowedFileTypes aft ON afe.fileTypeID = aft.fileTypeID
			WHERE extension = ? AND enable = 'true'";
        $size  = $this->db->query($query, [$this->getExtension($filename)])->fetchColumn();
        return ($size > 0) ? $size : Config::get('max_file_size');
    }

    public function fileSecurityCheck($file, $typeCheck = true)
    {
        $error = $this->checkError($file['error']);
        if ($error) {
            return $error;
        }

        /* Check file is in upload dir, or that it exists on the file system
         * in the case of zipFileUpload()
         */
        if (!is_uploaded_file($file['tmp_name']) && !is_file($file['tmp_name'])) {
            return ['code' => 9, 'message' => "Security infringement, possible file upload attack."];
        }

        //file size test
        if ($file['size'] <= 0 || $file['size'] > $this->getMaxFileSize($file['name'])) {
            return ['code' => 10, 'message' => 'The size of the uploaded file exceeds the file size restrictions.'];
        }

        if ($typeCheck) {
            return $this->isAllowed($file['name']);
        }
        return null;
    }

    public function getClass($iconFile)
    {
        $url = new Url(substr($iconFile, 0, strrpos($iconFile, ".")));

        return $url->getSlug();
    }

    /**
     * Decouple error handling from error checking methods, so we can reuse the
     * error checking methods in another context
     *
     * @param array $errorArray
     */
    public function handleError($errorArray)
    {

        /* Show error associated with error code, and optional message
         */
        if (!empty($errorArray['code'])) {
            error($errorArray['code'], $errorArray['message']);
        }

        /* No error - do nothing
         */
    }

    public function getAll($order = '')
    {
        $query = "SELECT *, GROUP_CONCAT(extension SEPARATOR ',') as extension FROM `" . $this->_table . "` a JOIN allowedFileExtension b ON a.fileTypeID=b.fileTypeID GROUP BY a.fileTypeID";
        if ($order) {
            $query .= " ORDER BY `$order`";
        }

        return $this->db->query($query)->fetchAll();
    }

    public function getIconList()
    {
        //get a list of filenames from mime
        $dir  = WEB_root . '/static/images/mime/';
        $path = opendir($dir);
        $files = [];

        while (false !== ($file = readdir($path))) {
            if ($file != "." && $file != "..") {
                if (is_file($dir . "/" . $file)) {
                    $files[] = $file;
                }
            }
        }

        if (count($files) > 0) {
            natcasesort($files);
        }
        closedir($path);

        return $files;
    }
}
