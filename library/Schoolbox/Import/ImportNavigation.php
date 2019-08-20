<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Import;

use Aura\Router\Router;
use Schoolbox\L10n\Mapper as L10nMapper;
use Schoolbox\L10n\RosettaStoneFactory;
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
     * Builds navigation for this page.
     *
     * @param Router $router
     * @param string $currentPage
     * @return Navigator
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \ReflectionException
     */
    protected function buildNavigation(Router $router, string $currentPage = ''): Navigator
    {
        $args = [
            new Node(
                'User',
                $router->generate('adminImportUser.upload'),
                $currentPage === 'adminImportUser'
            ),
            new Node(
                'User Photos',
                $router->generate('adminImportPhotos.getIndex'),
                $currentPage === 'adminImportPhotos'
            ),
            new Node(
                'User Profiles',
                $router->generate('adminImportProfile.getIndex'),
                $currentPage === 'adminImportProfile'
            ),
            new Node(
                'Timetable Structure',
                $router->generate('adminImportStructure.upload'),
                $currentPage === 'adminImportStructure'
            ),
            new Node(
                'Timetable',
                $router->generate('adminImportTimetable.upload'),
                $currentPage === 'adminImportTimetable'
            ),
            new Node(
                'Enrolment',
                $router->generate('adminImportEnrolment.upload'),
                $currentPage === 'adminImportEnrolment'
            ),
        ];

        /* Optional Pastoral Module
         */
        if ($this->getConfigLoader()->get('pastoral_enabled')) {
            $rosettaStone = (new RosettaStoneFactory())->build(L10nMapper::getInstance());

            $args[] = new Node(
                $rosettaStone->translate('pastoralCare', false, true) . ' Records',
                $router->generate('adminImportPastoral.getIndex'),
                $currentPage === 'adminImportPastoral'
            );
        }

        return new Navigator($args);
    }
}
