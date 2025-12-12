<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Response;

use Schoolbox\Learning\Assessment\AbstractResponse;
use Schoolbox\Mapper\Traits\Model\CreatedBy;
use Schoolbox\Mapper\Traits\Model\DeletedBy;
use Schoolbox\Mapper\Traits\Model\UpdatedBy;

class Model extends AbstractResponse
{
    use CreatedBy;
    use UpdatedBy;
    use DeletedBy;

    /**
     * @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model|null
     */
    private ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model $dialogAgent = null;


    /**
     * @var bool|null
     */
    private ?bool $authoritative = null;


    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $startedAt = null;


    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $submittedAt = null;


    /**
     * @var string|null
     */
    private ?string $sessionId = null;


    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $assistanceRequestedAt = null;


    /* Getters/Setters
     */

    /**
     * @return \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model|null
     */
    public function getDialogAgent(): ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model
    {
        return $this->dialogAgent;
    }


    /**
     * @param \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model|null $dialogAgent
     * @return $this
     */
    public function setDialogAgent(?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model $dialogAgent): self
    {
        $this->dialogAgent = $dialogAgent;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getAuthoritative(): ?bool
    {
        return $this->authoritative;
    }


    /**
     * @param bool|null $authoritative
     * @return $this
     */
    public function setAuthoritative(?bool $authoritative): self
    {
        $this->authoritative = $authoritative;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getStartedAt(): ?\Alaress_Date_Format
    {
        return $this->startedAt;
    }


    /**
     * @param \Alaress_Date_Format|null $startedAt
     * @return $this
     */
    public function setStartedAt(?\Alaress_Date_Format $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getSubmittedAt(): ?\Alaress_Date_Format
    {
        return $this->submittedAt;
    }


    /**
     * @param \Alaress_Date_Format|null $submittedAt
     * @return $this
     */
    public function setSubmittedAt(?\Alaress_Date_Format $submittedAt): self
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }


    /**
     * @param string|null $sessionId
     * @return $this
     */
    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getAssistanceRequestedAt(): ?\Alaress_Date_Format
    {
        return $this->assistanceRequestedAt;
    }


    /**
     * @param \Alaress_Date_Format|null $assistanceRequestedAt
     * @return $this
     */
    public function setAssistanceRequestedAt(?\Alaress_Date_Format $assistanceRequestedAt): self
    {
        $this->assistanceRequestedAt = $assistanceRequestedAt;
        return $this;
    }
}
