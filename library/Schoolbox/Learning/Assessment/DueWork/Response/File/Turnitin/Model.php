<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

/**
 * Represents data from the turnitin_report db table
 */
class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;


    /**
     * An int that represents the current state of a Similarity Report
     * Denotes whether it has successfully finished or not
     *
     * @var int
     */
    private int $status;


    /**
     * If an error occurred, this is the Turnitin string that they gave to us
     * when it died. It comes with a prefix so you can see where it died in
     * our overall workflow since there are numerous api endpoints where
     * Turnitin might die
     *
     * @var string
     */
    private string $errorCode;


    /**
     * A verbose description of what went wrong
     *
     * @var string
     */
    private string $errorMessage;


    /**
     * The individual submission file for which we are generating a Similarity Report
     * @var int
     */
    private int $studentSubmissionFileId;


    /**
     * A 36-char string that Turnitin uses internally to identify one of their submissions
     * It's like a guid: e.g. b5cf0e76-c1c7-11e8-a355-529269fb1459
     *
     * @var string
     */
    private string $turnitinSubmissionId;


    /**
     * When Turnitin finishes generating its Similarity Report we can remember the answer
     * so we can display it liberally throughout the app without querying Turnitin
     *
     * @var float
     */
    private float $overallMatchPercentage;


    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }


    /**
     * @param int $status
     * @return Model
     */
    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }


    /**
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }


    /**
     * @param string $errorCode
     * @return Model
     */
    public function setErrorCode(string $errorCode): self
    {
        $this->errorCode = $errorCode;
        return $this;
    }


    /**
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }


    /**
     * @param string $errorMessage
     * @return Model
     */
    public function setErrorMessage(string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }


    /**
     * @return int
     */
    public function getStudentSubmissionFileId(): int
    {
        return $this->studentSubmissionFileId;
    }


    /**
     * @param int $studentSubmissionFileId
     * @return Model
     */
    public function setStudentSubmissionFileId(int $studentSubmissionFileId): self
    {
        $this->studentSubmissionFileId = $studentSubmissionFileId;
        return $this;
    }


    /**
     * @return string
     */
    public function getTurnitinSubmissionId(): string
    {
        return $this->turnitinSubmissionId;
    }


    /**
     * @param string $turnitinSubmissionId
     * @return Model
     */
    public function setTurnitinSubmissionId(string $turnitinSubmissionId): self
    {
        $this->turnitinSubmissionId = $turnitinSubmissionId;
        return $this;
    }


    /**
     * @return float
     */
    public function getOverallMatchPercentage(): float
    {
        return $this->overallMatchPercentage;
    }


    /**
     * @param float $overallMatchPercentage
     * @return Model
     */
    public function setOverallMatchPercentage(float $overallMatchPercentage): self
    {
        $this->overallMatchPercentage = $overallMatchPercentage;
        return $this;
    }
}
