<?php

/**
 * Created by JetBrains PhpStorm.
 * User: jleckie
 * Date: 3/21/12
 * Time: 9:27 PM
 */

use Schoolbox\UserMapper;

class DAV_Folder extends Sabre\DAV\Collection {

    private $folder;
    private $folderHandler;
    private $fileHandler;
    private $groupHandler;

    /**
     * @var DAV_SubjectServer_SubjectList
     */
    protected $subject_list;

    public function __construct($path) {

        $this->myPath = $path;

        $this->setFolderHandler();
        $this->setFileHandler();
        $this->setGroupHandler();

        $this->setFolderID($path);

        //
        // Name may be configurable later on.
        $this->subject_list = new DAV_SubjectServer_SubjectList('/My Subjects');
    }

    public function getChildren() {

        $categoryList = $this->getFolderHandler()->getSimpleCategoryList($this->getFolderID());

        // Loop through the directory, and create objects for each node that the user has permission to access
        $folders = array();
        if(is_array($categoryList)){
            foreach($categoryList as $folder) {
                if($this->getGroupHandler()->getPermission($folder['id']) >= READ_ACCESS) {
                    $folders[] = new DAV_Folder($this->myPath.'/'.$folder['name']);
                }
            }
        }

        //Loop through all the files and output them if the user has access
        $files = array();
        if($this->getGroupHandler()->getPermission($this->getFolderID()) >= READ_ACCESS ) {
            $fileList = $this->getFileHandler()->getAllInCategory($this->getFolderID());
            if(is_array($fileList)){
                foreach($fileList as $file) {
                    if(is_array($file) && is_readable(Cfg::get('file_dir') .'/'. $file['id'] . '.dat') ) {
                        $files[] = new DAV_File($this->myPath.'/'.$file['realname']);
                    }
                }
            }
        }

        //
        // Students: get a "My Subjects" folder. (Add to plugin)
        if ($this->subject_list->isSubjectListShown($this->myPath)) {
            $folders[] = $this->subject_list;
        }

        //
        // Sort by type and name
        usort($folders, array($this, 'compareChildren'));
        usort($files,   array($this, 'compareChildren'));

        return array_merge($folders, $files);
    }

    /**
     * Sort callback for children.
     *
     * @param \Sabre\Dav\INode $child1
     * @param \Sabre\Dav\INode $child2
     *
     * @return int
     */
    public function compareChildren(Sabre\Dav\INode $child1, Sabre\Dav\INode $child2) {
        return strcmp($child1->getName(), $child2->getName());
    }

    /**
     * Returns a child object, by its name.
     *
     * Checks if name refers to a folder it then returns a folder object otherwise searches for a file in the current path
     *
     * @param string $name
     * @throws Sabre\DAV\Exception\Forbidden
     * @throws Sabre\DAV\Exception\NotFound
     * @return Sabre\DAV\INode
     */
    public function getChild($name) {

        //Prevent queries if permission is not allowed
        if($this->getFolderID() > 0) {
            if($this->getGroupHandler()->getPermission($this->getFolderID()) < READ_ACCESS) {
                throw new Sabre\DAV\Exception\Forbidden('Permission denied');
            }
        }

        $this_folder = $this->myPath.'/'.$name;
        if ($this_folder === $this->subject_list->getName()) {
            $user_mapper = new UserMapper();
            $this->subject_list
                ->setUser($user_mapper->findById($_SESSION['id']))
                ->loadChildren();
            return $this->subject_list;
        }
        if($this->isFolder($name, $this->getFolderID())) {
            return new DAV_Folder($this_folder);
        }
        else {
            $result = $this->getFileHandler()->getByArray(array('realname' => $name,'category'=>$this->getFolderID()));
            $file = $result[0];
            if(is_array($file) && is_readable(Cfg::get('file_dir') .'/'. $file['id'] . '.dat') ) {
                return new DAV_File($this->myPath.'/'.$file['realname']);
            }
            else {
                throw new Sabre\DAV\Exception\NotFound('The file with name: ' . $file['realname'] . ' could not be found');
            }
        }

    }


    /**
     * Checks is a child-node exists.
     *
     * Tests if the provided node name exists whithin folder, could be file or folder.
     *
     * @param string $name
     * @return bool
     */
    public function childExists($name) {
        if($this->isFolder($name, $this->getFolderID()) ){
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

        if($this->getGroupHandler()->getPermission($this->getFolderID()) >= WRITE_ACCESS) {

            $tmpLoc = tempnam(sys_get_temp_dir(),'sb');
            file_put_contents($tmpLoc, $data);

            $file = array('description'   => $name,
                        'category'      => $this->getFolderID(),
                        'owner'         => $_SESSION['id']);
            $fileData = array('tmp_name' => $tmpLoc,
                            'name'      => $name);
            $this->getFileHandler()->create($file, $fileData);
            unlink($tmpLoc);
        }
        else {
            throw new Sabre\DAV\Exception\Forbidden('Permission denied to create file');
        }
    }

    public function createDirectory($name) {
        throw new Sabre\DAV\Exception\Forbidden('Permission denied to create directory');
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

    private function isFolder($name, $parent = false) {
        return is_array($this->getFolderHandler()->getByArray(array('name' => $name, 'parent' => $parent,)));
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
            //
            // Path list - strip any blank parts
            $folderList = array_filter(explode('/',$path), 'strlen');
            $parent = 0;
            foreach ($folderList as $folderName) {
                $folder = $this->getFolderHandler()->getByArray(
                    array(
                        'name'   => $folderName,
                        'parent' => $parent,
                    )
                );
                $parent = $folder[0]['id'];
            }

            $this->folderName = $folderName;
            $this->folder = $folder[0];

            if(!is_numeric($this->folderID))
                $this->folderID = 0;
      }
    }

    /**
     * @return Category
     */
    private function getFolderHandler() {
        return $this->folderHandler;
    }

    private function setFolderHandler() {
        $this->folderHandler = new Category();
    }

    /**
     * @return File
     */
    private function getFileHandler() {
        return $this->fileHandler;
    }

    private function setFileHandler() {
        $this->fileHandler = new File();
    }

    private function setGroupHandler() {
        $this->groupHandler = new Group();
    }

    private function getGroupHandler() {
        return $this->groupHandler;
    }
}