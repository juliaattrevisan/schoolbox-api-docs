<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Import;

use Alaress\Csv\Reader;
use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Schoolbox\Mapper;
use Schoolbox\Notif\StoredisLogger;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Storage;
use Schoolbox\Sync\Log;
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
     * Builds breadcrumbs for this page.
     *
     * @param  Router $router
     * @return Navigator
     */
    protected function buildBreadcrumbs(Router $router)
    {
        return new Navigator(
            new Node('Home', '/adminv2/home', false),
            new Node('Importer', $router->generate('adminImport.intro'), true)
        );
    }

    /**
     * Builds navigation for this page.
     *
     * @param  Router $router
     * @param  string $currentPage
     * @return Navigator
     */
    protected function buildNavigation(Router $router, $currentPage = '')
    {
        return new Navigator(
            new Node(
                'User',
                $router->generate('adminImportUser.upload'),
                ($currentPage == 'adminImportUser')
            ),
            new Node(
                'Timetable Structure',
                $router->generate('adminImportStructure.upload'),
                ($currentPage == 'adminImportStructure')
            ),
            new Node(
                'Timetable',
                $router->generate('adminImportTimetable.upload'),
                ($currentPage == 'adminImportTimetable')
            ),
            new Node(
                'Enrolment',
                $router->generate('adminImportEnrolment.upload'),
                ($currentPage == 'adminImportEnrolment')
            ),
            new Node(
                'User Photos',
                $router->generate('adminPhotos.getIndex'),
                ($currentPage == 'adminUserPhotos')
            ),
            new Node(
                'Pastoral Care Records',
                $router->generate('adminImportPastoral.getIndex'),
                ($currentPage == 'adminPastoralCare')
            )
        );
    }
}
