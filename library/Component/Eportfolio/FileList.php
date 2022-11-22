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
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class FileList extends ListItem implements ISoftDeletable
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

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
}
