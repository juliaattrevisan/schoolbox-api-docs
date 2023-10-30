<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\SearchFilter;

use Alaress_Date_Format;
use DateTimeInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Data object for a set of search filters.
 */
class DataObject
{
    public array $types;
    public ?Alaress_Date_Format $fromDate;
    public ?Alaress_Date_Format $toDate;
    public ?User $creator;
    public ?Folder\Model $folder;

    /**
     * Build a search filter data object, for passing into the cursor.
     *
     * @param string|null       $filterQueryString The filter from the query string, if any.
     * @param Folder\Model|null $folder            If provided, the folder to filter search results to.
     *                                             (This is never provided via the query string.)
     * @throws Exception if the filter has invalid data
     * @throws NotFound  if the creator is set, and cannot be found
     */
    public function __construct(?string $filterQueryString, ?Folder\Model $folder, UserMapper $userMapper)
    {
        if (!isset($filterQueryString)) {
            $this->types = [];
            $this->fromDate = null;
            $this->toDate = null;
            $this->creator = null;
            $this->folder = null;
            return;
        }

        $decodedFilter = json_decode($filterQueryString, true);

        $types = null;
        if (isset($decodedFilter['types'])) {
            if (is_array($decodedFilter['types']) && array_is_list($decodedFilter['types'])) {
                $types = $decodedFilter['types'];
            } else {
                throw new Exception('Invalid filter: types is not a list of valid types');
            }
        }

        $fromDate = null;
        if (isset($decodedFilter['date']['from'])) {
            if (strlen($decodedFilter['date']['from']) === 10) {
                // Assume date has been supplied in 'Y-m-d' format, so add default time (start of day) in expected format.
                $decodedFilter['date']['from'] .= 'T00:00:00' . date('P');
            }
            try {
                $fromDate = new Alaress_Date_Format($decodedFilter['date']['from'], DateTimeInterface::RFC3339);
            } catch (InvalidArgumentException $e) {
                throw new Exception('Invalid filter: date.from is not a RFC3339 date', 0, $e);
            }
        }

        $toDate = null;
        if (isset($decodedFilter['date']['to'])) {
            if (strlen($decodedFilter['date']['to']) === 10) {
                // Assume date has been supplied in 'Y-m-d' format, so add default time (end of day) in expected format.
                $decodedFilter['date']['to'] .= 'T23:59:59' . date('P');
            }
            try {
                $toDate = new Alaress_Date_Format($decodedFilter['date']['to'], DateTimeInterface::RFC3339);
            } catch (InvalidArgumentException $e) {
                throw new Exception('Invalid filter: date.to is not a RFC3339 date', 0, $e);
            }
        }

        $creator = null;
        if (isset($decodedFilter['creator']['id'])) {
            if (!is_int($decodedFilter['creator']['id'])) {
                throw new Exception('Invalid filter: creator.id is not an integer');
            }
            try {
                $creator = $userMapper->findById($decodedFilter['creator']['id']);
            } catch (MapperException $e) {
                // Can't search for things created by an unknown user:
                // raise an error?
                throw new NotFound('Invalid filter: creator.id does not match known user', 0, $e);
            }
        }

        $this->types = $types;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->creator = $creator;
        $this->folder = $folder;
    }
}
