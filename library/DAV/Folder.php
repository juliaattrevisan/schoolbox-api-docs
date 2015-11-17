<?php

/**
 * Created by JetBrains PhpStorm.
 * User: jleckie
 * Date: 3/21/12
 * Time: 9:27 PM
 */

use Schoolbox\UserMapper;

class DAV_Folder extends Sabre\DAV\Collection
{

    /** @var \Schoolbox\Resource\Category */
    private $folder;

    /** @var Schoolbox\Resource\File\Mapper */
    private $fileMapper;

    /** @var Group */
    private $groupHandler;

    /** @var \Schoolbox\Resource\Folder\Mapper */
    private $folderMapper;

    /** @var DAV_SubjectServer_SubjectList */
    protected $subject_list;


    /**
     * Constructor
     *
     * @param string $path
     */
    public function __construct($path)
    {
        $this->setFolderMapper(Schoolbox\Resource\Folder\Mapper::getInstance());
        $this->setFileMapper(Schoolbox\Resource\File\Mapper::getInstance());

        $this->myPath = $path;

        $this->groupHandler = new Group();

        $this->setFolderFromPath($path);

        // Name may be configurable later on.
        $this->subject_list = new DAV_SubjectServer_SubjectList('My Subjects');
    }


    /**
     * @return Sabre\DAV\INode[]
     */
    public function getChildren()
    {
        $allFolders = $this->getFolderMapper()->findByParent($this->getFolder()->getId());

        // Loop through the directory, and create objects for each node that the user has permission to access
        $folders = array();

        foreach ($allFolders as $currentFolder) {
            if ($this->getGroupHandler()->getPermission($currentFolder->getId()) >= READ_ACCESS) {
                $folders[] = new DAV_Folder($this->myPath.'/'.$currentFolder->getName());
            }
        }

        // Loop through all the files and output them if the user has access
        $files = array();

        if ($this->getGroupHandler()->getPermission($this->getFolderID()) >= READ_ACCESS) {
            /** @var Schoolbox\Collection|Schoolbox\Resource\File\Model[] $fileList */
            $fileList = $this->getFileMapper()->findByColumn('category', $this->getFolderID());

            foreach ($fileList as $file) {
                $files[] = new DAV_File($this->myPath.'/'.$file->getBasename());
            }
        }

        // Students: get a "My Subjects" folder. (Add to plugin)
        if ($this->subject_list->isSubjectListShown($this->myPath)) {
            $folders[] = $this->subject_list;
        }

        // Sort by type and name
        usort($folders, array($this, 'compareChildren'));
        usort($files, array($this, 'compareChildren'));

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
    public function compareChildren(Sabre\Dav\INode $child1, Sabre\Dav\INode $child2)
    {
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
    public function getChild($name)
    {
        //Prevent queries if permission is not allowed
        if ($this->getFolderID() > 0) {
            if ($this->getGroupHandler()->getPermission($this->getFolderID()) < READ_ACCESS) {
                throw new Sabre\DAV\Exception\Forbidden('Permission denied');
            }
        }

        if (strlen($this->myPath) === 0) {
            $this_folder = $name;
        } else {
            $this_folder = $this->myPath.'/'.$name;
        }

        if ($this_folder === $this->subject_list->getName()) {
            $user_mapper = new UserMapper();
            $this->subject_list
                ->setUser($user_mapper->findById($_SESSION['id']))
                ->loadChildren();
            return $this->subject_list;
        }

        if ($this->isFolder($name, $this->getFolderID())) {
            return new DAV_Folder($this_folder);

        } else {

            /** @var Schoolbox\Collection|Schoolbox\Resource\File\Model[] $result */

            $realname = pathinfo($name,PATHINFO_FILENAME);

            $result = $this->getFileMapper()->findByColumnList([
                'realname'  => $realname,
                'category'  => $this->getFolderID()
            ]);

            if ($result->count()) {
                return new DAV_File($this->myPath.'/'.$result->first()->getBasename());
            } else {
                throw new Sabre\DAV\Exception\NotFound('The file with name "' . $name . '" could not be found');
            }
        }

    }


    /**
     * Checks is a child-node exists.
     *
     * Tests if the provided node name exists within folder, could be file or folder.
     *
     * @param string $name
     * @return bool
     */
    public function childExists($name)
    {
        if ($this->isFolder($name, $this->getFolderID())) {
            return true;

        } else {
            $realname = pathinfo($name,PATHINFO_FILENAME);

            if ($this->getFileMapper()->findByColumnList([ 'realname' => $realname, 'category' => $this->getFolderID() ])->count()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete a folder
     */
    public function delete()
    {
        $this->getFolderMapper()->delete($this->getFolder());
    }


    /**
     * @param string   $name
     * @param resource $data
     * @return void
     * @throws Sabre\DAV\Exception\Forbidden
     */
    public function createFile($name, $data = null)
    {
        if ($this->getGroupHandler()->getPermission($this->getFolderID()) >= WRITE_ACCESS) {
            $storage = $this->getFileMapper()->getStorageMapper()->acceptStream($name, $data, false);

            $file = $this->fileMapper->createModel()
                ->setStorage($storage)
                ->setTitle($name)
                ->setFilename(pathinfo($name,PATHINFO_FILENAME))
                ->setFolder($this->folder)
                ->setOwner($_SESSION['user']);

            $this->getFileMapper()->save($file);

        } else {
            throw new Sabre\DAV\Exception\Forbidden('Permission denied to create file');
        }
    }


    /**
     * @param string $name
     * @throws Sabre\DAV\Exception\Forbidden
     */
    public function createDirectory($name)
    {
        throw new Sabre\DAV\Exception\Forbidden('Permission denied to create directory');
    }


    /**
     * Returns the current folders name
     * @return string
     */
    public function getName()
    {
        return $this->folder->getName();
    }


    /**
     * Rename folder
     *
     * @param string $newName
     */
    public function setName($newName)
    {
        $this->getFolder()->setName($newName);

        $this->getFolderMapper()->save($this->getFolder());
    }


    /**
     * Determine if $name is a folder
     *
     * @param string $name
     * @param bool   $parent
     * @return bool
     */
    private function isFolder($name, $parent = false)
    {
        $allFolders = $this->getFolderMapper()->findByColumnList(array(
            'name' => $name,
            'parent' => $parent
        ));

        if ($allFolders->count()) {
            return true;
        }

        return false;
    }


    /**
     * @return int
     */
    private function getFolderID()
    {
        return $this->folder->getId();
    }


    /**
     * @param string $path
     */
    private function setFolderFromPath($path)
    {
        if ($path == '') {
            $this->folder = $this->getFolderMapper()->createModel()->setId(0);

        } else {
            // Path list - strip any blank parts
            $folderList = array_filter(explode('/', $path), 'strlen');
            $parent = 0;
            $folder = null;

            foreach ($folderList as $folderName) {
                $allFolders = $this->getFolderMapper()->findByColumnList(array(
                    'name' => $folderName,
                    'parent' => $parent
                ));

                if ($allFolders->count()) {
                    $folder = $allFolders->first();

                } else {
                    return;
                }

                $parent = $folder->getId();
            }

            $this->folder = $folder;
        }
    }


    /**
     * @return Group
     */
    private function getGroupHandler()
    {
        return $this->groupHandler;
    }

    /* Getters/Setters
     */

    /**
     * Set folder mapper
     *
     * @param \Schoolbox\Resource\Folder\Mapper $folderMapper
     */
    private function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }


    /**
     * Get folder mapper
     *
     * @return \Schoolbox\Resource\Folder\Mapper
     */
    private function getFolderMapper()
    {
        return $this->folderMapper;
    }


    /**
     * @param \Schoolbox\Resource\Folder\Model $folder
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
    }


    /**
     * @return \Schoolbox\Resource\Folder\Model
     */
    public function getFolder()
    {
        return $this->folder;
    }


    /**
     * @return \Schoolbox\Resource\File\Mapper
     */
    private function getFileMapper()
    {
        return $this->fileMapper;
    }


    /**
     * @param \Schoolbox\Resource\File\Mapper $fileMapper
     * @return static
     */
    private function setFileMapper($fileMapper)
    {
        $this->fileMapper = $fileMapper;
        return $this;
    }

}
