<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message;

use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Model for dialog agent response messages.
 */
class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use DeletedAtDate;

    /**
     * The response with which this message is associated.
     *
     * @var Response\Model
     */
    private Response\Model $dialogAgentResponse;

    /**
     * The OpenAI response ID, if this message is from OpenAI.
     */
    private ?string $aiResponseId = null;

    /**
     * The role with which this is associated.
     *
     * @var value-of<Role>
     */
    private string $role;

    /**
     * The status of the response which generated the message.
     *
     * @var string|null
     */
    private ?string $status = null;

    /**
     * The content of the message.
     *
     * @var string|null
     */
    private ?string $content = null;

    /**
     * @return Response\Model
     */
    public function getDialogAgentResponse(): Response\Model
    {
        return $this->dialogAgentResponse;
    }

    /**
     * @param Response\Model $dialogAgentResponse
     * @return $this
     */
    public function setDialogAgentResponse(Response\Model $dialogAgentResponse): self
    {
        $this->dialogAgentResponse = $dialogAgentResponse;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAiResponseId(): ?string
    {
        return $this->aiResponseId;
    }

    /**
     * @param string|null $aiResponseId
     * @return $this
     */
    public function setAiResponseId(?string $aiResponseId): self
    {
        $this->aiResponseId = $aiResponseId;
        return $this;
    }

    /**
     * @return string
     */
    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @param string $role
     * @return $this
     */
    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param string|null $status
     * @return $this
     */
    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * @param string|null $content
     * @return $this
     */
    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }
}
