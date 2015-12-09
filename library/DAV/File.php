<?php
/**
 * Created by JetBrains PhpStorm.
 * User: jleckie
 * Date: 3/21/12
 * Time: 9:27 PM
 */


class DAV_File extends Sabre\DAV\File
{

    /** @var Schoolbox\Resource\File\Model */
    private $file;

    /**
     * @var Schoolbox\Resource\File\Mapper
     */
    private $fileMapper;

    /**
     * A category mapper
     *
     * @var Schoolbox\Resource\Folder\Mapper
     */
    private $folderMapper;


    /**
     * @param string $path
     */
    public function __construct($path)
    {
        $this->setFileMapper(Schoolbox\Resource\File\Mapper::getInstance());
        $this->setFolderMapper(Schoolbox\Resource\Folder\Mapper::getInstance());
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
     * @param string $newName
     */
    public function setName($newName)
    {
        $this->getFile()->setFilename(pathinfo($newName, PATHINFO_FILENAME));

        $this->getFileMapper()->save($this->getFile());
    }


    /**
     * @return resource
     */
    public function get()
    {
        return $this->getFile()->readStream();
    }


    /**
     * @param resource $data
     */
    public function put($data)
    {
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
     */
    public function delete()
    {
        $this->getFileMapper()->delete($this->getFile());
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
}
