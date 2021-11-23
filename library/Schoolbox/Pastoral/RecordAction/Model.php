<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\RecordAction;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\Pastoral\Action\Model as Action;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\User;

/**
 * Class Model
 *
 * @package Schoolbox\Pastoral\RecordAction
 */
class Model implements ISoftDeletable
{
    use ModelTraits\Id;
    use ModelTraits\UpdatedAtDate;
    use ModelTraits\DeletedAtDate;

    /**
     * Pastoral
     * @var Pastoral|null
     */
    private ?Pastoral $pastoral = null;

    /**
     * @var Action|null
     */
    private ?Action $action = null;

    /**
     * Completion status
     * @var bool
     */
    private bool $completed = false;

    /**
     * External id for this record action (PastoralCareActionsSeq)
     * @var string|null
     */
    private ?string $externalId = null;

    /**
     * @var User|null
     */
    private ?User $updatedBy = null;

    /**
     * @return string|null
     */
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    /**
     * @param string|null $externalId
     * @return static
     */
    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    /**
     * @return Pastoral|null
     */
    public function getPastoral(): ?Pastoral
    {
        return $this->pastoral;
    }

    /**
     * @param Pastoral|null $pastoral
     * @return static
     */
    public function setPastoral(?Pastoral $pastoral): self
    {
        $this->pastoral = $pastoral;
        return $this;
    }

    /**
     * @return Action|null
     */
    public function getAction(): ?Action
    {
        return $this->action;
    }

    /**
     * @param Action|null $action
     * @return static
     */
    public function setAction(?Action $action): self
    {
        $this->action = $action;
        return $this;
    }

    /**
     * @return bool
     */
    public function getCompleted(): bool
    {
        return $this->completed;
    }

    /**
     * @param bool $completed
     * @return static
     */
    public function setCompleted(bool $completed): self
    {
        $this->completed = $completed;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    /**
     * @param User|null $updatedBy
     * @return static
     */
    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }
}
