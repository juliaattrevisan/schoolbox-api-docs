<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use Alaress\Json;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

/**
 * Model for discussion threads.
 */
class Model implements ISoftDeletable, Json\Serializable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;
    use Json\SerializeTrait;


    /**
     * @var bool
     */
    private $open;


    /**
     * @return bool
     */
    public function getOpen(): ?bool
    {
        return $this->open;
    }


    /**
     * @param bool $open
     * @return static
     */
    public function setOpen(bool $open): self
    {
        $this->open = $open;
        return $this;
    }


    /**
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        $map = [
            'id',
            'open',
            'createdAt',
            'updatedAt',
        ];
        return $map;
    }
}
