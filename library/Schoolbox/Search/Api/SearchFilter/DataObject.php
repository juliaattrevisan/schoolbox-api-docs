<?php
/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\SearchFilter;

use Alaress_Date_Format;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

/**
 * Data object for a set of search filters.
 */
class DataObject
{
    /**
     * Constructor
     *
     * @param array<string>|null       $types    if defined, return only results of this type.
     * @param Alaress_Date_Format|null $fromDate if defined, return only results created after this date.
     * @param Alaress_Date_Format|null $toDate   if defined, return only results created before this date.
     * @param User|null                $creator  if defined, return only results created by this user.
     * @param Folder\Model|null        $folder   if defined, return only results within this folder.
     */
    public function __construct(
        public ?array $types,
        public ?Alaress_Date_Format $fromDate,
        public ?Alaress_Date_Format $toDate,
        public ?User $creator,
        public ?Folder\Model $folder,
    ) {
    }
}
