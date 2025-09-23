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
                    if (!is_int($value) && !ctype_digit($value)) {
                        throw new SchoolboxException("Filter 'externalId' must have an integer value.");
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
                    break;
                case 'role':
                    if (!is_array($value)) {
                        // Note 'object' in the exception below refers to JSON objects.  JSON objects will appear here as arrays.
                        throw new SchoolboxException("Filter 'role' must be an array of objects.");
                    }
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
                    $this->validateFilterFolderValue($value);
                    break;
                default:
                    throw new SchoolboxException("Unexpected filter key '$key'.");
            }
        }
    }
}
