<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

trait UpdatedBy
{
    /**
     * @var int|null
     */
    protected $updatedBy;

    /**
     * @return int|null
     */
    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    /**
     * @param int|null $updatedBy
     *
     * @return $this
     */
    public function setUpdatedBy(?int $updatedBy)
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }
}
