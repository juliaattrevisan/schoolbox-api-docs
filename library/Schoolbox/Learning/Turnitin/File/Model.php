<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\File;

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
     * Consts that elucidate the current state of a turnitin process
     *
     * Note that when you try to get more granular status reports about the
     * turnitin callback workflow you'll end up having to expand the available
     * strings here as turnitin can die in multiple places with the same
     * kind of error string
     *
     * These statuses update at the start of their respective duties
     */

    /* We saved a student's submission in schoolbox and set up the turnitin record
     * in the instance, but we haven't told turnitin about this document yet.
     */
    public const STATUS_TRANSMIT_PENDING = 'pending';

    /* We started setting up a turnitin Group ("submission box") or found an existing
     * one to use, which is th same thing really
     */
    public const STATUS_TRANSMIT_GROUP = 'group';

    /* We started setting up a turnitin Submission which is a container for student's
     * file contents which is sent separately
     */
    public const STATUS_TRANSMIT_SUBMISSION = 'submission';

    /* We started sending the student's file contents to turnitin
     */
    public const STATUS_TRANSMIT_UPLOADING = 'uploading';

    /* Transmission ended; now awaiting the next phase to begin
     */
    public const STATUS_TRANSMIT_LOADED = 'loaded';

    /* Upload was processed by turnitin and now ready for report generation
     */
    public const STATUS_REPORT_PREPARED = 'prepared';

    /* We started turnitin's analysis process to generate their similarity report
     * This should start when their webhook executes after our successful upload
     */
    public const STATUS_REPORT_ANALYSING = 'analysing';

    /* We started indexing a historical document in turnitin
     */
    public const STATUS_REPORT_INDEXING = 'indexing';

    /* We successfully completed turnitin analysis, yanking their overall percentage
     * and caching that locally
     */
    public const STATUS_COMPLETE = 'complete';

    /* Something went wrong; check the error_code and error_message for more details
     */
    public const STATUS_ERROR = 'error';


    /**
     * Translate mixed Turnitin errors into something human readable
     */
    private const ERROR_TRANSLATION = [
        /* 409 Conflict means multiple things in specific parts of turnitin
         * Uploading a binary into a container already containing a binary,
         * creating a Turnitin Submission in a broken state, etc.
         */
        '404' => 'Turnitin is missing some data about this file.',
        '409' => 'Conflicting data in Turnitin.',
        /* Generic message for these techy reasons
         */
        '400' => 'Turnitin configuration issue.',
        '403' => 'Turnitin configuration issue.',
        '429' => 'Turnitin configuration issue.',
        '500' => 'Turnitin configuration issue.',
        /* Sizes
         */
        '413' => 'File is too big.',
        '422' => 'File is too small.',
        /* Specifically about files, not the general Turnitin process
         */
        'UNSUPPORTED_FILETYPE' => 'This file type is not supported by Turnitin.',
        'PROCESSING_ERROR' => 'Turnitin could not process this file.',
        'CANNOT_EXTRACT_TEXT' => 'This file does not contain enough text for Turnitin to analyse properly.',
        'TOO_LITTLE_TEXT' => 'This file does not contain enough text for Turnitin to analyse properly.',
        'TOO_MUCH_TEXT' => 'This file contains too much text for Turnitin to analyse properly.',
        'TOO_MANY_PAGES' => 'This file contains too many pages for Turnitin to analyse properly.',
        'FILE_LOCKED' => 'The file requires a password to be opened.',
        'CORRUPT_FILE' => 'The file is corrupted.',
    ];


     /**
     * A string that represents the current state of a Similarity Report
     * Denotes whether it has successfully finished or not
     *
     * @var string
     */
    private string $status;


    /**
     * If an error occurred, this is the Turnitin string that they gave to us
     * when it died. It comes with a prefix so you can see where it died in
     * our overall workflow since there are numerous api endpoints where
     * Turnitin might die
     *
     * @var string|null
     */
    private ?string $errorCode = null;


    /**
     * A verbose description of what went wrong
     *
     * @var string|null
     */
    private ?string $errorMessage = null;


    /**
     * The individual submission file for which we are generating a Similarity Report
     * @var int
     */
    private int $studentSubmissionFileId;


    /**
     * A 36-char string that Turnitin uses internally to identify one of their submissions
     * It's like a guid: e.g. b5cf0e76-c1c7-11e8-a355-529269fb1459
     *
     * @var string|null
     */
    private ?string $turnitinSubmissionId = null;


    /**
     * When Turnitin finishes generating its Similarity Report we can remember the answer
     * so we can display it liberally throughout the app without querying Turnitin
     *
     * @var float|null
     */
    private ?float $overallMatchPercentage = null;


    /**
     * @var float|null
     */
    private ?float $internetMatchPercentage = null;


    /**
     * @var float|null
     */
    private ?float $publicationMatchPercentage = null;


    /**
     * @var float|null
     */
    private ?float $submittedWorksMatchPercentage = null;


    /**
     * @var bool|null
     */
    private ?bool $historical = false;


    /**
     * Historical Turnitin documents, while they have a Complete status
     * and also a turnitin_submission_id, do not come with an actual Report
     * that humans can see. Historical Turnitin documents only get their
     * contents added to the turnitin brain
     *
     * This method decides whether a link can be shown to the user -
     * it must be a normal non-historical turnitin document that got to
     * Complete status
     *
     * @return bool
     */
    public function hasReport(): bool
    {
        return $this->isComplete() && !$this->getHistorical();
    }


    /**
     * Turnitin report finished processing
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        return self::STATUS_COMPLETE === $this->getStatus();
    }


    /**
     * Turnitin report still processing - can be in any of these turnitin states
     *
     * @return bool
     */
    public function isProcessing(): bool
    {
        return \in_array($this->getStatus(), [
            self::STATUS_TRANSMIT_GROUP,
            self::STATUS_TRANSMIT_SUBMISSION,
            self::STATUS_TRANSMIT_UPLOADING,
            self::STATUS_TRANSMIT_LOADED,
            self::STATUS_REPORT_PREPARED,
            self::STATUS_REPORT_ANALYSING,
            self::STATUS_REPORT_INDEXING
        ]);
    }


    /**
     * Turnitin report died for any number of reasons
     *
     * @return bool
     */
    public function isError(): bool
    {
        return self::STATUS_ERROR === $this->getStatus();
    }


    /**
     * Turnitin report pending
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return self::STATUS_TRANSMIT_PENDING === $this->getStatus();
    }


    /**
     * Get the human readable error for the current status
     *
     * This is used to translate error codes like 409, TOO_LITTLE_TEXT, etc into
     * something fit for the average citizen who don't talky a developer words
     *
     * Unknown translations return the error_message value instead
     *
     * Note that http status codes mean different things in different places.
     *
     * @return string|null
     */
    public function getHumanReadableError(): ?string
    {
        if (null === $this->errorCode) {
            return null;
        }

        /* Try to find a human readable version; if not, you get the nerd words
         */
        if (array_key_exists($this->errorCode, self::ERROR_TRANSLATION)) {
            return self::ERROR_TRANSLATION[$this->errorCode];
        }

        return $this->errorMessage;
    }


    /* Getters/Setters
     */

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }


    /**
     * @param string $status
     * @return Model
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }


    /**
     * @param string|null $errorCode
     * @return Model
     */
    public function setErrorCode(?string $errorCode): self
    {
        $this->errorCode = $errorCode;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }


    /**
     * @param string|null $errorMessage
     * @return Model
     */
    public function setErrorMessage(?string $errorMessage): self
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
     * @return string|null
     */
    public function getTurnitinSubmissionId(): ?string
    {
        return $this->turnitinSubmissionId;
    }


    /**
     * @param string|null $turnitinSubmissionId
     * @return Model
     */
    public function setTurnitinSubmissionId(?string $turnitinSubmissionId): self
    {
        $this->turnitinSubmissionId = $turnitinSubmissionId;
        return $this;
    }


    /**
     * @return float|null
     */
    public function getOverallMatchPercentage(): ?float
    {
        return $this->overallMatchPercentage;
    }


    /**
     * @param float|null $overallMatchPercentage
     * @return Model
     */
    public function setOverallMatchPercentage(?float $overallMatchPercentage): self
    {
        $this->overallMatchPercentage = $overallMatchPercentage;
        return $this;
    }


    /**
     * @return float|null
     */
    public function getInternetMatchPercentage(): ?float
    {
        return $this->internetMatchPercentage;
    }


    /**
     * @param float|null $internetMatchPercentage
     * @return Model
     */
    public function setInternetMatchPercentage(?float $internetMatchPercentage): self
    {
        $this->internetMatchPercentage = $internetMatchPercentage;
        return $this;
    }


    /**
     * @return float|null
     */
    public function getPublicationMatchPercentage(): ?float
    {
        return $this->publicationMatchPercentage;
    }


    /**
     * @param float|null $publicationMatchPercentage
     * @return Model
     */
    public function setPublicationMatchPercentage(?float $publicationMatchPercentage): self
    {
        $this->publicationMatchPercentage = $publicationMatchPercentage;
        return $this;
    }


    /**
     * @return float|null
     */
    public function getSubmittedWorksMatchPercentage(): ?float
    {
        return $this->submittedWorksMatchPercentage;
    }


    /**
     * @param float|null $submittedWorksMatchPercentage
     * @return Model
     */
    public function setSubmittedWorksMatchPercentage(?float $submittedWorksMatchPercentage): self
    {
        $this->submittedWorksMatchPercentage = $submittedWorksMatchPercentage;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getHistorical(): ?bool
    {
        return $this->historical;
    }


    /**
     * @param bool|null $historical
     * @return Model
     */
    public function setHistorical(?bool $historical): self
    {
        $this->historical = $historical;
        return $this;
    }
}
