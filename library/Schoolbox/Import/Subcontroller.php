<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Import;

use Alaress\Csv\Reader;
use Alaress\Web\Controller\Base;
use Schoolbox\External\Importer;
use Schoolbox\Mapper;
use Schoolbox\Notif\StoredisLogger;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Storage;
use Schoolbox\Sync\Log;
use Psr\Log\LoggerInterface;

/**
 * Controller for admin importer.
 *
 * @package Schoolbox\Import
 */
class Subcontroller extends Base
{
    use ImportNavigation;

    /**
     * @var string
     */
    protected $basename;

    /**
     * Constructor
     *
     * @param string $basename
     */
    public function __construct($basename)
    {
        $this->basename = $basename;
    }

    /**
     * User import submit screen action
     */
    public function upload()
    {
        $this->getResponse()->content->set([
            'template' => '@'.$this->basename.'/upload.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs($this->getRouter())->getAllNodes(),
                'allNavigation'  => $this->buildNavigation($this->getRouter(), $this->basename)->getAllNodes(),
                'attachzone'     => $this->getAttachzone()->getTwigContext(),
                'basename'       => $this->basename,
            ],
        ]);
    }

    /**
     * User preview action
     */
    public function preview()
    {
        $factory = new CollectorFactory();
        $upload  = $factory->build($this->getRequest()->post->get('attachment'))->getFirst();

        // Try to make a CSV parser out of the upload
        /** @var StoredisLogger $logger */
        $logger = $this->getDi()->get('ups.logger');
        $reader = new Reader($upload->readStream());
        $reader->setLoadFromFirst(true);
        $importer = $this->buildImporter($reader, $logger, false);
        $importer->save();

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
            'template' => '@'.$this->basename.'/preview.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs($this->getRouter())->getAllNodes(),
                'allNavigation'  => $this->buildNavigation($this->getRouter(), $this->basename)->getAllNodes(),
                'log'            => ['lines' => $logLines],
                'attachzone'     => $this->getAttachzone($upload->getHash())->getTwigContext(),
                'basename'       => $this->basename,
            ],
        ]);
    }

    /**
     * User import action
     */
    public function import()
    {
        $factory = new CollectorFactory();
        $upload  = $factory->build($this->getRequest()->post->get('attachment'))->getFirst();

        // Try to make a CSV parser out of the upload
        /** @var StoredisLogger $logger */
        $logger = $this->getDi()->get('ups.logger');
        $reader = new Reader($upload->readStream());
        $reader->setLoadFromFirst(true);
        $importer = $this->buildImporter($reader, $logger, true);
        $importer->save();

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
            'template' => '@'.$this->basename.'/import.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs($this->getRouter())->getAllNodes(),
                'allNavigation'  => $this->buildNavigation($this->getRouter(), $this->basename)->getAllNodes(),
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

    /**
     * Builds an importer object based on this subcontroller's basename.
     *
     * @param Reader          $reader
     * @param LoggerInterface $log
     * @param bool            $performUpdate
     *
     * @return Importer\AbstractImporter
     */
    protected function buildImporter(Reader $reader, LoggerInterface $log, $performUpdate = false)
    {
        switch ($this->basename) {
            case 'adminImportUser':
                return new Importer\User($reader, $log, $performUpdate);
            case 'adminImportStructure':
                return new Importer\TimetableStructure($reader, $log, $performUpdate);
            case 'adminImportTimetable':
                return new Importer\Timetable($reader, $log, $performUpdate);
            case 'adminImportEnrolment':
                return new Importer\Enrolment($reader, $log, $performUpdate);
            default:
                throw new \OutOfRangeException(sprintf('No importer matches basename "%s"', $this->basename));
        }
    }
}
