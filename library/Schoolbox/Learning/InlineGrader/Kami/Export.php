<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

use Schoolbox\Learning\InlineGrader\Kami\Exception\InvalidStatusException;

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
     * @throws InvalidStatusException if export's status is not recognised
     */
    public function __construct(string $id, string $status, ?string $fileUrl, ?string $errorType)
    {
        $this->id        = $id;
        $this->status    = $status;
        $this->fileUrl   = $fileUrl;
        $this->errorType = $errorType;
        $this->validateStatus();
    }


    /**
     * Validates status of export.
     *
     * @return bool
     * @throws InvalidStatusException if export's status is not recognised
     */
    private function validateStatus(): bool
    {
        if (!($this->isStatusPending() || $this->isStatusDone() || $this->isStatusError())) {
            throw new InvalidStatusException("{$this->getStatus()} is not a valid export status");
        }
        return true;
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
     * @return bool
     */
    public function isStatusPending(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }


    /**
     * @return bool
     */
    public function isStatusDone(): bool
    {
        return self::STATUS_DONE === $this->status;
    }


    /**
     * @return bool
     */
    public function isStatusError(): bool
    {
        return self::STATUS_ERROR === $this->status;
    }


    /**
     * @return null|string
     */
    public function getFileUrl(): ?string
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
