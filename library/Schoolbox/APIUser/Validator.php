<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\APIUser;

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Role;

/**
 * Validator of user-related API requests.
 *
 * @package Schoolbox\User\Password
 */
class Validator
{
    public function __construct()
    {
    }

    /**
     * @param array $filter
     */
    public function validateFilter(array $filter): void
    {
        foreach ($filter as $key => $value) {
            switch ($key) {
                case 'externalId':
                    if (!is_string($value)) {
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
                        // Note 'object' in the exception below refers to JSON objects.  JSON objects will appear here as arrays.
                        throw new SchoolboxException("Filter 'year' must be an array of objects.");
                    }
                    $this->validateSingleOrMultipleIdOrNameArrays('year', $value);
                    break;
                case 'role':
                    if (!is_array($value)) {
                        // Note 'object' in the exception below refers to JSON objects.  JSON objects will appear here as arrays.
                        throw new SchoolboxException("Filter 'role' must be an array of objects.");
                    }
                    $this->validateSingleOrMultipleIdOrNameArrays('role', $value);
                    break;
                case 'roleType':
                    if (!in_array($value, Role::getRoleTypes(), true)) {
                        throw new SchoolboxException("Filter 'roleType' has unknown value.");
                    }
                    break;
                case 'campus':
                    if (!is_array($value)) {
                        // Note 'object' in the exception below refers to JSON objects.  JSON objects will appear here as arrays.
                        throw new SchoolboxException("Filter 'campus' must be an array of objects.");
                    }
                    $this->validateSingleOrMultipleIdOrNameArrays('campus', $value);
                    break;
                default:
                    throw new SchoolboxException("Unexpected filter key '$key'.");
            }
        }
    }

    /**
     * @param string $arrayName
     * @param array $array
     */
    private function validateSingleOrMultipleIdOrNameArrays(string $arrayName, array $array): void
    {
        if (count($array) > 0 && !is_array(array_values($array)[0])) {
            // The array doesn't contain an array, so validate as single id/name array.
            // Expect $array to be either ['id' => ...] or ['name' => ...].
            $this->validateIdOrNameArray($arrayName, $array);
        } else {
            // The array contains an array, so validate as multiple id/name arrays.
            // Expect $array to contain items that are either ['id' => ...] or ['name' => ...].
            foreach ($array as $subArray) {
                $this->validateIdOrNameArray("$arrayName item", $subArray);
            }
        }
    }

    /**
     * @param string $arrayName
     * @param array  $array
     * @return void
     */
    private function validateIdOrNameArray(string $arrayName, array $array): void
    {
        if (count($array) === 0) {
            // Note 'object' in the exception below refers to JSON objects.  JSON objects will appear here as arrays.
            throw new SchoolboxException("Filter '$arrayName' contains an item that is missing an expected key.");
        }

        foreach ($array as $key => $subValue) {
            switch ($key) {
                case 'id':
                    if (!is_int($subValue) && !ctype_digit($subValue)) {
                        throw new SchoolboxException("Filter '$arrayName' item property 'id' must have an integer value.");
                    }
                    break;
                case 'name':
                    if (!is_string($subValue)) {
                        throw new SchoolboxException("Filter '$arrayName' item property 'name' must have a string value.");
                    }
                    break;
                default:
                    throw new SchoolboxException("Each filter '$arrayName' item must have key 'id' or 'name'.");
            }
        }
    }
}
