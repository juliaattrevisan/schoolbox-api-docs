<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Group\Attachment;

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

    /* We saved a staff's submission box file in schoolbox and set up the turnitin record
     * in the instance, but we haven't told turnitin about this document yet.
     */
    public const STATUS_TRANSMIT_PENDING = 'pending';

    /* We started setting up a turnitin Group Attachment which is a container for a group attachment's
     * file contents which is sent separately
     */
    public const STATUS_TRANSMIT_GROUP_ATTACHMENT = 'group_attachment';

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
     * The individual submission box file
     *
     * @var int
     */
    private int $submissionBoxFileId;


    /**
     * The Turnitin Group Identifier to which this Attachment belongs
     *
     * @var string|null
     */
    private ?string $turnitinGroupId = null;


    /**
     * A 36-char string that Turnitin uses internally to identify one of their group attachments
     * It's like a guid: e.g. b5cf0e76-c1c7-11e8-a355-529269fb1459
     *
     * @var string|null
     */
    private ?string $turnitinGroupAttachmentId = null;


    /**
     * Turnitin group attachment finished processing
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        return self::STATUS_COMPLETE === $this->getStatus();
    }


    /**
     * Turnitin group attachment still processing - can be in any of these turnitin states
     *
     * @return bool
     */
    public function isProcessing(): bool
    {
        return $this->getStatus() === self::STATUS_TRANSMIT_UPLOADING;
    }


    /**
     * Turnitin group attachment died for any number of reasons
     *
     * @return bool
     */
    public function isError(): bool
    {
        return self::STATUS_ERROR === $this->getStatus();
    }


    /**
     * Turnitin group attachment pending
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return self::STATUS_TRANSMIT_PENDING === $this->getStatus();
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
    public function getSubmissionBoxFileId(): int
    {
        return $this->submissionBoxFileId;
    }


    /**
     * @param int $submissionBoxFileId
     * @return Model
     */
    public function setSubmissionBoxFileId(int $submissionBoxFileId): self
    {
        $this->submissionBoxFileId = $submissionBoxFileId;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getTurnitinGroupId(): ?string
    {
        return $this->turnitinGroupId;
    }


    /**
     * @param string|null $turnitinGroupId
     * @return $this
     */
    public function setTurnitinGroupId(?string $turnitinGroupId): self
    {
        $this->turnitinGroupId = $turnitinGroupId;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getTurnitinGroupAttachmentId(): ?string
    {
        return $this->turnitinGroupAttachmentId;
    }


    /**
     * @param string|null $turnitinGroupAttachmentId
     * @return Model
     */
    public function setTurnitinGroupAttachmentId(?string $turnitinGroupAttachmentId): self
    {
        $this->turnitinGroupAttachmentId = $turnitinGroupAttachmentId;
        return $this;
    }
}
