<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\InlineGrader\Kami;

use Alaress_Date_Format;

/**
 * Represents a kami document.
 *
 * Intentionally no setters, this object is immutable, being created by Kami
 */
class Document
{

    const FILE_STATUS_PROCESSING = 'processing';
    const FILE_STATUS_DONE = 'done';
    const FILE_STATUS_ERROR = 'error';

    /** @var string */
    private $id;

    /** @var string */
    private $name;

    /** @var string */
    private $fileStatus;

    /** @var null|string */
    private $fileErrorMessage;

    /** @var Alaress_Date_Format */
    private $createdAt;


    /**
     * @param string              $id
     * @param string              $name
     * @param string              $fileStatus
     * @param null|string         $fileErrorMessage
     * @param Alaress_Date_Format $createdAt
     */
    public function __construct($id, $name, $fileStatus, $fileErrorMessage, Alaress_Date_Format $createdAt)
    {
        $this->id               = $id;
        $this->name             = $name;
        $this->fileStatus       = $fileStatus;
        $this->fileErrorMessage = $fileErrorMessage;
        $this->createdAt        = $createdAt;
    }


    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * @return string
     */
    public function getFileStatus()
    {
        return $this->fileStatus;
    }


    /**
     * @return bool
     */
    public function isProcessing()
    {
        return self::FILE_STATUS_PROCESSING === $this->fileStatus;
    }


    /**
     * @return bool
     */
    public function isDone()
    {
        return self::FILE_STATUS_DONE === $this->fileStatus;
    }


    /**
     * @return bool
     */
    public function isError()
    {
        return self::FILE_STATUS_ERROR === $this->fileStatus;
    }


    /**
     * Will be null if file status is not 'error'
     *
     * @return null|string
     */
    public function getFileErrorMessage()
    {
        return $this->fileErrorMessage;
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }
}
