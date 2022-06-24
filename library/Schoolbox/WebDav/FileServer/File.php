<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\WebDav\FileServer;

use Sabre\DAV;
use Schoolbox\File\Exception as FileException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\File\Model as FileModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\WebDav\HasUserSource;

/**
 * Class File
 *
 * A WebDAV node that represents a file within the Schoolbox file system.
 *
 * @package Schoolbox\WebDav\FileServer
 */
class File extends DAV\File
{
    use HasUserSource;


    /**
     * @var FileModel
     */
    private $file;


    /**
     * @var FileMapper
     */
    private $fileMapper;


    /**
     * A folder mapper
     *
     * @var FolderMapper
     */
    private $folderMapper;


    /**
     * A storage mapper
     *
     * @var StorageMapper
     */
    private $storageMapper;


    /**
     * Folder where file is located
     *
     * @var FolderModel
     */
    private $folder;


    /**
     * Permissions mapper for determining access rights
     *
     * @var Access
     */
    private $accessor;


    /**
     * Constructor
     *
     * @param string          $path
     * @param callable        $userSource
     * @param FolderMapper    $folderMapper
     * @param FileMapper      $fileMapper
     * @param StorageMapper   $storageMapper
     * @param Access          $accessor
     * @param FilesystemProxy $filesystemProxy
     */
    public function __construct(
        $path,
        callable $userSource,
        FolderMapper $folderMapper,
        FileMapper $fileMapper,
        StorageMapper $storageMapper,
        Access $accessor,
        private FilesystemProxy $filesystemProxy,
    ) {
        $this->setFolderMapper($folderMapper);
        $this->setFileMapper($fileMapper);
        $this->setStorageMapper($storageMapper);
        $this->setAccessor($accessor);
        $this->setUserSource($userSource);
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
     * @throws DAV\Exception\Forbidden
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function setName($newName)
    {
        if ($this->getAccessor()->getFolderAccess($this->retrieveUserFromSource(), $this->getFolder()->getId()) >= UserAccess::ACCESS_WRITE) {
            $this->getFile()->setFilename(pathinfo($newName, PATHINFO_FILENAME));
            $this->getFileMapper()->save($this->getFile());
        } else {
            throw new DAV\Exception\Forbidden('Permission denied to modify file');
        }
    }


    /**
     * @return resource
     */
    public function get()
    {
        return $this->filesystemProxy->readStream($this->file);
    }


    /**
     * Modify file
     * @param resource $data
     * @throws DAV\Exception\Forbidden
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function put($data)
    {
        if ($this->getAccessor()->getFolderAccess($this->retrieveUserFromSource(), $this->getFolder()->getId()) >= UserAccess::ACCESS_WRITE) {
            try {
                // accept the data as a new storage
                $storage = $this->getStorageMapper()->acceptStream(
                    $this->getFile()->getBasename(),
                    $data,
                    false
                );

                // point this file to the new content
                $this->getFile()->setStorage($storage);
                $this->getFileMapper()->save($this->getFile());
            } catch (FileException $e) {
                // ...? noop? throw?
            }
        } else {
            throw new DAV\Exception\Forbidden('Permission denied to modify file');
        }

        return null;
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
     * @throws DAV\Exception\Forbidden
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function delete()
    {
        if ($this->getAccessor()->getFolderAccess($this->retrieveUserFromSource(), $this->getFolder()->getId()) >= UserAccess::ACCESS_WRITE) {
            $this->getFileMapper()->delete($this->getFile());
        } else {
            throw new DAV\Exception\Forbidden('Permission denied to delete file');
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
     * @return FileModel
     */
    private function getFile()
    {
        return $this->file;
    }


    /**
     * @param FileMapper $fileMapper
     * @return $this
     */
    private function setFileMapper($fileMapper)
    {
        $this->fileMapper = $fileMapper;
        return $this;
    }


    /**
     * @return FileMapper
     */
    private function getFileMapper()
    {
        return $this->fileMapper;
    }


    /**
     * Set folder mapper
     *
     * @param FolderMapper $folderMapper
     */
    private function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }


    /**
     * Get folder mapper
     *
     * @return FolderMapper
     */
    private function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @return FolderModel
     */
    private function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param FolderModel $folder
     */
    private function setFolder($folder)
    {
        $this->folder = $folder;
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
}
