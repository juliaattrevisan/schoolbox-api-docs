<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

use Alaress_Date_Format;
use Schoolbox\Learning\InlineGrader\Kami\Exception\InvalidStatusException;

/**
 * Represents a kami document.
 *
 * Intentionally no setters, this object is immutable, being created by Kami
 */
class Document
{

    // Intentionally distinct from the Kami Model definitions to ensure changes to
    // Kami's API do not require changes to Schoolbox's schema.
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
     * @throws InvalidStatusException if document's status is not recognised
     */
    public function __construct(
        string $id,
        string $name,
        string $fileStatus,
        ?string $fileErrorMessage,
        Alaress_Date_Format $createdAt
    ) {
        $this->id               = $id;
        $this->name             = $name;
        $this->fileStatus       = $fileStatus;
        $this->fileErrorMessage = $fileErrorMessage;
        $this->createdAt        = $createdAt;
        $this->validateStatus();
    }


    /**
     * Validates status of document.
     *
     * @return bool
     * @throws InvalidStatusException if document's status is not recognised
     */
    private function validateStatus(): bool
    {
        if (!($this->isProcessing() || $this->isDone() || $this->isError())) {
            throw new InvalidStatusException("{$this->getFileStatus()} is not a valid document status");
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
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * @return string
     */
    public function getFileStatus(): string
    {
        return $this->fileStatus;
    }


    /**
     * @return bool
     */
    public function isProcessing(): bool
    {
        return self::FILE_STATUS_PROCESSING === $this->fileStatus;
    }


    /**
     * @return bool
     */
    public function isDone(): bool
    {
        return self::FILE_STATUS_DONE === $this->fileStatus;
    }


    /**
     * @return bool
     */
    public function isError(): bool
    {
        return self::FILE_STATUS_ERROR === $this->fileStatus;
    }


    /**
     * Will be null if file status is not 'error'
     *
     * @return null|string
     */
    public function getFileErrorMessage(): ?string
    {
        return $this->fileErrorMessage;
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt(): Alaress_Date_Format
    {
        return $this->createdAt;
    }
}
