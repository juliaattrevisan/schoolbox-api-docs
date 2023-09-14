<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

trait CreatedBy
{
    /**
     * @var int|null
     */
    protected ?int $createdBy;

    /**
     * @return int|null
     */
    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    /**
     * @param int|null $createdBy
     *
     * @return $this
     */
    public function setCreatedBy(?int $createdBy)
    {
        $this->createdBy = $createdBy;
        return $this;
    }
}
