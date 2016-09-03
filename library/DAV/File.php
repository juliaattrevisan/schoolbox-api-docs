<?php
/**
 * Created by JetBrains PhpStorm.
 * User: jleckie
 * Date: 3/21/12
 * Time: 9:27 PM
 */

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class DAV_File extends Sabre\DAV\File
{

    /** @var Schoolbox\Resource\File\Model */
    private $file;

    /**
     * @var Schoolbox\Resource\File\Mapper
     */
    private $fileMapper;

    /**
     * A folder mapper
     *
     * @var Schoolbox\Resource\Folder\Mapper
     */
    private $folderMapper;

    /**
     * Folder where file is located
     *
     * @var Schoolbox\Resource\Folder\Model
     */
    private $folder;

    /**
     * Permissions mapper for determining access rights
     *
     * @var \Schoolbox\Permission\Access\Access
     */
    private $accessor;

    /**
     * @param string $path
     */
    public function __construct($path)
    {
        $this->setFileMapper(FileMapper::getInstance());
        $this->setFolderMapper(FolderMapper::getInstance());
        $this->setAccessor(Access::getInstance());

        $this->setFileFromPath($path);
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->getFile()->getBasename();
    }


    /**
     * Rename file
     *
     * @param string $newName
     * @throws Sabre\DAV\Exception\Forbidden
     */
    public function setName($newName)
    {
        if ($this->getAccessor()->getFolderAccess($_SESSION['user'], array($this->getFolder()->getId())) >= UserAccess::ACCESS_WRITE) {
            $this->getFile()->setFilename(pathinfo($newName, PATHINFO_FILENAME));
            $this->getFileMapper()->save($this->getFile());
        }
        else {
            throw new Sabre\DAV\Exception\Forbidden('Permission denied to modify file');
        }
    }


    /**
     * @return resource
     */
    public function get()
    {
        return $this->getFile()->readStream();
    }


    /**
     * Modify file
     * @param resource $data
     * @throws Sabre\DAV\Exception\Forbidden
     */
    public function put($data)
    {
        if ($this->getAccessor()->getFolderAccess($_SESSION['user'], array($this->getFolder()->getId())) >= UserAccess::ACCESS_WRITE) {

            try {
                // accept the data as a new storage
                $storage = Schoolbox\Resource\Storage\Mapper::getInstance()->acceptStream(
                    $this->getFile()->getBasename(), $data, false
                );

                // point this file to the new content

                $this->getFile()->setStorage($storage);
                $this->getFileMapper()->save($this->getFile());

            } catch (Schoolbox\File\Exception $e) {
                // ...? noop? throw?
            }
        }
        else {
            throw new Sabre\DAV\Exception\Forbidden('Permission denied to modify file');
        }
    }


    /**
     * @return int
     */
    public function getSize()
    {
        return $this->file->getSize();
    }


    /**
     * @return string
     */
    public function getETag()
    {
        return $this->file->getHash();
    }


    /**
     * @return void
     * @throws Sabre\DAV\Exception\Forbidden
     */
    public function delete()
    {
        if ($this->getAccessor()->getFolderAccess($_SESSION['user'], array($this->getFolder()->getId())) >= UserAccess::ACCESS_WRITE) {
            $this->getFileMapper()->delete($this->getFile());
        }
        else {
            throw new Sabre\DAV\Exception\Forbidden('Permission denied to delete file');
        }
    }


    /**
     * @return int
     */
    public function getLastModified()
    {
        return $this->getFile()->getModifiedAt()->getUnixTimeStamp();
    }


    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->getFile()->getMime();
    }


    /**
     * @param string $path
     */
    private function setFileFromPath($path)
    {
        // Path list - strip any blank parts
        $folderList = array_filter(explode('/', $path), 'strlen');
        $filename = pathinfo(array_pop($folderList), PATHINFO_FILENAME);

        $folderId = 0;

        foreach ($folderList as $folderName) {
            $allFolders = $this->getFolderMapper()->findByColumnList(array(
                'name' => $folderName,
                'parent' => $folderId
            ));

            if ($allFolders->count()) {
                $folderId = $allFolders->first()->getId();
                $this->setFolder($allFolders->first());
            }
        }

        $result = $this->getFileMapper()->findByColumnList(
            array(
                'realname' => $filename,
                'category' => $folderId,
            )
        );

        if ($result->count()) {
            $this->file = $result->first();
        } else {
            // ...? noop? throw?
        }
    }


    /**
     * @return \Schoolbox\Resource\File\Model
     */
    private function getFile()
    {
        return $this->file;
    }


    /**
     * @param Schoolbox\Resource\File\Mapper $fileMapper
     * @return $this
     */
    private function setFileMapper($fileMapper)
    {
        $this->fileMapper = $fileMapper;
        return $this;
    }


    /**
     * @return Schoolbox\Resource\File\Mapper
     */
    private function getFileMapper()
    {
        return $this->fileMapper;
    }


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
     * @return \Schoolbox\Resource\Folder\Model
     */
    private function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param \Schoolbox\Resource\Folder\Model $folder
     */
    private function setFolder($folder)
    {
        $this->folder = $folder;
    }

    /**
     * @return \Schoolbox\Permission\Access\Access
     */
    private function getAccessor()
    {
        return $this->accessor;
    }

    /**
     * @param \Schoolbox\Permission\Access\Access $accessor
     */
    private function setAccessor($accessor)
    {
        $this->accessor = $accessor;
    }


}
