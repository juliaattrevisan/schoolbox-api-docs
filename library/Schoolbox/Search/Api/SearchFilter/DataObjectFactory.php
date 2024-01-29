<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\SearchFilter;

use Alaress_Date_Format;
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
    public function __construct(private UserMapper $userMapper)
    {
    }

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

                // Expand parent types to include child types.
                // This is done so API users can specify filter 'user' to mean "'user' plus all subtypes of 'user'".
                // Filter types and subtypes are defined in ResultTypes.ts.  Types/subtypes used below must match that file.
                $typesAsKeys = array_fill_keys($types, null);
                foreach (array_keys($typesAsKeys) as $type) {
                    if ($type === 'assessment') {
                        $typesAsKeys['assessment.dueWork'] = null;
                        $typesAsKeys['assessment.lessonPlan'] = null;
                        $typesAsKeys['assessment.project'] = null;
                        $typesAsKeys['assessment.lti'] = null;
                        $typesAsKeys['assessment.task'] = null;
                        $typesAsKeys['assessment.quiz'] = null;
                    }
                    if ($type === 'file') {
                        $typesAsKeys['file.audio'] = null;
                        $typesAsKeys['file.document'] = null;
                        $typesAsKeys['file.image'] = null;
                        $typesAsKeys['file.video'] = null;
                        $typesAsKeys['file.other'] = null;
                    }
                    if ($type === 'page') {
                        $typesAsKeys['page.class'] = null;
                        $typesAsKeys['page.course'] = null;
                        $typesAsKeys['page.homepage'] = null;
                        $typesAsKeys['page.unit'] = null;
                        $typesAsKeys['page.folder'] = null;
                        $typesAsKeys['page.group'] = null;
                    }
                    if ($type === 'user') {
                        $typesAsKeys['user.guest'] = null;
                        $typesAsKeys['user.parent'] = null;
                        $typesAsKeys['user.staff'] = null;
                        $typesAsKeys['user.student'] = null;
                    }
                }
                $types = array_keys($typesAsKeys);
            } else {
                throw new Exception('Invalid filter: types is not a list of valid types');
            }
        }

        $fromDate = null;
        if (isset($decodedFilter['date']['from'])) {
            try {
                $fromDate = new Alaress_Date_Format($decodedFilter['date']['from'], Alaress_Date_Format::DATABASE_DATE_FORMAT);
            } catch (InvalidArgumentException $e) {
                throw new Exception("Invalid filter: date.from ('{$decodedFilter['date']['from']}') is not in expected format", 0, $e);
            }
        }

        $toDate = null;
        if (isset($decodedFilter['date']['to'])) {
            try {
                $toDate = new Alaress_Date_Format($decodedFilter['date']['to'], Alaress_Date_Format::DATABASE_DATE_FORMAT);
            } catch (InvalidArgumentException $e) {
                throw new Exception("Invalid filter: date.to ('{$decodedFilter['date']['to']}') is not in expected format", 0, $e);
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
