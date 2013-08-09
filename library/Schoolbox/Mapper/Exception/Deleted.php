<?php
/**
 * Exception that is thrown when a user requests a deleted object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
     */
    public function setDeletedObject(ISoftDeletable $deleted_object) {
        $this->deleted_object = $deleted_object;
    }

    /**
     * @return ISoftDeletable
     */
    public function getDeletedObject() {
        return $this->deleted_object;
    }
}