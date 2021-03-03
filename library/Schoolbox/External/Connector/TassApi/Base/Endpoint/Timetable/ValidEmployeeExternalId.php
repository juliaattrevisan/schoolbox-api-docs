<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Timetable;

use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\EmployeeExternalId;


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

        return EmployeeExternalId::EMPLOYEE_PREFIX === $externalId[0];
    }
}