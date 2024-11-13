<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Import\Navigation;

use Aura\Router\Generator;
use Schoolbox\Sync\Navigator\Navigator;
use Schoolbox\Sync\Navigator\Node;

/**
 * Class SchoolData
 *
 * Builds navigation for School Data context
 *
 * @package Schoolbox\Admin\Import\Navigation
 */
class SchoolData implements INavigation
{
    /**
     * Builds breadcrumbs for the Direct Import page.
     *
     * @param Generator $routeGenerator
     * @return array
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function buildBreadcrumbs(Generator $routeGenerator): array
    {
        return [
            ['Administration', '/adminv2/home'],
            ['School Data Import', null],
            ['User Profiles', $routeGenerator->generate('adminImportIndex.getSchoolDataIndex')],
        ];
    }


    /**
     * Builds navigation for this page.
     *
     * @param Generator $routeGenerator
     * @param string    $currentPage
     * @return Navigator
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \ReflectionException
     */
    public function buildNavigation(Generator $routeGenerator, string $currentPage = ''): Navigator
    {
        $args = [
            new Node(
                'User Profiles',
                $routeGenerator->generate('adminImportProfile.getIndex'),
                $currentPage === 'adminImportProfile'
            ),
            new Node(
                'Timetable Structure',
                $routeGenerator->generate('adminImportTimetableStructure.getUpload'),
                $currentPage === 'adminImportTimetableStructure'
            ),
            new Node(
                'Timetable',
                $routeGenerator->generate('adminImportTimetable.getUpload'),
                $currentPage === 'adminImportTimetable'
            ),
            new Node(
                'Timetable Enrolment',
                $routeGenerator->generate('adminImportTimetableEnrolment.getUpload'),
                $currentPage === 'adminImportTimetableEnrolment'
            ),
        ];

        return new Navigator($args);
    }
}
