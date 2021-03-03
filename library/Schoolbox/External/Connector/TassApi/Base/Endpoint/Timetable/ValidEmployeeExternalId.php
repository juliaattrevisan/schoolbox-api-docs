<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable;

/**
 * Class ValidEmployeeExternalId
 *
 * Decide if $externalId followed an Employee prefix scheme which distinguished
 * them from students and teaching staff.
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable
 */
class ValidEmployeeExternalId
{
    /**
     * An arbitrary string used to prefix Employees' user_code. This field is usually numeric
     * and can directly conflict with student external ids, so you end up with employees' profiles
     * overwriting student profiles. To prevent them matching up like that, we chuck a letter in
     */
    private const EMPLOYEE_PREFIX = 'E';


    /**
     * If the first character of $externalId is the employee prefix,
     * then they're the employee
     *
     * @param string $externalId
     * @return bool
     */
    public function isSatisfiedBy(string $externalId): bool
    {
        if ('' === $externalId) {
            return false;
        }

        return self::EMPLOYEE_PREFIX === $externalId[0];
    }
}