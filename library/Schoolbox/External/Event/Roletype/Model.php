<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event\Roletype;

use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model
{
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;


    /**
     * @var int|null
     */
    private ?int $eventId;


    /**
     * @var string|null
     */
    private ?string $roleType;


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
     * @return string|null
     */
    public function getRoleType(): ?string
    {
        return $this->roleType;
    }


    /**
     * @param string|null $roleType
     * @return Model
     */
    public function setRoleType(?string $roleType): self
    {
        $this->roleType = $roleType;
        return $this;
    }
}
