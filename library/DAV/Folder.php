<?php

/**
 * Created by JetBrains PhpStorm.
 * User: jleckie
 * Date: 3/21/12
 * Time: 9:27 PM
 */

class DAV_Folder extends Sabre_DAV_Collection {

    private $folder;
    private $folderHandler;
    private $fileHandler;

    public function __construct($path) {

        $this->myPath = $path;

        $this->setFolderHandler();
        $this->setFileHandler();

        $this->setFolderID($path);


    }

    public function getChildren() {

        $children = array();

        $categoryList = $this->getFolderHandler()->getSimpleCategoryList($this->getFolderID());

        // Loop through the directory, and create objects for each node
        if(is_array($categoryList)){
            foreach($categoryList as $folder) {
                $children[] = new DAV_Folder($this->myPath.'/'.$folder['name']);
            }
        }

        $fileList = $this->getFileHandler()->getAllInCategory($this->getFolderID());
        if(is_array($fileList)){
            foreach($fileList as $file) {
                if(is_array($file) && is_readable(Cfg::get('file_dir') .'/'. $file['id'] . '.dat') )
                    $children[] = new DAV_File($this->myPath.'/'.$file['realname']);
            }
        }

        return $children;
    }

    public function getChild($name) {

        if($this->isFolder($name)) {
            return new DAV_Folder($this->myPath.'/'.$name);
        }
        else {
            $result = $this->getFileHandler()->getByArray(array('realname' => $name,'category'=>$this->getFolderID()));
            $file = $result[0];
            if(is_array($file) && is_readable(Cfg::get('file_dir') .'/'. $file['id'] . '.dat') ){
                return new DAV_File($this->myPath.'/'.$file['realname']);
            }
            else
                throw new Sabre_DAV_Exception_NotFound('The file with name: ' . $file['realname'] . ' could not be found');
        }

    }

    /*
     * Tests if the provided node name exists whithin folder, could be file or folder.
     */
    public function childExists($name) {
        if($this->isFolder($name) ){
               return true;
        }
        else {
            if(is_array($file = $this->getFileHandler()->getByArray(array('realname' => $name,'category'=>$this->getFolderID()))))
                return true;
        }

    }

    public function delete() {
        $this->getFolderHandler()->delete($this->getFolderID());
    }



    public function createFile($name, $data = null){

        $tmpLoc = tempnam(sys_get_temp_dir(),'DAV');
        file_put_contents($tmpLoc, $data);

        $file = array('description'   => $name,
                    'category'      => $this->getFolderID(),
                    'owner'         => $_SESSION['id']);
        $fileData = array('tmp_name' => $tmpLoc,
                        'name'      => $name);
        $this->getFileHandler()->create($file, $fileData);
        unlink($tmpLoc);
    }

    public function createDirectory($name) {

    }


    /*
     * Returns the current folders name
     */
    public function getName() {
        return $this->getFolderName();
    }

    public function setName($newName) {
        $folder = array('id' => $this->getFolderID(),
                        'name' => $newName
                        );
        $this->getFolderHandler()->update($folder);
    }

    private function isFolder($name) {
        if( is_array( $this->getFolderHandler()->searchByName($name) )  )
            return true;
        else
            return false;
    }

    private function getFolderID(){
        return $this->folder['id'];
    }

    private function getFolderName() {
        return $this->folder['name'];
    }

    private function setFolderID($path) {
        if($path == '') {
            $this->folder['id'] = 0;
        }
        else {

            $folderList = explode('/',$path);
            array_shift($folderList);
            $parent = 0;
            foreach($folderList as $folderName) {

                $folder =  $this->getFolderHandler()->searchByName($folderName, $parent);
                $parent = $folder[0]['id'];
            }

            $this->folderName = $folderName;
            $this->folder = $folder[0];

            if(!is_numeric($this->folderID))
                $this->folderID = 0;
      }
    }

    private function getFolderHandler() {
        return $this->folderHandler;
    }

    private function setFolderHandler() {
        $this->folderHandler = new Category();
    }

    private function getFileHandler() {
        return $this->fileHandler;
    }

    private function setFileHandler() {
        $this->fileHandler = new File();
    }
}