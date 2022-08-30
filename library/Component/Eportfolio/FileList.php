<?php

/**
 * Component_Eportfolio_FileList
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Component\Eportfolio;

use Schoolbox\ISoftDeletable;

class FileList extends ListItem implements ISoftDeletable
{
    /**
     * Id of a published file
     *
     * @var int
     */
    private $fileId;

    /**
     * The page to which a file is published
     *
     * @var int
     */
    private $pageId;

    /**
     * A Schoolbox\MyFile instance
     *
     * @var \Schoolbox\Resource\My\File\Model
     */
    private $file;

    /**
     * When the File List item was created
     *
     * @var \Alaress_Date_Format
     */
    private $created_at;

    /**
     * When the File List item was updated
     * @var \Alaress_Date_Format
     */
    private $updated_at;

    /**
     * When the File List item was softdeleted
     * @var \Alaress_Date_Format
     */
    private $deleted_at;

    /* Getters/Setters
     */

    /**
     * Set file id
     *
     * @param int $fileId
     */
    public function setFileId($fileId)
    {
        $this->fileId = $fileId;
    }

    /**
     * Get file id
     *
     * @return int
     */
    public function getFileId()
    {
        return $this->fileId;
    }

    /**
     * Set created at
     *
     * @param \Alaress_Date_Format $created_at
     */
    public function setCreatedAt($created_at)
    {
        $this->created_at = $created_at;
    }

    /**
     * Get created at
     *
     * @return \Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Set deleted at
     *
     * @param \Alaress_Date_Format $deleted_at
     */
    public function setDeletedAt($deleted_at)
    {
        $this->deleted_at = $deleted_at;
    }

    /**
     * Get deleted at
     *
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deleted_at;
    }

    /**
     * Set updated at
     *
     * @param \Alaress_Date_Format $updated_at
     */
    public function setUpdatedAt($updated_at)
    {
        $this->updated_at = $updated_at;
    }

    /**
     * Get updated at
     *
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    /**
     * Set file
     *
     * @param \Schoolbox\Resource\My\File\Model $file
     */
    public function setFile($file)
    {
        $this->file = $file;
    }

    /**
     * Get file
     *
     * @return \Schoolbox\Resource\My\File\Model
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Set page id
     *
     * @param int $pageId
     */
    public function setPageId($pageId)
    {
        $this->pageId = $pageId;
    }

    /**
     * Get page id
     *
     * @return int
     */
    public function getPageId()
    {
        return $this->pageId;
    }

    /**
     * @inheritDoc
     */
    public function isDeleted()
    {
        return ($this->getDeletedAt() instanceof \Alaress_Date_Format && !$this->getDeletedAt()->isNull());
    }
}
