<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Import;

use Alaress\Csv\Reader;
use Alaress\Web\Controller\Base;
use Schoolbox\External\Importer;
use Schoolbox\Notif\CacheableLoggerInterface;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Storage;
use Psr\Log\LoggerInterface;

/**
 * Controller for admin importer.
 *
 * @package Schoolbox\Import
 */
class ImportController extends Base
{
    use ImportNavigation;

    /**
     * @var string
     */
    protected $basename;
    /** @var ImporterFactory */
    private $importerFactory;


    /**
     * Constructor
     *
     * @param string          $basename
     * @param ImporterFactory $importerFactory
     */
    public function __construct($basename, ImporterFactory $importerFactory)
    {
        $this->basename = $basename;
        $this->importerFactory = $importerFactory;
    }

    /**
     * Gets base template data. Actions should extend these template vars.
     *
     * @return array
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function getBaseData()
    {
        return [
            'allNavigation'  => $this->buildNavigation($this->getRouter(), $this->basename)->getAllNodes(),
            'basename'       => $this->basename,
            'hasExternalDb'  => ($this->getDi()->get('config')->get('external_type') != 'schoolbox'),
            'now'            => \Alaress_Date_Format::now(),
        ];
    }

    /**
     * User import submit screen action
     */
    public function upload()
    {
        $this->getResponse()->content->set([
            'template' => '@' . $this->basename . '/upload.twig',
            'data'     => $this->getBaseData() + [
                'pageCurrent' => 'import',
                'attachzone'    => $this->getAttachzone()->getTwigContext(),
                'uploadMissing' => $this->getSession()->getSegment(static::class)->getFlash('uploadMissing', false)
            ],
        ]);
    }

    /**
     * User preview action
     */
    public function preview()
    {
        set_time_limit(1800);

        $logger = $this->buildLogger();
        try {
            $reader = $this->buildReader($upload = $this->buildUpload());
        } catch (\Schoolbox\Resource\Exception $e) {
            $this->getSession()->getSegment(static::class)->setFlash('uploadMissing', true);
            $this->getResponse()->redirect->to($this->getRouter()->generate($this->basename.'.upload'));
            return;
        }
        $this->buildImporter($reader, $logger, false)->save();
        $this->getResponse()->content->set([
            'template' => '@' . $this->basename . '/preview.twig',
            'data'     => $this->getBaseData() + [
                'pageCurrent' => 'import',
                'log'        => ['lines' => $this->parseLogLines($logger),],
                'attachzone' => $this->getAttachzone($upload->getHash())->getTwigContext(),
            ],
        ]);
    }

    /**
     * User import action
     */
    public function import()
    {
        set_time_limit(1800);

        $logger = $this->buildLogger();
        try {
            $reader = $this->buildReader($upload = $this->buildUpload());
        } catch (\Schoolbox\Resource\Exception $e) {
            $this->getSession()->getSegment(static::class)->setFlash('uploadMissing', true);
            $this->getResponse()->redirect->to($this->getRouter()->generate($this->basename.'.upload'));
            return;
        }
        $this->buildImporter($reader, $logger, true)->save();
        $this->getResponse()->content->set([
            'template' => '@' . $this->basename . '/import.twig',
            'data'     => $this->getBaseData() + [
                'pageCurrent' => 'import',
                'log' => ['lines' => $this->parseLogLines($logger),],
            ],
        ]);
    }

    /**
     * Gets an attachzone used to upload a file for import
     *
     * @param  string $hash if set, the hash of the file to attach
     * @param  bool   $readonly
     * @return Zone
     */
    protected function getAttachzone($hash = null, $readonly = false)
    {
        $zone = new Zone('import');
        $zone
            ->setModalTitle('Select CSV')
            ->setAttachmentsLabel('Upload CSV')
            ->setAttachPrompt('upload CSV')
//            ->setMimeFilter('text/csv')
            ->setReadOnly($readonly)
            ->setMulti(false);
        if (isset($hash)) {
            $zone->attachFiles([$hash]);
        }
        return $zone;
    }

    /**
     * Builds a logger for use in the importer.
     *
     * @return CacheableLoggerInterface
     * @throws \Aura\Di\Exception\ServiceNotFound if the logger wasn't found?
     */
    protected function buildLogger()
    {
        return $this->getDi()->get('ups.logger');
    }

    /**
     * Builds a storage object out of what was uploaded.
     *
     * @return Storage\IStorage
     * @throws \Schoolbox\Resource\Exception if nothing was uploaded
     */
    protected function buildUpload()
    {
        $factory = new CollectorFactory();
        $attach  = $this->getRequest()->post->get('attachment');
        if (is_array($attach)) {
            return $factory->build($attach)->getFirst();
        } else {
            throw new \Schoolbox\Resource\Exception('No file was uploaded');
        }
    }

    /**
     * Builds a CSV reader out of the uploaded file.
     *
     * @param  Storage\IStorage $upload
     * @return Reader
     * @throws \Schoolbox\Resource\Exception if nothing was uploaded
     */
    protected function buildReader(Storage\IStorage $upload)
    {
        return $this->importerFactory->buildReader($this->basename, $upload);
    }

    /**
     * Parses lines out of the logger.
     *
     * @param  CacheableLoggerInterface $logger
     * @return array
     */
    protected function parseLogLines($logger)
    {
        $stream   = $logger->flushToStorage()->readStream();
        $logLines = [];
        while ($line = fgets($stream)) {
            if ($logLine = json_decode($line, true)) {
                $logLines[] = $logLine;
            }
        }
        return $logLines;
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
        return $this->importerFactory->buildImporter($this->basename, $reader, $log, $performUpdate);
    }
}
