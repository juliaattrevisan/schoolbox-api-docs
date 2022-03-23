<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event\Overrides;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model implements ISoftDeletable
{
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;


    /**
     * These consts correspond to the enum values in sis_event_user_overrides
     */
    public const CLEAR = 'clear';
    public const ADD = 'add';
    public const SUBTRACT = 'subtract';


    /**
     * @var int|null
     */
    private ?int $eventId;


    /**
     * @var \Schoolbox\User|null
     */
    private ?\Schoolbox\User $user;


    /**
     * @var string|null
     */
    private ?string $action;


    /**
     * @var int
     */
    private int $sequence;


    /**
     * @return int|null
     */
    public function getEventId(): ?int
    {
        return $this->eventId;
    }


    /**
     * @param int|null $eventId
     * @return Model
     */
    public function setEventId(?int $eventId): self
    {
        $this->eventId = $eventId;
        return $this;
    }


    /**
     * @return \Schoolbox\User|null
     */
    public function getUser(): ?\Schoolbox\User
    {
        return $this->user;
    }


    /**
     * @param \Schoolbox\User|null $user
     * @return Model
     */
    public function setUser(?\Schoolbox\User $user): self
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getAction(): ?string
    {
        return $this->action;
    }


    /**
     * @param string|null $action
     * @return Model
     */
    public function setAction(?string $action): self
    {
        $this->action = $action;
        return $this;
    }


    /**
     * @return int
     */
    public function getSequence(): int
    {
        return $this->sequence;
    }


    /**
     * @param int $sequence
     * @return Model
     */
    public function setSequence(int $sequence): self
    {
        $this->sequence = $sequence;
        return $this;
    }
}
