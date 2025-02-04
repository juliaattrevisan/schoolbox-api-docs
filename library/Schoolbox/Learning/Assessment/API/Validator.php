<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\API;

use Alaress\Web\Request\Values;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Learning\Assessment\AssessmentType;

/**
 * Password validator
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
                case 'assessmentId':
                    if (!is_numeric($value)) {
                        throw new SchoolboxException("Filter 'assessmentId' must have a numeric value.");
                    }
                    break;
                case 'title':
                    if (!is_string($value)) {
                        throw new SchoolboxException("Filter 'title' must have a string value.");
                    }
                    break;
                case 'assessmentType':
                    if (
                        !in_array(
                            $value,
                            [
                                AssessmentType::TYPE_TASK,
                                AssessmentType::TYPE_QUIZ,
                                AssessmentType::TYPE_LTI_ASSESSMENT,
                                AssessmentType::TYPE_DUE_WORK,
                                AssessmentType::TYPE_PROJECT,
                                AssessmentType::TYPE_LESSON_PLAN
                            ]
                        )
                    ) {
                        throw new SchoolboxException("Filter 'assessmentType' has unexpected enum value.");
                    }
                    break;
                case 'folder':
                    if (!is_array($value)) {
                        // Note 'object' in the exception below refers to JSON objects.  JSON objects will appear here as arrays.
                        throw new SchoolboxException("Filter 'folder' must be a object or an array of objects.");
                    }
                    $this->validateFilterFolderValue($value);
                    break;
                case 'subjectCode':
                    if (!is_array($value)) {
                        throw new SchoolboxException("Filter 'subjectCode' must be an array of strings.");
                    }
                    foreach ($value as $code) { // Ignore the keys in this array.
                        if (!is_string($code)) {
                            throw new SchoolboxException("Filter 'subjectCode' contains an item that is not a string.");
                        }
                    }
                    break;
                case 'weighted':
                    if (!is_bool((new Values(['key' => $value]))->getBool('key'))) {
                        throw new SchoolboxException("Filter 'weighted' must have a boolean value.");
                    }
                    break;
                case 'workType':
                    if (!is_array($value)) {
                        // Note 'object' in the exception below refers to JSON objects.  JSON objects will appear here as arrays.
                        throw new SchoolboxException("Filter 'workType' must be an object.");
                    }
                    $this->validateIdNameArray('workType', $value);
                    break;
                case 'dueDate':
                    if (!is_array($value)) {
                        // Note 'object' in the exception below refers to JSON objects.  JSON objects will appear here as arrays.
                        throw new SchoolboxException(
                            'Filter \'dueDate\' must be an object like {"from": date-time, "to": date-time}.' .
                            '  Note either property may be omitted, but not both.'
                        );
                    }
                    $this->validateFilterDueDateValue($value);
                    break;
                case 'yearLevel':
                    if (!is_array($value)) {
                        // Note 'object' in the exception below refers to JSON objects.  JSON objects will appear here as arrays.
                        throw new SchoolboxException("Filter 'yearLevel' must be an object.");
                    }
                    $this->validateIdNameArray('yearLevel', $value);
                    break;
                case 'project':
                    if (
                        !is_array($value) ||
                        count($value) !== 1 ||
                        (array_key_exists('id', $value) && is_numeric($value['id']))
                    ) {
                        // Note 'object' in the exception below refers to JSON objects.  JSON objects will appear here as arrays.
                        throw new SchoolboxException('Filter \'project\' must be an object like {"id": integer}.');
                    }
                    break;
                default:
                    throw new SchoolboxException("Unexpected filter key '$key'.");
            }
        }
    }

    /**
     * @param array $value
     */
    private function validateFilterFolderValue(array $value): void
    {
        if (array_key_exists('id', $value)) {
            // Expect $value to contain an array like ['id' => $integer].
            if (count($value) > 1) {
                throw new SchoolboxException(
                    "Filter 'folder' contains a key 'id', plus other items." .
                    "  If key 'id' is present there should be no other items."
                );
            }
        } else {
            // Expect $value to contain an array like the following.
            // Note each sub-array may contain 'id', 'code' or both.
            // [
            //    ['id' => $integer],
            //    ['code' => $integer],
            //    ['id' => $integer],
            //    ['id' => $integer, 'code' => $string],
            //    ...
            // ]
            foreach ($value as $subArray) { // Ignore the keys in this array.
                if (!is_array($subArray)) {
                    // Note 'object' in the exception below refers to JSON objects.  JSON objects will appear here as arrays.
                    throw new SchoolboxException("Filter 'folder' contains an item that is not an object.");
                }

                if (count($subArray) === 0) {
                    throw new SchoolboxException("Filter 'folder' item must not be empty.");
                }

                foreach ($subArray as $key => $value) {
                    switch ($key) {
                        case 'id':
                            if (!is_numeric($value)) {
                                throw new SchoolboxException("Filter 'folder' item property 'id' must have a numeric value.");
                            }
                            break;
                        case 'code':
                            if (!is_string($value)) {
                                throw new SchoolboxException("Filter 'folder' item property 'name' must have a string value.");
                            }
                            break;
                        default:
                            throw new SchoolboxException("Filter 'folder' item contains unexpected key '$key'.");
                    }
                }
            }
        }
    }

    /**
     * @param array $dueDateStruct
     */
    private function validateFilterDueDateValue(array $dueDateStruct): void
    {
        if (count($dueDateStruct) === 0) {
            // Note 'object' in the exception below refers to JSON objects.  JSON objects will appear here as arrays.
            throw new SchoolboxException(
                'Filter \'dueDate\' must be an object like {"from": date-time, "to": date-time}.' .
                '  Note either attribute may be omitted, but not both.'
            );
        }

        foreach ($dueDateStruct as $key => $value) {
            if (!in_array($key, ['to', 'from'])) {
                throw new SchoolboxException("Filter 'dueDate' contains a key other than 'to' or 'from'.");
            }

            if (!is_string($value)) {
                throw new SchoolboxException("Filter 'dueDate' ($key) must be a string.");
            }
            try {
                new \Alaress_Date_Format($value, [DATE_RFC3339, DATE_RFC3339_EXTENDED]);
            } catch (\Exception $e) {
                throw new SchoolboxException("Failed to parse date from filter 'dateDate' ($key).");
            }
        }
    }

    /**
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
                    if (!is_numeric($value)) {
                        throw new SchoolboxException("Filter '$arrayName' property 'id' must have a numeric value.");
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
