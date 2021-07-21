<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Timetable;

use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;

/**
 * Data generator for academic groups from Compass.
 *
 * Academic groups are Compass' closest analogue to Schoolbox campuses: whilst
 * Compass has campuses, they refer to an actual physical location rather than
 * a senior/junior/middle school split.
 */
class AcademicGroups
{
    /**
     * API for communicating with Compass.
     */
    private IApi $api;

    /**
     * Constructor
     *
     * @param IApi $api
     */
    public function __construct(IApi $api)
    {
        $this->api = $api;
    }

    /**
     * Generate and return academic groups.
     *
     * @return array
     */
    public function generate(): array
    {
        $stack          = [];
        $academicGroups = $this->api->retrieveDocument('reference/v1/GetAcademicGroups', []);
        foreach ($academicGroups['d'] as $academicGroup) {
            if (!($academicGroup['isRelevant'] ?? false)) {
                continue;
            }
            $stack[] = $academicGroup;
        }
        return $stack;
    }
}
