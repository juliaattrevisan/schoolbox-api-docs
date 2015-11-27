<?php
/**
 * Edumate-only result iterator wrapper - changes all result keys to lowercase
 * because Edumate is like that.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Edumate\Base;

use IteratorIterator;

class ResultIterator extends IteratorIterator
{
    /**
     * @inheritDoc
     */
    public function current()
    {
        return array_change_key_case($this->getInnerIterator()->current(), CASE_LOWER);
    }
}
