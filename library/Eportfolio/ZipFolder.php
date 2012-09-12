<?php
// Copyright 2012 Alaress Pty Ltd. http://alaress.com.au

/**
 * Archives myFiles
 *
 * @author Ryan Hughes <ryan.hughes@alaress.com.au>
 */
class Eportfolio_ZipFolder extends ZipFolder {

    protected $user_id;

    /**
     * List of saved files indexed by id
     * @var array
     */
    protected $relativePaths = array();

    /**
     * @param $folder_id Id of parent myFolder to open
     * @param $user_id Id of myFiles/myFolder owner
     */
    public function __construct($folder_id, $user_id) {
        $this->user_id = $user_id;

        return parent::ZipFolder($folder_id);
    }

    /**
     * Gets a list of myFiles within the given folder id
     * @param $folder_id
     * @return array
     */
    function getResources($folder_id) {
        $query = "SELECT id, realname, file_size FROM myFile
                  WHERE parent = ".(int)$folder_id." AND owner = ".(int)$this->user_id." AND deleted = 0";
        return $this->db->getAll($query);
    }

    /**
     * Gets a list of sub folders within the given folder id
     * @param int $folder_id myFolder row id
     * @return array
     */
    function getFolders($folder_id) {
        $query = "SELECT id, name FROM myFolder WHERE parent = ".(int)$folder_id." AND owner = ".(int)$this->user_id."";
        return $this->db->getAll($query);
    }

    /**
     * Gets a db row representing the given myFolder id
     * @param $folder_id
     * @return array
     */
    protected function getFolderDetails($folder_id) {
        $myFolder = new MyFolder();
        if ($folder_id > 0) {
            return $myFolder->getByID($folder_id);
        } else {
            return array(
                'id' => 0,
                'name' => 'myFiles'
            );
        }
    }

    /**
     * Gets a Group permission level for the given folder
     *
     * Always returns READ_ACCESS
     *
     * @param $folder_id
     * @return READ_ACCESS
     */
    protected function getPermission($folder_id) {
        // Avoid permission checks on private files
        return READ_ACCESS;
    }

    /**
     * Gets the location of the file on disk
     * @param Id $file_id
     * @return string
     */
    protected function getFilePath($file_id) {
        return Cfg::get('myfile_dir') . '/' . $file_id . '.dat';
    }

    /**
     * Gets a unique location to save the given file
     * @param string $currentDir
     * @param array $file
     * @return string
     */
    protected function getTargetPath($currentDir, $file) {
        $targetPath = parent::getTargetPath($currentDir, $file);

        // maintain an index of file ids and the relative paths they have been moved to
        $this->relativePaths[$file['id']] = str_replace($this->tmpFolder . '/', '', $targetPath);

        return $targetPath;
    }

    /**
     * Gets a list of file paths relative to tmpFolder indexed by myFileId
     *
     * Relative paths do not have a leading forward slash
     *
     * @return array
     */
    public function getRelativePaths() {
        return $this->relativePaths;
    }
}
