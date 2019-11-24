<?php
/**
 * Exception that is thrown when a user requests a deleted object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Exception;

use Schoolbox\Mapper\Exception as BaseException;
use Schoolbox\ISoftDeletable;

class Deleted extends BaseException {
    protected $message = 'Object was deleted';

    /**
     * @var ISoftDeletable
     */
    protected $deleted_object;

    /**
     * @param ISoftDeletable $deleted_object
     * @return Deleted
     */
    public function setDeletedObject(ISoftDeletable $deleted_object): self
    {
        $this->deleted_object = $deleted_object;
        return $this;
    }

    /**
     * @return ISoftDeletable
     */
    public function getDeletedObject() {
        return $this->deleted_object;
    }
}
