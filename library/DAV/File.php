<?php
/**
 * Created by JetBrains PhpStorm.
 * User: jleckie
 * Date: 3/21/12
 * Time: 9:27 PM
 */


class DAV_File extends Sabre\DAV\File
{

    private $folderID;
    private $fileName;
    private $fileHandler;
    private $file;

    /**
     * A category mapper
     *
     * @var \Schoolbox\Resource\CategoryMapper
     */
    private $folderMapper = null;

    function __construct($path)
    {
        $this->setFileHandler();
        $this->setFolderMapper(new \Schoolbox\Resource\CategoryMapper());
        $this->setPath($path);
        $this->setFile();
    }

    public function getName()
    {

        return $this->getFileName();
    }

    public function setName($newName)
    {

        $file = array('id' => $this->getFileID(),
                        'name' => $newName
                        );

        $this->getFileHandler()->update($file);
    }

    public function get()
    {

        /*		if($groupHandler->getPermission($file['category']) < READ_ACCESS)
        			error(5);
        */

        return fopen($this->getRealFilePath(), 'r');

    }

    public function put($data)
    {
        file_put_contents($this->getRealFilePath(), $data);
        $this->getFileHandler()->update(
            array(
                'id'        => $this->getFileID(),
                'file_size' => filesize($this->getRealFilePath()),
            )
        );
    }

    function getSize()
    {

        return filesize($this->getRealFilePath());

    }

    function getETag()
    {

        return '"' . md5_file($this->getRealFilePath()) . '"';

    }

    function delete()
    {
        $this->getFileHandler()->delete($this->getFileID());
    }

    function getLastModified()
    {
        return strtotime($this->file['modified']);
    }

    public function getContentType()
    {

        return $this->file['mime_type'];

    }

    function setPath($path)
    {
        //
        // Path list - strip any blank parts
        $folderList = array_filter(explode('/', $path), 'strlen');
        $this->fileName = array_pop($folderList);

        $parent = 0;

        foreach ($folderList as $folderName) {
            $allFolders = $this->getFolderMapper()->findByColumnList(array(
                'name' => $folderName,
                'parent' => $parent
            ));

            if ($allFolders->count()) {
                $parent = $allFolders->first()->getId();
            }
        }

        $this->folderID = $parent;

    }

    function getFileName()
    {
        return $this->fileName;
    }

    function getParentFolder()
    {
        return $this->folderID;
    }

    function setFileHandler()
    {
        $this->fileHandler = new File;
    }

    /**
     * @return File
     */
    function getFileHandler()
    {
        return $this->fileHandler;
    }

    function setFile()
    {
        $result = $this->getFileHandler()->getByArray(
            array(
                'realname' => $this->getFileName(),
                'category' => $this->getParentFolder(),
            )
        );
        $this->file = $result[0];
    }

    function getFileID()
    {
        return $this->file['id'];
    }

    function getRealFilePath()
    {
        return  Cfg::get('file_dir') .'/'. $this->getFileID() . '.dat';
    }

    /* Getters/Setters
     */

    /**
     * Set folder mapper
     *
     * @param \Schoolbox\Resource\CategoryMapper $folderMapper
     */
    private function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }

    /**
     * Get folder mapper
     *
     * @return \Schoolbox\Resource\CategoryMapper
     */
    private function getFolderMapper()
    {
        return $this->folderMapper;
    }
}
