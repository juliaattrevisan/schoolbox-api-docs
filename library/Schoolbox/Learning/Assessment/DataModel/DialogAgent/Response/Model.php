<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response;

use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\CreatedBy;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedBy;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedBy;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use CreatedBy;
    use UpdatedAtDate;
    use UpdatedBy;
    use DeletedAtDate;
    use DeletedBy;


    /**
     * @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model|null
     */
    private ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model $dialogAgent = null;


    /**
     * @var \Schoolbox\User|null
     */
    private ?\Schoolbox\User $learner = null;


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
    private ?\Alaress_Date_Format $lockedAt = null;


    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $submittedAt = null;


    /**
     * @var \Schoolbox\User|null
     */
    private ?\Schoolbox\User $submittedBy = null;


    /**
     * I tried exploring the possibility that this could be an OpenAI\Log\Model instance
     * but I ended up walking around in the base mapper and collection implementation
     * trying to fix this:
     *
     * "mb_convert_encoding(): Argument #1 ($string) must be of type array|string, Schoolbox\Editor\OpenAI\Log\Model given"
     *
     * I eventually had to conclude that because session_id isn't the primary key, there must be
     * some special requirement to deal with this manually. The identifier column presumably has
     * a special treatment, but in the openai log table, session_id is not the primary identifier.
     *
     * For that reason you'll have to live with $session->getSessionId() usage instead, for now,
     * and manually obtain the session's contents separately.
     *
     * @var string|null
     */
    private ?string $sessionId = null;


    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $assistanceRequestedAt = null;

    /**
     * @var GenericCollection<Message\Model>|null
     */
    private ?GenericCollection $messages = null;

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
     * @return \Schoolbox\User|null
     */
    public function getLearner(): ?\Schoolbox\User
    {
        return $this->learner;
    }


    /**
     * @param \Schoolbox\User|null $learner
     * @return $this
     */
    public function setLearner(?\Schoolbox\User $learner): self
    {
        $this->learner = $learner;
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
    public function getLockedAt(): ?\Alaress_Date_Format
    {
        return $this->lockedAt;
    }


    /**
     * @param \Alaress_Date_Format|null $lockedAt
     * @return $this
     */
    public function setLockedAt(?\Alaress_Date_Format $lockedAt): self
    {
        $this->lockedAt = $lockedAt;
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
     * @return \Schoolbox\User|null
     */
    public function getSubmittedBy(): ?\Schoolbox\User
    {
        return $this->submittedBy;
    }


    /**
     * @param \Schoolbox\User|null $submittedBy
     * @return $this
     */
    public function setSubmittedBy(?\Schoolbox\User $submittedBy): self
    {
        $this->submittedBy = $submittedBy;
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


    /**
     * @return GenericCollection<Message\Model>|null
     */
    public function getMessages(): ?GenericCollection
    {
        return $this->messages;
    }


    /**
     * @param GenericCollection<Message\Model>|null $messages
     * @return $this
     */
    public function setMessages(?GenericCollection $messages): self
    {
        $this->messages = $messages;
        return $this;
    }
}
