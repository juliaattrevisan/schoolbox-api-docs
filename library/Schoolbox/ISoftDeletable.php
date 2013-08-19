<?php
/**
 * Interface that defines a model class as able to be soft-deletable.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox;

interface ISoftDeletable {
    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted();
}
