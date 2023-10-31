<?php
/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\SearchFilter;

use Alaress_Date_Format;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder;

/**
 * Factory for creating DataObjects.
 */
class DataObjectFactory
{
    public function __construct(private UserMapper $userMapper) {}

    /**
     * Build a search filter data object, for passing into the cursor.
     *
     * @param string|null       $filterQueryString The filter from the query string, if any.
     * @param Folder\Model|null $folder            If provided, the folder to filter search results to.
     *                                             (This is never provided via the query string.)
     * @return DataObject
     * @throws Exception if the filter has invalid data
     * @throws MapperException\NotFound  if the creator is set, and cannot be found
     */
    public function build(?string $filterQueryString, ?Folder\Model $folder): DataObject
    {
        if (!isset($filterQueryString)) {
            return new DataObject(null, null, null, null, $folder);
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
                throw new Exception("Invalid filter: date.from ('{$decodedFilter['date']['from']}') is not an RFC3339 date", 0, $e);
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
                throw new Exception("Invalid filter: date.to ('{$decodedFilter['date']['to']}') is not an RFC3339 date", 0, $e);
            }
        }

        $creator = null;
        if (isset($decodedFilter['creator']['id'])) {
            if (!is_int($decodedFilter['creator']['id'])) {
                throw new Exception('Invalid filter: creator.id is not an integer');
            }
            try {
                $creator = $this->userMapper->findById($decodedFilter['creator']['id']);
            } catch (MapperException $e) {
                // Can't search for things created by an unknown user:
                // raise an error?
                throw new MapperException\NotFound('Invalid filter: creator.id does not match known user', 0, $e);
            }
        }

        return new DataObject($types, $fromDate, $toDate, $creator, $folder);
    }
}
