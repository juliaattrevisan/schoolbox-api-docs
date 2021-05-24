<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Alaress_Date_Format;

class Model implements ISoftDeletable
{

    const STATUS_QUEUED = 'queued';          // Initial state. Inserted when we save a eligible submission file
    const STATUS_UPLOADING = 'uploading';    // As soon as we attempt to upload it. Transient state.
    const STATUS_FAILED = 'failed';          // Unable to be uploaded to Kami. May be permanent or transient
    const STATUS_PROCESSING = 'processing';  // Accepted by Kami. Transient state.
    const STATUS_DONE = 'done';              // As reported by Kami. End state.
    const STATUS_ERROR = 'error';            // As reported by Kami. Probably an end state.

    /**
     * Supported mime types
     *
     * @var string[]
     */
    const ALLOWED_FILE_TYPES = [
        'doc'     => true,
        'docx'    => true,
        'ppt'     => true,
        'pptx'    => true,
        'xls'     => true,
        'xlsx'    => true,
        'odt'     => true,
        'odp'     => true,
        'ods'     => true,
        'jpg'     => true,
        'jpeg'    => true,
        'gif'     => true,
        'tiff'    => true,
        'png'     => true,
        'pages'   => true,
        'key'     => true,
        'numbers' => true,
        'pdf'     => true,
    ];

    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var SingleFile\Model
     */
    private $studentSubmissionFile;

    /**
     * Null if not yet successfully uploaded into Kami
     *
     * @var null|string
     */
    private $kamiDocumentId;

    /**
     * @var string
     */
    private $status;

    /**
     * The most recent errors reported by kami
     *
     * Even if a previously failed document is later accepted, we retain the most recent error
     * message.
     *
     * @var null|string
     */
    private $errorMessage;

    /**
     * An Alaress_Date_NullDate if not yet successfully uploaded into Kami
     *
     * @var Alaress_Date_Format
     */
    private $kamiCreatedAt;


    /**
     * @return SingleFile\Model
     */
    public function getStudentSubmissionFile(): SingleFile\Model
    {
        return $this->studentSubmissionFile;
    }


    /**
     * @param SingleFile\Model $studentSubmissionFile
     * @return $this|self
     */
    public function setStudentSubmissionFile(SingleFile\Model $studentSubmissionFile): self
    {
        $this->studentSubmissionFile = $studentSubmissionFile;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getKamiDocumentId(): ?string
    {
        return $this->kamiDocumentId;
    }


    /**
     * @param null|string $kamiDocumentId
     * @return $this|self
     */
    public function setKamiDocumentId(?string $kamiDocumentId): self
    {
        $this->kamiDocumentId = $kamiDocumentId;
        return $this;
    }


    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }


    /**
     * @param string $status
     * @return $this|self
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }


    /**
     * @return bool
     */
    public function isStatusQueued(): bool
    {
        return self::STATUS_QUEUED === $this->status;
    }


    /**
     * @return $this|self
     */
    public function setStatusQueued(): self
    {
        return $this->setStatus(self::STATUS_QUEUED);
    }


    /**
     * @return bool
     */
    public function isStatusUploading(): bool
    {
        return self::STATUS_UPLOADING === $this->status;
    }


    /**
     * @return $this|self
     */
    public function setStatusUploading(): self
    {
        return $this->setStatus(self::STATUS_UPLOADING);
    }


    /**
     * @return bool
     */
    public function isStatusFailed(): bool
    {
        return self::STATUS_FAILED === $this->status;
    }


    /**
     * @return $this|self
     */
    public function setStatusFailed(): self
    {
        return $this->setStatus(self::STATUS_FAILED);
    }


    /**
     * @return bool
     */
    public function isStatusProcessing(): bool
    {
        return self::STATUS_PROCESSING === $this->status;
    }


    /**
     * @return $this|self
     */
    public function setStatusProcessing(): self
    {
        return $this->setStatus(self::STATUS_PROCESSING);
    }


    /**
     * @return bool
     */
    public function isStatusDone(): bool
    {
        return self::STATUS_DONE === $this->status;
    }


    /**
     * @return $this|self
     */
    public function setStatusDone(): self
    {
        return $this->setStatus(self::STATUS_DONE);
    }


    /**
     * @return bool
     */
    public function isStatusError(): bool
    {
        return self::STATUS_ERROR === $this->status;
    }


    /**
     * @return $this|self
     */
    public function setStatusError(): self
    {
        return $this->setStatus(self::STATUS_ERROR);
    }


    /**
     * @return null|string
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }


    /**
     * @param null|string $errorMessage
     * @return $this|self
     */
    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }


    /**
     * Has the kami document been uploaded to Kami? Regardless of whether Kami has finished processing
     *
     * @return bool
     */
    public function isUploaded(): bool
    {
        return in_array($this->status, [self::STATUS_PROCESSING, self::STATUS_DONE, self::STATUS_ERROR], true);
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getKamiCreatedAt(): ?Alaress_Date_Format
    {
        return $this->kamiCreatedAt;
    }


    /**
     * @param null|Alaress_Date_Format $kamiCreatedAt
     * @return $this|self
     */
    public function setKamiCreatedAt(?Alaress_Date_Format $kamiCreatedAt): self
    {
        $this->kamiCreatedAt = $kamiCreatedAt;
        return $this;
    }
}
