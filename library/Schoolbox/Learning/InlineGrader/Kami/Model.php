<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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

    // 'uploading' is before accepted by Kami.
    // The other states are the state most recently reported by Kami.
    const STATUS_UPLOADING = 'uploading';
    const STATUS_PROCESSING = 'processing';
    const STATUS_DONE = 'done';
    const STATUS_ERROR = 'error';

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
    public function isUploading(): bool
    {
        return self::STATUS_UPLOADING === $this->status;
    }


    /**
     * @return bool
     */
    public function isProcessing(): bool
    {
        return self::STATUS_PROCESSING === $this->status;
    }


    /**
     * @return bool
     */
    public function isDone(): bool
    {
        return self::STATUS_DONE === $this->status;
    }


    /**
     * @return bool
     */
    public function isError(): bool
    {
        return self::STATUS_ERROR === $this->status;
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
     * @return Alaress_Date_Format
     */
    public function getKamiCreatedAt(): Alaress_Date_Format
    {
        return $this->kamiCreatedAt;
    }


    /**
     * @param Alaress_Date_Format $kamiCreatedAt
     * @return $this|self
     */
    public function setKamiCreatedAt(Alaress_Date_Format $kamiCreatedAt): self
    {
        $this->kamiCreatedAt = $kamiCreatedAt;
        return $this;
    }
}
