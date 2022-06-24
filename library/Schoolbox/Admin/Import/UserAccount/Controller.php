<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Import\UserAccount;

use Alaress\Csv\Reader;
use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Psr\Log\LoggerInterface;
use Schoolbox\Admin\Import\Navigation\Direct;
use Schoolbox\Admin\Import\Navigation\INavigation;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\External\Importer\AbstractImporter;
use Schoolbox\IConfigLoader;
use Schoolbox\Import\ImporterFactory;
use Schoolbox\Notif\CacheableLoggerInterface;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Exception;
use Schoolbox\Resource\Storage;

/**
 * Class Controller
 *
 * @package Schoolbox\Admin\Import\UserAccount
 */
class Controller extends Base
{
    /**
     * @var string
     */
    protected $basename = 'adminImportUserAccount';


    /**
     * @var ImporterFactory
     */
    protected $importerFactory;


    /**
     * @var INavigation
     */
    protected $navigation;


    /**
     * Controller constructor.
     *
     * @param ImporterFactory         $importerFactory
     * @param IConfigLoader           $configLoader
     * @param Storage\FilesystemProxy $filesystemProxy
     */
    public function __construct(
        ImporterFactory $importerFactory,
        IConfigLoader $configLoader,
        private Storage\FilesystemProxy $filesystemProxy,
    ) {
        $this->importerFactory = $importerFactory;
        $this->navigation = new Direct($configLoader);
    }


    /**
     * Upload data
     *
     * @throws RouteNotFound
     * @throws \ReflectionException
     * @throws \UnexpectedValueException
     */
    public function getUpload()
    {
        $this->getResponse()->content->set([
            'template' => "@{$this->basename}/upload.twig",
            'data' => $this->getBaseData() + [
                'attachzone' => $this->getAttachzone()->getTwigContext(),
                'uploadMissing' => $this->getSession()->getSegment(static::class)->getFlash('uploadMissing', false)
            ],
        ]);
    }


    /**
     * Gets base template data. Actions should extend these template vars.
     *
     * @return array
     * @throws RouteNotFound
     * @throws \ReflectionException
     */
    protected function getBaseData(): array
    {
        return [
            'pageCurrent' => $this->getRouter()->generate("{$this->basename}.getUpload"),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->getRouter()->generate("{$this->basename}.getUpload")),
            'allNavigation' => $this->navigation->buildNavigation($this->getRouter(), $this->basename)->getAllNodes(),
            'basename' => $this->basename,
            'now' => \Alaress_Date_Format::now(),
        ];
    }


    /**
     * User preview action
     */
    public function postPreview()
    {
        $this->extendExecutionTime();

        $logger = $this->buildLogger();
        try {
            $reader = $this->buildReader($upload = $this->buildUpload());
        } catch (Exception $e) {
            $this->getSession()->getSegment(static::class)->setFlash('uploadMissing', true);
            $this->getResponse()->redirect->to($this->getRouter()->generate("{$this->basename}.getUpload"));

            return;
        }

        $this->buildImporter($reader, $logger)->save();

        $this->getResponse()->content->set([
            'template' => "@{$this->basename}/preview.twig",
            'data' => $this->getBaseData() + [
                'log' => [
                    'lines' => $this->parseLogLines($logger),
                ],
                'attachzone' => $this->getAttachzone($upload->getHash())->getTwigContext(),
            ],
        ]);
    }


    /**
     * User import action
     */
    public function postImport()
    {
        $this->extendExecutionTime();

        $logger = $this->buildLogger();

        try {
            $reader = $this->buildReader($upload = $this->buildUpload());
        } catch (Exception $e) {
            $this->getSession()->getSegment(static::class)->setFlash('uploadMissing', true);
            $this->getResponse()->redirect->to($this->getRouter()->generate("{$this->basename}.getUpload"));

            return;
        }

        $this->buildImporter($reader, $logger, true)->save();

        $this->getResponse()->content->set([
            'template' => "@{$this->basename}/import.twig",
            'data' => $this->getBaseData() + [
                'log' => [
                    'lines' => $this->parseLogLines($logger)
                ],
            ],
        ]);
    }


    /**
     * Gets an attachzone used to upload a file for import
     *
     * @param string|null $hash if set, the hash of the file to attach
     * @param bool        $readonly
     * @return Zone
     * @throws \UnexpectedValueException
     */
    protected function getAttachzone(string $hash = null, bool $readonly = false): Zone
    {
        $zone = (new Zone('import'))
            ->setModalTitle('Select CSV')
            ->setAttachmentsLabel('Upload CSV')
            ->setAttachPrompt('upload CSV')
            ->setReadOnly($readonly)
            ->setMulti(false);

        if (null !== $hash) {
            $zone->attachFiles([$hash]);
        }

        return $zone;
    }


    /**
     * Builds a logger for use in the importer.
     *
     * @return CacheableLoggerInterface
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface if the logger wasn't found?
     */
    protected function buildLogger(): CacheableLoggerInterface
    {
        return $this->getDi()->get('ups.logger');
    }


    /**
     * Builds a CSV reader out of the uploaded file.
     *
     * @param Storage\IStorage $upload
     * @return Reader
     * @throws \RuntimeException
     */
    protected function buildReader(Storage\IStorage $upload): Reader
    {
        return $this->importerFactory->buildReader($this->basename, $this->filesystemProxy->readStream($upload));
    }


    /**
     * Builds a storage object out of what was uploaded.
     *
     * @return Storage\IStorage
     * @throws \InvalidArgumentException
     * @throws Exception if nothing was uploaded
     * @throws \UnexpectedValueException
     */
    protected function buildUpload(): Storage\IStorage
    {
        $factory = new CollectorFactory();
        $attach  = $this->getRequest()->post->get('attachment');

        if (\is_array($attach)) {
            return $factory->build($attach)->getFirst();
        }

        throw new Exception('No file was uploaded');
    }


    /**
     * Builds an importer object based on this subcontroller's basename.
     *
     * @param Reader          $reader
     * @param LoggerInterface $log
     * @param bool            $performUpdate
     *
     * @return AbstractImporter
     * @throws \OutOfRangeException
     */
    protected function buildImporter(Reader $reader, LoggerInterface $log, bool $performUpdate = false): AbstractImporter
    {
        return $this->importerFactory->buildImporter($this->basename, $reader, $log, $performUpdate);
    }


    /**
     * Parses lines out of the logger.
     *
     * @param CacheableLoggerInterface $logger
     * @return array
     * @throws \Schoolbox\File\Exception
     */
    protected function parseLogLines($logger): array
    {
        $stream   = $this->filesystemProxy->readStream($logger->flushToStorage());
        $logLines = [];

        while ($line = fgets($stream)) {
            if ($logLine = json_decode($line, true, 512, JSON_THROW_ON_ERROR)) {
                $logLines[] = $logLine;
            }
        }

        return $logLines;
    }
}
