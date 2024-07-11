<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

use Alaress_Date_Format;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

/**
 * Model for relation between Kami data and:
 * * student submission file annotations
 * * submission box worksheet templates
 * * student submission worksheets
 */
class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    public const STATUS_QUEUED = 'queued';          // Initial state. Inserted when we save a eligible submission file
    public const STATUS_UPLOADING = 'uploading';    // As soon as we attempt to upload it. Transient state.
    public const STATUS_FAILED = 'failed';          // Unable to be uploaded to Kami. May be permanent or transient
    public const STATUS_PROCESSING = 'processing';  // Accepted by Kami. Transient state.
    public const STATUS_DONE = 'done';              // As reported by Kami. End state.
    public const STATUS_ERROR = 'error';            // As reported by Kami. Probably an end state.

    /**
     * Types for this Kami upload:
     * * TYPE_STUDENT_SUBMISSION_FILE_ANNOTATION: annotations on a file a student has submitted
     * * TYPE_SUBMISSION_BOX_WORKSHEET_TEMPLATE:  a worksheet template, uploaded by a teacher
     * * TYPE_STUDENT_SUBMISSION_WORKSHEET:       a worksheet, created from a template by a student
     */
    public const TYPE_STUDENT_SUBMISSION_FILE_ANNOTATION = 'annotation';
    public const TYPE_SUBMISSION_BOX_WORKSHEET_TEMPLATE  = 'worksheet_template';
    public const TYPE_STUDENT_SUBMISSION_WORKSHEET       = 'worksheet';

    /**
     * Supported mime types
     *
     * @var array<string, bool>
     */
    public const ALLOWED_FILE_TYPES = [
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

    /**
     * The type of document: how it relates to something in Schoolbox.
     *
     * @var self::TYPE_*
     */
    private string $type;

    /**
     * The student submission file to which this Kami upload reference related,
     * if there is one.
     */
    private ?SingleFile\Model $studentSubmissionFile = null;

    /**
     * The submission box for which this upload supplies a worksheet template,
     * if any.
     */
    private ?SubmissionBox $worksheetTemplateSubmissionBox = null;

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
     * Null if not yet successfully uploaded into Kami
     *
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $kamiCreatedAt = null;

    /**
     * The number of times the Kami endpoint has been polled.
     * @var int|null
     */
    private ?int $pollCount = null;

    /**
     * The job reference for polling the kami download endpoint
     *
     * @var string|null
     */
    private ?string $jobReference = null;

    /**
     * @return self::TYPE_*
     */
    public function getType(): string
    {
        return $this->type;
    }


    /**
     * @param self::TYPE_* $type
     * @return $this
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }


    /**
     * @return SingleFile\Model|null
     */
    public function getStudentSubmissionFile(): ?SingleFile\Model
    {
        return $this->studentSubmissionFile;
    }


    /**
     * @param SingleFile\Model|null $studentSubmissionFile
     * @return $this|self
     */
    public function setStudentSubmissionFile(?SingleFile\Model $studentSubmissionFile): self
    {
        $this->studentSubmissionFile = $studentSubmissionFile;
        return $this;
    }


    /**
     * @return SubmissionBox|null
     */
    public function getWorksheetTemplateSubmissionBox(): ?SubmissionBox
    {
        return $this->worksheetTemplateSubmissionBox;
    }


    /**
     * @param SubmissionBox|null $worksheetTemplateSubmissionBox
     * @return $this
     */
    public function setWorksheetTemplateSubmissionBox(?SubmissionBox $worksheetTemplateSubmissionBox): self
    {
        $this->worksheetTemplateSubmissionBox = $worksheetTemplateSubmissionBox;
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
     * @return self::STATUS_*
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
     * @return Alaress_Date_Format|null
     */
    public function getKamiCreatedAt(): ?Alaress_Date_Format
    {
        return $this->kamiCreatedAt;
    }


    /**
     * @param Alaress_Date_Format|null $kamiCreatedAt
     *
     * @return static
     */
    public function setKamiCreatedAt(?Alaress_Date_Format $kamiCreatedAt): self
    {
        $this->kamiCreatedAt = $kamiCreatedAt;
        return $this;
    }

    /**
     * @param int|null $pollCount
     * @return Model
     */
    public function setPollCount(?int $pollCount): self
    {
        $this->pollCount = $pollCount;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getPollCount(): ?int
    {
        return $this->pollCount;
    }

    /**
     * @param string|null $jobReference
     * @return Model
     */
    public function setJobReference(?string $jobReference): self
    {
        $this->jobReference = $jobReference;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getJobReference(): ?string
    {
        return $this->jobReference;
    }
}
