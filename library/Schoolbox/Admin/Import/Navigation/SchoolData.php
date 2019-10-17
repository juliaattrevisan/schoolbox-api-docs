<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Navigation;

use Aura\Router\Router;
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
     * Direct constructor.
     */
    public function __construct() {
    }


    /**
     * Builds breadcrumbs for the Direct Import page.
     *
     * @param Router $router
     * @return array
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function buildBreadcrumbs(Router $router): array
    {
        return [
            ['Administration', '/adminv2/home'],
            ['School Data Import', $router->generate('adminImportIndex.getSchoolDataIndex')],
        ];
    }


    /**
     * Builds navigation for this page.
     *
     * @param Router $router
     * @param string $currentPage
     * @return Navigator
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \ReflectionException
     */
    public function buildNavigation(Router $router, string $currentPage = ''): Navigator
    {
        $args = [
            new Node(
                'User Profiles',
                $router->generate('adminImportProfile.getIndex'),
                $currentPage === 'adminImportProfile'
            ),
            new Node(
                'Timetable Structure',
                $router->generate('adminImportTimetableStructure.getUpload'),
                $currentPage === 'adminImportTimetableStructure'
            ),
            new Node(
                'Timetable',
                $router->generate('adminImportTimetable.getUpload'),
                $currentPage === 'adminImportTimetable'
            ),
            new Node(
                'Timetable Enrolment',
                $router->generate('adminImportTimetableEnrolment.getUpload'),
                $currentPage === 'adminImportTimetableEnrolment'
            ),
        ];

        return new Navigator($args);
    }
}
