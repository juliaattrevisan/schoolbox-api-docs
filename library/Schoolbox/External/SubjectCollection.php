<?php

/**
 * Generic subject collection object.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External;

use Schoolbox\Collection;

class SubjectCollection extends Collection
{
    /**
     * Returns folder ids of subjects
     * @return array
     */
    public function getIds(): array
    {
        return $this->map(
            static function (array $subject) {
                return $subject['id'];
            }
        )->getAll();
    }
}
