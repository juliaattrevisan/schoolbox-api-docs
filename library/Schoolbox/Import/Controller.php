<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Import;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper;
use Schoolbox\Resource\Storage;
use Schoolbox\Sync\Log;
use Schoolbox\Sync\Navigator\Navigator;
use Schoolbox\Sync\Navigator\Node;

/**
 * Controller for admin importer.
 *
 * @package Schoolbox\Import
 */
class Controller extends Base
{
    /**
     * Builds breadcrumbs for this page.
     *
     * @return Navigator
     */
    protected function buildBreadcrumbs()
    {
        return new Navigator(
            new Node('Home', '/adminv2/home', false),
            new Node('Importer', $this->getRouteIfAuthorized('adminImport.intro'), true)
        );
    }

    /**
     * Builds navigation for this page.
     *
     * @param  string $currentPage
     * @return Navigator
     */
    protected function buildNavigation($currentPage = '')
    {
        return new Navigator(
            new Node(
                'User',
                $this->getRouteIfAuthorized('adminImport.user'),
                ($currentPage == 'user')
            ),
            new Node(
                'Timetable Structure',
                $this->getRouteIfAuthorized('adminImport.timetableStructure'),
                ($currentPage == 'timetableStructure')
            ),
            new Node(
                'Timetable',
                $this->getRouteIfAuthorized('adminImport.timetable'),
                ($currentPage == 'timetable')
            ),
            new Node(
                'Enrolment',
                $this->getRouteIfAuthorized('adminImport.enrolment'),
                ($currentPage == 'enrolment')
            )
        );
    }

    /**
     * Intro action
     */
    public function intro()
    {
        $this->getResponse()->content->set([
            'template' => '@adminImport/intro.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs()->getAllNodes(),
                'allNavigation'  => $this->buildNavigation()->getAllNodes(),
            ],
        ]);
    }

    /**
     * User import action
     */
    public function user()
    {
        $this->getResponse()->content->set([
            'template' => '@adminImport/user.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs()->getAllNodes(),
                'allNavigation'  => $this->buildNavigation('user')->getAllNodes(),
            ],
        ]);
    }

    /**
     * Download user import template action
     */
    public function userTemplate()
    {
        $this->getResponse()->content->set([
            'type' => 'file',
            'file' => __DIR__ . '/templates/userTemplate.csv'
        ]);
    }

    /**
     * Timetable structure import action
     */
    public function timetableStructure()
    {
        $this->getResponse()->content->set([
            'template' => '@adminImport/timetableStructure.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs()->getAllNodes(),
                'allNavigation'  => $this->buildNavigation('timetableStructure')->getAllNodes(),
            ],
        ]);
    }

    /**
     * Timetable import action
     */
    public function timetable()
    {
        $this->getResponse()->content->set([
            'template' => '@adminImport/timetable.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs()->getAllNodes(),
                'allNavigation'  => $this->buildNavigation('timetable')->getAllNodes(),
            ],
        ]);
    }

    /**
     * Enrolment import action
     */
    public function enrolment()
    {
        $this->getResponse()->content->set([
            'template' => '@adminImport/enrolment.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs()->getAllNodes(),
                'allNavigation'  => $this->buildNavigation('enrolment')->getAllNodes(),
            ],
        ]);
    }
}
