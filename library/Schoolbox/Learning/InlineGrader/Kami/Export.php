<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

/**
 * Represents the status of a requested export.
 *
 * Exports take some time to execute, so we must poll the API until
 * the export finishes and a URL is provided.
 *
 * Intentionally no setters. This object is immutable, being created by Kami.
 */
class Export
{

    const STATUS_PENDING = 'pending';
    const STATUS_DONE = 'done';
    const STATUS_ERROR = 'error';

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $status;

    /**
     * Null prior to export completion
     *
     * @var null|string
     */
    private $fileUrl;

    /**
     * @var null|string TODO verify data type
     */
    private $errorType;


    /**
     * @param string      $id
     * @param string      $status
     * @param null|string $fileUrl
     * @param null|string $errorType
     */
    public function __construct(string $id, string $status, ?string $fileUrl, ?string $errorType)
    {
        $this->id        = $id;
        $this->status    = $status;
        $this->fileUrl   = $fileUrl;
        $this->errorType = $errorType;
    }


    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }


    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }


    /**
     * @return null|string
     */
    public function getFileUrl()
    {
        return $this->fileUrl;
    }


    /**
     * @return null|string
     */
    public function getErrorType(): ?string
    {
        return $this->errorType;
    }
}
