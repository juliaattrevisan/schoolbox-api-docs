<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Event\Campus;

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
    private ?string $campusName;


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
    public function getCampusName(): ?string
    {
        return $this->campusName;
    }


    /**
     * @param string|null $campusName
     * @return Model
     */
    public function setCampusName(?string $campusName): self
    {
        $this->campusName = $campusName;
        return $this;
    }
}
