<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec;

/**
 * Class EmployeeExternalId
 *
 * SUDS TASS Employees get a letter prefixed onto their externalId because they were
 * conflicting with the student's externalId namespace, by virtue of poor design
 *
 * The prefix allows us to segregate that namespace, but it won't be recognised by other
 * parts of the TASS suite so we need to decode it before we can use it properly
 */
class EmployeeExternalId
{

    /**
     * An arbitrary string used to prefix Employees' user_code. This field is usually numeric
     * and can directly conflict with student external ids, so you end up with employees' profiles
     * overwriting student profiles. To prevent them matching up like that, we chuck a letter in
     */
    public const EMPLOYEE_PREFIX = 'E';


    /**
     * Convert from its native format
     *
     * @param string $decodedExternalId
     * @return string
     */
    public function encode(string $decodedExternalId): string
    {
        return self::EMPLOYEE_PREFIX . $decodedExternalId;
    }


    /**
     * Convert to its native format
     *
     * @param string $encodedExternalId
     * @return string
     */
    public function decode(string $encodedExternalId): string
    {
        return preg_replace('/^E([a-zA-Z0-9]+)/', '$1', $encodedExternalId);
    }
}
