<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

trait DeletedBy
{
    /**
     * @var int|null
     */
    protected ?int $deletedBy = null;

    /**
     * @return int|null
     */
    public function getDeletedBy(): ?int
    {
        return $this->deletedBy;
    }

    /**
     * @param int|null $deletedBy
     *
     * @return $this
     */
    public function setDeletedBy(?int $deletedBy)
    {
        $this->deletedBy = $deletedBy;
        return $this;
    }
}
