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
     * Extract the item ID out of `$item`, for use in `buildUpdates` and
     * `buildDeletes` if updating indices for a single item.
     *
     * @param object $item The item to update indices for.
     * @return int|string|null The item ID, or `null` if this builder doesn't work with this type of item.
     */
    public function extractItemId(object $item): int|string|null;

    /**
     * Build a collection of search indices which were updated between
     * `$fromDate` and `$toDate`.
     *
     * This list is intended to override any existing indices with the same
     * identifier.
     *
     * @param Alaress_Date_Format|null $fromDate If provided, only build indices updated at or after this date.
     * @param Alaress_Date_Format|null $toDate   If provided, only build indices updated at or before this date.
     * @param int|string|null          $itemId   If provided, only build indices for this item ID.
     *
     * @return iterable<Model> A collection of search indices to create.
     */
    public function buildUpdates(
        ?Alaress_Date_Format $fromDate,
        ?Alaress_Date_Format $toDate,
        int|string|null $itemId,
    ): iterable;

    /**
     * Build a collection of search indices which were deleted between
     * `$fromDate` and `$toDate`.
     *
     * This list is intended to delete any indices which match the identifiers.
     *
     * @param Alaress_Date_Format|null $fromDate If provided, only clear indices updated at or after this date.
     * @param Alaress_Date_Format|null $toDate   If provided, only clear indices updated at or before this date.
     * @param int|string|null          $itemId   If provided, only clear indices for this item ID.
     * @return iterable<Model> A collection of search indices to delete.
     */
    public function buildDeletes(
        ?Alaress_Date_Format $fromDate,
        ?Alaress_Date_Format $toDate,
        int|string|null $itemId,
    ): iterable;
}
