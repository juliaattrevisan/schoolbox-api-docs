<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Import;

use Alaress\Csv\Reader;
use Alaress\Web\Controller\Base;
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
     * User import submit screen action
     */
    public function user()
    {
        // Show a different template if using the Schoolbox connector
        $csvTemplate = '/adminv2/import/userTemplate.csv';
        if ($this->getDi()->get('config')->get('external_db') === 'schoolbox') {
            $csvTemplate = '/adminv2/import/userTemplateWithProfile.csv';
        }
        $this->getResponse()->content->set([
            'template' => '@adminImport/user.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs()->getAllNodes(),
                'allNavigation'  => $this->buildNavigation('user')->getAllNodes(),
                'csvTemplate'    => $csvTemplate,
                'attachzone'     => $this->getAttachzone()->getTwigContext(),
            ],
        ]);
    }

    /**
     * User import action
     */
    public function userImport()
    {
        $factory = new CollectorFactory();
        $upload  = $factory->build($this->getRequest()->post->get('attachment'))->getFirst();

        // Try to make a CSV parser out of the upload
        /** @var StoredisLogger $logger */
        $logger = $this->getDi()->get('ups.logger');
        $reader = new Reader($upload->readStream());
        $reader->setLoadFromFirst(true);
        $parser = new \Schoolbox\External\Importer\User($reader, $logger, false);
        $parser->save();
        $logger->flushToStorage();
        // And do something else
    }

    /**
     * Timetable structure import submit screen action
     */
    public function timetableStructure()
    {
        $this->getResponse()->content->set([
            'template' => '@adminImport/timetableStructure.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs()->getAllNodes(),
                'allNavigation'  => $this->buildNavigation('timetableStructure')->getAllNodes(),
                'attachzone'     => $this->getAttachzone()->getTwigContext(),
            ],
        ]);
    }

    /**
     * Timetable structure import action
     */
    public function timetableStructureImport()
    {
        $factory = new CollectorFactory();
        $upload  = $factory->build($this->getRequest()->post->get('attachment'))->getFirst();
        // Start parsing the upload
    }

    /**
     * Timetable import submit screen action
     */
    public function timetable()
    {
        $this->getResponse()->content->set([
            'template' => '@adminImport/timetable.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs()->getAllNodes(),
                'allNavigation'  => $this->buildNavigation('timetable')->getAllNodes(),
                'attachzone'     => $this->getAttachzone()->getTwigContext(),
            ],
        ]);
    }

    /**
     * Timetable import action
     */
    public function timetableImport()
    {
        $factory = new CollectorFactory();
        $upload  = $factory->build($this->getRequest()->post->get('attachment'))->getFirst();
        // Start parsing the upload
    }

    /**
     * Enrolment import submit screen action
     */
    public function enrolment()
    {
        $this->getResponse()->content->set([
            'template' => '@adminImport/enrolment.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs()->getAllNodes(),
                'allNavigation'  => $this->buildNavigation('enrolment')->getAllNodes(),
                'attachzone'     => $this->getAttachzone()->getTwigContext(),
            ],
        ]);
    }

    /**
     * Enrolment import action
     */
    public function enrolmentImport()
    {
        $factory = new CollectorFactory();
        $upload  = $factory->build($this->getRequest()->post->get('attachment'))->getFirst();
        // Start parsing the upload
    }

    /**
     * Gets an attachzone used to upload a file for import
     *
     * @return Zone
     */
    protected function getAttachzone()
    {
        $zone = new Zone('import');
        return $zone
            ->setModalTitle('Select CSV')
            ->setAttachmentsLabel('Upload CSV')
            ->setAttachPrompt('upload CSV')
//            ->setMimeFilter('text/csv')
            ->setReadOnly(false)
            ->setMulti(false);
    }
}
