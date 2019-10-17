<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Import;

use Aura\Router\Router;
use Schoolbox\Sync\Navigator\Navigator;
use Schoolbox\Sync\Navigator\Node;

/**
 * Controller for admin importer.
 *
 * @package Schoolbox\Import
 */
trait ImportNavigation
{

    /**
     * Builds breadcrumbs for the School Data Import page.
     *
     * @param Router $router
     * @return array
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    protected function buildSchoolDataBreadcrumbs(Router $router): array
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
    protected function buildSchoolDataNavigation(Router $router, string $currentPage = ''): Navigator
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
