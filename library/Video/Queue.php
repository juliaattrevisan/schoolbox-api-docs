<?php
/**
 * Video Processing Queue
 *
 * This is the standard object
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2012 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;

class Storage_Resource {
    private $id;
    private $createdOn;
    private $processedOn;
    private $fileId;
    private $status;

    public function setCreatedOn($createdOn)
    {
        $this->createdOn = $createdOn;
    }

    public function getCreatedOn()
    {
        return $this->createdOn;
    }

    public function setFileId($fileId)
    {
        $this->fileId = $fileId;
    }

    public function getFileId()
    {
        return $this->fileId;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setProcessedOn($processedOn)
    {
        $this->processedOn = $processedOn;
    }

    public function getProcessedOn()
    {
        return $this->processedOn;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function getStatus()
    {
        return $this->status;
    }
}