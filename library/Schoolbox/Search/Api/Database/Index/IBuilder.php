<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\Index;

use Alaress_Date_Format;
use Schoolbox\Search\Index\Model;

/**
 * Interface for index builder classes.
 */
interface IBuilder
{
    /**
     * Build a collection of search indices which were updated after `$date`.
     *
     * This list is intended to override any existing indices with the same
     * identifier.
     *
     * @param Alaress_Date_Format $date Only fetch indices updated after this
     *                                  date: this should be the last date on
     *                                  which the search index was populated.
     * @return iterable<Model>
     */
    public function buildUpdates(Alaress_Date_Format $date): iterable;

    /**
     * Build a collection of search indices which were deleted after `$date`.
     *
     * This list is intended to delete any indices which match the identifiers.
     *
     * @param Alaress_Date_Format $date Only fetch indices updated after this
     *                                  date: this should be the last date on
     *                                  which the search index was populated.
     * @return iterable<string>
     */
    public function buildDeletes(Alaress_Date_Format $date): iterable;
}
