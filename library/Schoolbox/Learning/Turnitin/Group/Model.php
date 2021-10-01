<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Group;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

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

    /* The Due Work Submission Box got changed somehow, but we don't know how
     * We need to investigate further to see if any files were changed
     */
    public const STATUS_TRANSMIT_PENDING = 'pending';

    /* We need to create a group for this submission box; it didn't already exist
     */
    public const STATUS_TRANSMIT_GROUP = 'group';

    /* We started sending the group attachment's file contents to turnitin
     */
    public const STATUS_TRANSMIT_UPLOADING = 'uploading';

    /* We successfully uploaded the group attachment file in its entirety
     */
    public const STATUS_COMPLETE = 'complete';

    /* Something went wrong; check the error_code and error_message for more details
     */
    public const STATUS_ERROR = 'error';


    /**
     * A string that represents the current state of a group attachment
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
     * @var int|null
     */
    private ?int $submissionBoxId = null;


    /**
     * @var string|null
     */
    private ?string $groupId = null;


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
     * @return $this
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
     * @return int|null
     */
    public function getSubmissionBoxId(): ?int
    {
        return $this->submissionBoxId;
    }


    /**
     * @param int|null $submissionBoxId
     * @return $this
     */
    public function setSubmissionBoxId(?int $submissionBoxId): self
    {
        $this->submissionBoxId = $submissionBoxId;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getGroupId(): ?string
    {
        return $this->groupId;
    }


    /**
     * @param string|null $groupId
     * @return $this
     */
    public function setGroupId(?string $groupId): self
    {
        $this->groupId = $groupId;
        return $this;
    }
}
