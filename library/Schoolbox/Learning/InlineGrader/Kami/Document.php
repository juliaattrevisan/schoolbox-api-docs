<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

use Alaress_Date_Format;

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
