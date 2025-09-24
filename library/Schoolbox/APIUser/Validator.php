<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIUser;

use Alaress\Web\Request\Values as WebRequestValues;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Role;

/**
 * Validator of user-related API requests.
 *
 * @package Schoolbox\APIUser
 */
class Validator
{
    public function __construct()
    {
    }

    /**
     * @param WebRequestValues $requestQuery
     * @return array{string, array, int, string}
     * @throws SchoolboxException
     */
    public function validateForApiUserSearch(WebRequestValues $requestQuery): array
    {
        $search = $requestQuery->get('search', '');
        $cursor = $requestQuery->get('cursor', '');
        $limit = $requestQuery->get('limit', 5);
        $filterJson = $requestQuery->get('filter', '');

        if (!is_string($search)) {
            throw new SchoolboxException("Parameter 'search' must have a string value.");
        }

        if (!is_int($limit) && !ctype_digit($limit)) { // Allow digit string for backwards compatibility.
            throw new SchoolboxException("Parameter 'limit' must have an integer value.");
        }
        $limit = (int)$limit; // Set type to that expected according to the API docs.

        if (!is_string($cursor) && !is_int($cursor)) { // Allow integer for backwards compatibility.
            throw new SchoolboxException("Parameter 'cursor' must have a string value.");
        }
        $cursor = (string)$cursor; // Set type to that expected according to the API docs.

        $filter = null;
        if ($filterJson === '') {
            $filter = [];
        } elseif (is_string($filterJson)) {
            try {
                $filter = json_decode($filterJson, true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $filter = null;
            }
        }

        if (!is_array($filter)) {
            throw new SchoolboxException('JSON filter could not be decoded.');
        }

        $this->validateFilter($filter);

        return [$search, $filter, $limit, $cursor];
    }

    /**
     * @param array $filter
     */
    private function validateFilter(array $filter): void
    {
        foreach ($filter as $key => $value) {
            switch ($key) {
                case 'externalId':
                    if (!is_string($value) && !is_int($value)) { // Allow integers also for backwards compatibility.
                        throw new SchoolboxException("Filter 'externalId' must have a string value.");
                    }
                    break;
                case 'username':
                    if (!is_string($value)) {
                        throw new SchoolboxException("Filter 'username' must have a string value.");
                    }
                    break;
                case 'email':
                    if (!is_string($value)) {
                        throw new SchoolboxException("Filter 'email' must have a string value.");
                    }
                    break;
                case 'year':
                    if (!is_array($value)) {
                        // Note 'object' in the exception below refers to a JSON object.  JSON objects will appear here as arrays.
                        throw new SchoolboxException("Filter 'year' must be an object.");
                    }
                    $this->validateIdNameArray('year', $value);
                    break;
                case 'role':
                    if (!is_array($value)) {
                        // Note 'object' in the exception below refers to a JSON object.  JSON objects will appear here as arrays.
                        throw new SchoolboxException("Filter 'role' must be an object.");
                    }
                    $this->validateIdNameArray('role', $value);
                    break;
                case 'roleType':
                    if (!in_array($value, Role::getRoleTypes(), true)) {
                        throw new SchoolboxException(
                            "Filter 'roleType' has unknown value.  Expected one of: " . implode(', ', Role::getRoleTypes())
                        );
                    }
                    break;
                case 'campus':
                    if (!is_array($value)) {
                        // Note 'object' in the exception below refers to a JSON object.  JSON objects will appear here as arrays.
                        throw new SchoolboxException("Filter 'campus' must be an object.");
                    }
                    $this->validateIdNameArray('campus', $value);
                    break;
                default:
                    throw new SchoolboxException("Unexpected filter key '$key'.");
            }
        }
    }

    /**
     * See also the same method in the API\Validator class.
     * TODO: It may be a good idea to combine the two classes with a common parent class.
     *
     * @param string $arrayName
     * @param array $array
     */
    private function validateIdNameArray(string $arrayName, array $array): void
    {
        if (count($array) === 0) {
            // Note 'object' in the exception below refers to JSON objects.  JSON objects will appear here as arrays.
            throw new SchoolboxException("Filter '$arrayName' must not be empty.");
        }

        foreach ($array as $key => $value) {
            switch ($key) {
                case 'id':
                    if (!is_int($value) && !ctype_digit($value)) {
                        throw new SchoolboxException("Filter '$arrayName' property 'id' must have an integer value.");
                    }
                    break;
                case 'name':
                    if (!is_string($value)) {
                        throw new SchoolboxException("Filter '$arrayName' property 'name' must have a string value.");
                    }
                    break;
                default:
                    throw new SchoolboxException("Filter '$arrayName' contains unexpected key '$key'.");
            }
        }
    }
}
