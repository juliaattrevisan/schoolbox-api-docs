<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\WebDav\FileServer;

use Sabre\DAV;
use Schoolbox\Collection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\File\Model as FileModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;
use Schoolbox\WebDav\SubjectServer\SubjectList;

/**
 * A WebDAV node that represents a folder within the Schoolbox file system.
 */
class Folder extends DAV\Collection
{
    /**
     * @var User
     */
    private $user;

    /** @var FolderModel */
    private $folder;

    /** @var FileMapper */
    private $fileMapper;

    /** @var Access */
    private $accessor;

    /** @var FolderMapper */
    private $folderMapper;

    /**
     * A storage mapper
     *
     * @var StorageMapper
     */
    private $storageMapper;

    /**
     * @var SubjectList
     */
    private $subjectList;

    /**
     * Requested folder path string
     * @var string
     */
    private $path;


    /**
     * Constructor
     *
     * @param              $path
     * @param User         $user
     * @param FolderMapper $folderMapper
     * @param FileMapper   $fileMapper
     * @param StorageMapper $storageMapper
     * @param Access        $accessor
     */
    public function __construct(
        $path,
        User $user,
        FolderMapper $folderMapper,
        FileMapper $fileMapper,
        StorageMapper $storageMapper,
        Access $accessor
    ) {
        $this->setFolderMapper($folderMapper);
        $this->setFileMapper($fileMapper);
        $this->setStorageMapper($storageMapper);
        $this->setAccessor($accessor);
        $this->setUser($user);
        $this->setPath($path);
        $this->setFolderFromPath($path);

//        // Name may be configurable later on.
//        $this->subject_list = new DAV_SubjectServer_SubjectList('My Subjects');
    }

    /**
     * Injects a preconfigured subject list into this folder.
     *
     * @param SubjectList $subjectList
     * @return $this
     */
    public function addSubjectList(SubjectList $subjectList)
    {
        $this->setSubjectList($subjectList);
        return $this;
    }

    /**
     * @return DAV\INode[]
     */
    public function getChildren()
    {
        // TODO abstract all folder creation (inc. finding sub-objects) to factories
        $allFolders = $this->getFolderMapper()->findByParent($this->getFolder()->getId());

        // Loop through the directory, and create objects for each node that the user has permission to access
        $folders = array();
        foreach ($allFolders as $currentFolder) {
            if ($this->getAccessor()->getFolderAccess($this->getUser(), array($currentFolder->getId())) >= UserAccess::ACCESS_READ) {
                $folders[] = new Folder(
                    $this->getPath().'/'.$currentFolder->getName(),
                    $this->getUser(),
                    $this->getFolderMapper(),
                    $this->getFileMapper(),
                    $this->getStorageMapper(),
                    $this->getAccessor()
                );
            }
        }

        // Loop through all the files and output them if the user has access
        $files = array();

        if ($this->getAccessor()->getFolderAccess($this->getUser(),array($this->getFolderID())) >= UserAccess::ACCESS_READ) {
            /** @var Collection|FileModel[] $fileList */
            $fileList = $this->getFileMapper()->findByColumn('category', $this->getFolderID());

            foreach ($fileList as $file) {
                $files[] = new File(
                    $this->getPath().'/'.$file->getBasename(),
                    $this->getUser(),
                    $this->getFolderMapper(),
                    $this->getFileMapper(),
                    $this->getStorageMapper(),
                    $this->getAccessor()
                );
            }
        }

        // Students: get a "My Subjects" folder. (Add to plugin)
        if ($this->hasSubjectList() && $this->getSubjectList()->isSubjectListShown($this->getPath())) {
            $folders[] = $this->subjectList;
        }

        // Sort by type and name
        usort($folders, array($this, 'compareChildren'));
        usort($files, array($this, 'compareChildren'));

        return array_merge($folders, $files);
    }


    /**
     * Sort callback for children.
     *
     * @param DAV\INode  $child1
     * @param DAV\INode  $child2
     *
     * @return int
     */
    public function compareChildren(DAV\INode $child1, DAV\INode $child2)
    {
        return strcmp($child1->getName(), $child2->getName());
    }


