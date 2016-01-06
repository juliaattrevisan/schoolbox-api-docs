<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Import;

use Alaress\Csv\Reader;
use Alaress\Web\Controller\Base;
use Schoolbox\External\Importer\AbstractImporter;
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
class Subcontroller extends Base
{
    use ImportNavigation;

    /**
     * @var string
     */
    protected $basename;

//    /**
//     * @var AbstractImporter
//     */
//    protected $importer;

    /**
     * Constructor
     *
     * @param string $basename
     */
    public function __construct($basename)
    {
        $this->basename = $basename;
//        $this->importer = $importer;
    }

    /**
     * User import submit screen action
     */
    public function upload()
    {
        // Show a different template if using the Schoolbox connector
        $csvTemplate = '/adminv2/import/userTemplate.csv';
        if ($this->getDi()->get('config')->get('external_db') === 'schoolbox') {
            $csvTemplate = '/adminv2/import/userTemplateWithProfile.csv';
        }
        $this->getResponse()->content->set([
            'template' => '@adminImport/upload.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs($this->getRouter())->getAllNodes(),
                'allNavigation'  => $this->buildNavigation($this->getRouter(), $this->basename)->getAllNodes(),
                'csvTemplate'    => $csvTemplate,
                'attachzone'     => $this->getAttachzone()->getTwigContext(),
                'basename'       => $this->basename,
//                'logHash'        => $this->getSession()->getSegment(static::class)->getFlash('logHash'),
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
            'template' => '@adminImport/preview.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs($this->getRouter())->getAllNodes(),
                'allNavigation'  => $this->buildNavigation($this->getRouter(), $this->basename)->getAllNodes(),
                'log'            => ['lines' => $logLines],
                'attachzone'     => $this->getAttachzone($upload->getHash())->getTwigContext(),
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
            'template' => '@adminImport/import.twig',
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
}
