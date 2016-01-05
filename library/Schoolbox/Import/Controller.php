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
            new Node('Importer', $this->getRouter()->generate('adminImport.intro'), true)
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
                $this->getRouter()->generate('adminImport.user'),
                ($currentPage == 'user')
            ),
            new Node(
                'Timetable Structure',
                $this->getRouter()->generate('adminImport.timetableStructure'),
                ($currentPage == 'timetableStructure')
            ),
            new Node(
                'Timetable',
                $this->getRouter()->generate('adminImport.timetable'),
                ($currentPage == 'timetable')
            ),
            new Node(
                'Enrolment',
                $this->getRouter()->generate('adminImport.enrolment'),
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
                'logHash'        => $this->getSession()->getSegment(static::class)->getFlash('logHash'),
            ],
        ]);
    }

    /**
     * User preview action
     */
    public function userPreview()
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

        // Try to use the UPS templates here
        // Each line is json-decodable
        $stream   = $logger->flushToStorage()->readStream();
        $logLines = [];
        while ($line = fgets($stream)) {
            if ($logLine = json_decode($line, true)) {
                $logLines[] = $logLine;
            }
        }

        $this->getResponse()->content->set([
            'template' => '@adminImport/userPreview.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs()->getAllNodes(),
                'allNavigation'  => $this->buildNavigation('user')->getAllNodes(),
                'log'            => ['lines' => $logLines],
                'attachzone'     => $this->getAttachzone($upload->getHash())->getTwigContext(),
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
        $parser = new \Schoolbox\External\Importer\User($reader, $logger, true);
        $parser->save();

        // Try to use the UPS templates here
        // Each line is json-decodable
        $stream   = $logger->flushToStorage()->readStream();
        $logLines = [];
        while ($line = fgets($stream)) {
            if ($logLine = json_decode($line, true)) {
                $logLines[] = $logLine;
            }
        }

        $this->getResponse()->content->set([
            'template' => '@adminImport/userImport.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs()->getAllNodes(),
                'allNavigation'  => $this->buildNavigation('user')->getAllNodes(),
                'log'            => ['lines' => $logLines],
            ],
        ]);
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
     * Generic log action
     *
     * @param Storage\Model $log A log in storage
     */
    public function log(Storage\Model $log)
    {
        // Try to use the UPS templates here
        // Each line is json-decodable
        $stream   = $log->readStream();
        $logLines = [];
        while ($line = fgets($stream)) {
            if ($logLine = json_decode($line, true)) {
                $logLines[] = $logLine;
            }
        }

        $this->getResponse()->content->set([
            'template' => '@adminImport/log.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs()->getAllNodes(),
                'log'            => ['lines' => $logLines],
            ],
        ]);
    }

    /**
     * Gets an attachzone used to upload a file for import
     *
     * @param  string $hash if set, the hash of the file to attach
     * @return Zone
     */
    protected function getAttachzone($hash = null)
    {
        $zone = new Zone('import');
        $zone
            ->setModalTitle('Select CSV')
            ->setAttachmentsLabel('Upload CSV')
            ->setAttachPrompt('upload CSV')
//            ->setMimeFilter('text/csv')
            ->setReadOnly(false)
            ->setMulti(false);
        if (isset($hash)) {
            $zone->attachFiles([$hash]);
        }
        return $zone;
    }
}