    /**
     * Returns a child object, by its name.
     *
     * Checks if name refers to a folder it then returns a folder object otherwise searches for a file in the current path
     *
     * @param string $name
     * @throws DAV\Exception\Forbidden
     * @throws DAV\Exception\NotFound
     * @return DAV\INode
     */
    public function getChild($name)
    {
        //Prevent queries if permission is not allowed
        if ($this->getFolderID() > 0) {
            if ($this->getAccessor()->getFolderAccess($this->getUser(), array($this->getFolderID())) < UserAccess::ACCESS_READ) {
                throw new DAV\Exception\Forbidden('Permission denied');
            }
        }

        if (strlen($this->getPath()) === 0) {
            $this_folder = $name;
        } else {
            $this_folder = $this->getPath().'/'.$name;
        }

        if ($this->hasSubjectList() && $this_folder === $this->getSubjectList()->getName()) {
            return $this->getSubjectList();
        }

        if ($this->isFolder($name, $this->getFolderID())) {
            return new Folder(
                $this_folder,
                $this->getUser(),
                $this->getFolderMapper(),
                $this->getFileMapper(),
                $this->getStorageMapper(),
                $this->getAccessor()
            );

        } else {

            /** @var Collection|FileModel[] $result */

            $realname = pathinfo($name,PATHINFO_FILENAME);

            $result = $this->getFileMapper()->findByColumnList([
                'realname'  => $realname,
                'category'  => $this->getFolderID()
            ]);

            if ($result->count()) {
                return new File(
                    $this->getPath().'/'.$result->first()->getBasename(),
                    $this->getUser(),
                    $this->getFolderMapper(),
                    $this->getFileMapper(),
                    $this->getStorageMapper(),
                    $this->getAccessor()
                );
            } else {
                throw new DAV\Exception\NotFound('The file with name "' . $name . '" could not be found');
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
     * @return void
     * @throws DAV\Exception\Forbidden
     */
    public function delete()
    {
        if ($this->getAccessor()->getFolderAccess($this->getUser(), array($this->getFolderID())) >= UserAccess::ACCESS_WRITE) {
            $this->getFolderMapper()->deleteFolder($this->getFolder()->getId(), $this->getUser());
        }
        else {
            throw new DAV\Exception\Forbidden('Permission denied to delete folder');
        }
    }


    /**
     * @param string   $name
     * @param resource $data
     * @return void
     * @throws DAV\Exception\Forbidden
     */
    public function createFile($name, $data = null)
    {
        if ($this->getAccessor()->getFolderAccess($this->getUser(), array($this->getFolderID())) >= UserAccess::ACCESS_WRITE) {
            $storage = $this->getFileMapper()->getStorageMapper()->acceptStream($name, $data, false);

            $file = $this->fileMapper->createModel()
                                     ->setStorage($storage)
                                     ->setTitle($name)
                                     ->setFilename(pathinfo($name,PATHINFO_FILENAME))
                                     ->setFolder($this->folder)
                                     ->setOwner($this->getUser());

            $this->getFileMapper()->save($file);

        } else {
            throw new DAV\Exception\Forbidden('Permission denied to create file');
        }
    }


    /**
     * @param string $name
     * @throws DAV\Exception\Forbidden
     */
    public function createDirectory($name)
    {
        throw new DAV\Exception\Forbidden('Permission denied to create directory');
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
     * @throws DAV\Exception\Forbidden
     */
    public function setName($newName)
    {
        if ($this->getAccessor()->getFolderAccess($this->getUser(), array($this->getFolder()->getParent()->getId())) >= UserAccess::ACCESS_WRITE) {

            $this->getFolder()->setName($newName);
            $this->getFolderMapper()->save($this->getFolder());
        }
        else {
            throw new DAV\Exception\Forbidden('Permission denied to modify directory');
        }
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
        $this->folder = $this->getFolderMapper()->createModel()->setId(0);
        if ($path !== '') {
            // Path list - strip any blank parts
            $folderList = array_filter(explode('/', $path), 'strlen');
            $parent     = 0;
            $folder     = null;

            foreach ($folderList as $folderName) {
                $allFolders = $this->getFolderMapper()->findByColumnList([
                    'name'   => $folderName,
                    'parent' => $parent,
                ]);

                if ($allFolders->count()) {
                    $folder = $allFolders->first();
                } else {
                    return;
                }

                $this->setFolder($folder);
                $parent       = $folder->getId();
            }
        }
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

    /**
     * @return Access
     */
    private function getAccessor()
    {
        return $this->accessor;
    }

    /**
     * @param Access $accessor
     */
    private function setAccessor($accessor)
    {
        $this->accessor = $accessor;
    }

    /**
     * @return User
     */
    private function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return static
     */
    private function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return string
     */
    private function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     * @return static
     */
    private function setPath($path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @return StorageMapper
     */
    private function getStorageMapper()
    {
        return $this->storageMapper;
    }

    /**
     * @param StorageMapper $storageMapper
     * @return static
     */
    private function setStorageMapper($storageMapper)
    {
        $this->storageMapper = $storageMapper;
        return $this;
    }

    /**
     * @return SubjectList
     */
    private function getSubjectList()
    {
        return $this->subjectList;
    }

    /**
     * @param SubjectList $subjectList
     * @return static
     */
    private function setSubjectList($subjectList)
    {
        $this->subjectList = $subjectList;
        return $this;
    }

    /**
     * @return bool
     */
    private function hasSubjectList()
    {
        return $this->getSubjectList() instanceof SubjectList;
    }
}
