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

        $children = array();

        $categoryList = $this->getFolderHandler()->getSimpleCategoryList($this->getFolderID());

        // Loop through the directory, and create objects for each node that the user has permission to access
        if(is_array($categoryList)){
            foreach($categoryList as $folder) {
                if($this->getGroupHandler()->getPermission($folder['id']) >= READ_ACCESS) {
                    $children[] = new DAV_Folder($this->myPath.'/'.$folder['name']);
                }
            }
        }

        //Loop through all the files and output them if the user has access
        if($this->getGroupHandler()->getPermission($this->getFolderID()) >= READ_ACCESS ) {
            $fileList = $this->getFileHandler()->getAllInCategory($this->getFolderID());
            if(is_array($fileList)){
                foreach($fileList as $file) {
                    if(is_array($file) && is_readable(Cfg::get('file_dir') .'/'. $file['id'] . '.dat') ) {
                        $children[] = new DAV_File($this->myPath.'/'.$file['realname']);
                    }
                }
            }
        }

		//
		// Students: get a "My Subjects" folder. (Add to plugin)
		if ($this->subject_list->isSubjectListShown($this->myPath)) {
			$children[] = $this->subject_list;
		}

        return $children;
    }

    /**
     * Returns a child object, by its name.
     *
     * Checks if name refers to a folder it then returns a folder object otherwise searches for a file in the current path
     *
     * @param string $name
     * @throws Sabre\DAV\Exception_NotFound
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
        if( is_array( $this->getFolderHandler()->searchByName($name, $parent) )  )
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

    private function setGroupHandler() {
        $this->groupHandler = new Group();
    }

    private function getGroupHandler() {
        return $this->groupHandler;
    }
}