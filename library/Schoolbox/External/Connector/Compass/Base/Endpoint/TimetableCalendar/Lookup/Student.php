<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar\Lookup;

use Schoolbox\External\Connector\Compass\Base\Endpoint\Helper;
use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;

/**
 * Some Compass Timetable events don't have enrolments, such as yard duties and other
 * non-academic classes; these have attendees instead, and they're referenced by
 * compassPersonId not the external id.
 *
 * That means you gotta look up dudes separately, which is annoying
 */
class Student
{

    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @var Helper
     */
    private Helper $helper;


    /**
     * Constructor.
     *
     * @param IApi  $api
     */
    public function __construct(IApi $api)
    {
        $this->api = $api;

        /* Helper class
         */
        $this->helper = new Helper($this->api);
    }


    /**
     * Generate a list of guys indexed by their CompassPersonId
     */
    public function generate(string $lBound, string $uBound): array
    {
        $stack = [];

        $students = $this->api->retrieveDocument('person/v1/GetStudents', [
            'lBound' => $lBound,
            'uBound' => $uBound,
        ]);

        foreach ($students['d'] as $student) {
            $stack[$student['compassPersonId']] = [
                'externalId' => $this->helper->findExternalId($student['importIdentifiers']),
                'firstname' => $student['firstName'],
                'lastname' => $student['lastName'],
            ];
        }

        return $stack;
    }
}
