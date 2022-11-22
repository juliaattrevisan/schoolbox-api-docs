<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox;

/**
 * Interface that defines a model class as able to be soft-deletable.
 */
interface ISoftDeletable
{
    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted(): bool;
}
