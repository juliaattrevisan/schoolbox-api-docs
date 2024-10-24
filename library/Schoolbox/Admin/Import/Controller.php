<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Import;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Lock\InstanceLockFactory;
use Alaress\Lumberjack\Logger;
use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Aura\Router\Exception\RouteNotFound;
use PDOException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use ReflectionException;
use Schoolbox\Admin\Import\Navigation\SchoolData;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\Settings\ModuleConfig;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\Connector\DSNFactory;
use Schoolbox\External\Connector\Liss\Base\Endpoint\PayloadList;
use Schoolbox\External\Event\Export\Classes;
use Schoolbox\External\Event\Export\Timetable;
use Schoolbox\External\Importer\Endpoint;
use Schoolbox\External\Importer\Endpoint\Scheduler;
use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Schoolbox\External\Importer\Endpoint\ValidUsesScheduleTimetable;
use Schoolbox\Mapper\Exception;
use Symfony\Component\Lock\LockInterface;
use Twig\Error\Error;
use UnexpectedValueException;

/**
 * Class Controller
 *
 * Present a landing page for the import destination you chose
 *
 * @package Schoolbox\Admin\Import
 */
class Controller extends ConfigController
{
    /**
     * Default message if you happen to have the status report open
     * but you got your settings arbitrarily reset
     */
    private const DEFAULT_UNKNOWN_DATA_SOURCE = 'Unknown data source selected.';


    /**
     * Constructor
     *
     * @param ConfigMapper $configMapper
     * @param FieldStrategyFactory $fieldStrategyFactory
     * @param Rebuilder $rebuilder
     * @param CacheInterface $oidcCache
     * @param \Schoolbox\External\Connector\Log\Mapper $importLogMapper
     * @param \Schoolbox\External\Event\Mapper $eventMapper
     * @param ClassEntryMapper $classEntryMapper
     * @param \Schoolbox\Resource\Storage\Mapper $storageMapper
     * @param \Schoolbox\Resource\File\Mapper $fileMapper
     * @param \Schoolbox\External\Connector\ImportQueue\Mapper $importQueueMapper
     */
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache,
        protected \Schoolbox\External\Connector\Log\Mapper $importLogMapper,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\ClassEntryMapper $classEntryMapper,
        private \Schoolbox\Resource\Storage\Mapper $storageMapper,
        private \Schoolbox\Resource\File\Mapper $fileMapper,
        private \Schoolbox\External\Connector\ImportQueue\Mapper $importQueueMapper
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder, $oidcCache);
    }


    /**
     * Landing Page for School Data Import
     *
     * @throws ContainerExceptionInterface
     * @throws InvalidColumnException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws RouteNotFound
     * @throws Error
     */
    public function getSchoolDataIndex(): void
    {
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate(),
            $this->routeGenerator->generate('adminImportIndex.postSchoolDataOptions'),
            [ModuleConfig::CONTEXT_USE_BOTTOM_DRAWER => false,],
        );

        $externalType = (string)$this->getConfigLoader()->get('external_type');
        $host = (string)$this->getConfigLoader()->get('schooldata_import_endpoint_host');
        $key = (string)$this->getConfigLoader()->get('schooldata_import_endpoint_key');
        $dataSource = (string)$this->getConfigLoader()->get('schooldata_import_data_source');
        $type = (string)$this->getConfigLoader()->get('schooldata_import_data_source');
        $timetableView = (string)$this->getConfigLoader()->get('timetable_view');

        $hasIndicator = $host !== '' && $key !== '';

        /* Check the DSN assembly here - produce the corresponding DSN codec,
         * decode the parts, then provide it to the ui elements
         *
         * Shank it directly into configContext because that was a thing
         */
        $configContext['dsn'][$dataSource] = (new DSNFactory())->produceDSNParts($dataSource, $key);

        /* If you run the Live Sync, you might get a message about it
         * This message persists to keep reminding people that it's still going
         */
        $hasPerformUpdate = false;

        /* Decide if you can execute a Perform Live Update
         *   - You need to be on an actual suds connector
         *     (ie. selected something other than CSV and "Please select")
         *   - A lock must be obtainable (ie. a lock has not yet been acquired)
         */
        $canPerformUpdate = false;

        if ('' !== $type) {
            /* No lock? You can perform the update, but immediately release the lock
             * so you can actually do that
             */
            try {
                /* Spawn a logger out of darkness
                 * Was there a particular way to do this?
                 */
                $lock = $this->acquireLock($this->spawnLogger(), Scheduler::LOCK_NAME, 1);
                $lock->release();

                $canPerformUpdate = true;
            } catch (\RuntimeException $e) {
                // noop

                /* However, it means that the sync is running in some way
                 */
                $hasPerformUpdate = true;
            }
        }

        /* Input payloads for liss that we capture and store
         */
        $lissInputPayloads = (new PayloadList($this->storageMapper))->generate();

        $this->response = $this->responseFromTemplate(
            '@adminImportIndex/schoolData/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminImportIndex.getSchoolDataIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminImportIndex.getSchoolDataIndex')),
                'allNavigation'  => (new SchoolData())->buildNavigation($this->routeGenerator)->getAllNodes(),
                'dataSource' => $dataSource,
                'configContext' => $configContext,
                'hasIndicator' => $hasIndicator,
                'statusIndicatorRoute' => $this->routeGenerator->generate('adminImportIndex.getStatusIndicator'),
                'importLogs' => $this->importLogMapper->findLogsWithDuration(),
                /* A list of user-initiated syncs
                 */
                'importQueue' => [
                    'script' => Scheduler::MANUAL_SIS_SCRIPT_NAME,
                    'instance' => $this->importQueueMapper->findAll()->first()
                ],
                /* Find any LISS input payloads that we might have logged
                 */
                'lissInputPayloads' => $lissInputPayloads,
                /* Decide if the Perform Live Sync is available based on the current
                 * data source selected (you can't do it if you're using No Data Source
                 * or if you're using CSV Files)
                 */
                'canPerformUpdate' => $canPerformUpdate,
                /* A flash message to tell users to w8
                 */
                'hasPerformUpdate' => $hasPerformUpdate,
                /* Decide if the current suds selection uses the cyclic grid
                 */
                'cyclicGrid' => !(new ValidUsesScheduleTimetable())->isSatisfiedBy($externalType, $dataSource, $timetableView)
            ],
            'School Data Import'
        );
    }


    /**
     * Save the configuration for School Data Import's chosen mechanism
     *
     * @throws InvalidColumnException
     * @throws RouteNotFound
     * @throws PDOException
     * @throws Exception
     * @throws UnexpectedValueException
     */
    public function postSchoolDataOptions(): void
    {
        $postData = $this->requestPost->getArrayCopy();

        /* Check the DSN assembly here - produce the corresponding DSN codec,
         * encode the parts, modify $postData, then save it
         *
         * This is the schooldata endpoint key which we would have assembled manually
         */
        $postData['schooldata_import_endpoint_key'] = (new DSNFactory())
            ->produceDSNString($postData['schooldata_import_data_source'] ?? '', $postData['dsn'] ?? []);

        /* Trim the schooldata_import_endpoint_host, as it could possibly have whitespace which should be removed...
         */
        $postData['schooldata_import_endpoint_host'] = trim((string)$postData['schooldata_import_endpoint_host']);

        /* We don't need this array element which has no corresponding db rows
         */
        unset($postData['dsn']);

        /* Save it
         */
        $this->updateConfig($postData, $this->buildConfigPredicate());

        /* Go back to the place from whence you came
         */
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminImportIndex.getSchoolDataIndex'));
    }


    /**
     * Returns a predicate builder for finding all custom config for this module
     *
     * @return Builder
     * @throws InvalidColumnException
     */
    private function buildConfigPredicate(): Builder
    {
        return $this->getConfigMapper()->getPredicateBuilder()->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_SCHOOLDATA);
    }


    /**
     * Build asynchronous status indicator payload
     */
    public function getStatusIndicator(): void
    {
        $host = $this->getConfigLoader()->get('schooldata_import_endpoint_host');
        $key = $this->getConfigLoader()->get('schooldata_import_endpoint_key');
        $type = (string)$this->getConfigLoader()->get('schooldata_import_data_source');
        $includePhoto = $this->getConfigLoader()->get('photo_sis_import');

        $isReachable = false;

        if ('' !== $type) {
            try {
                $status = (new Endpoint\Status\Factory($host, $key, $includePhoto))->produce($type);

                if ($status) {
                    $isReachable = $status->isReachable();
                }
            } catch (\DomainException | \InvalidArgumentException $e) {
                // noop
            }
        }

        $this->response = $this->responseFromTemplateUnthemed('@adminImportIndex/schoolData/status.twig', [
            'isReachable' => $isReachable
        ]);
    }


    /**
     * Build asynchronous status report
     */
    public function getStatusReport(): void
    {
        $host = $this->getConfigLoader()->get('schooldata_import_endpoint_host');
        $key = $this->getConfigLoader()->get('schooldata_import_endpoint_key');
        $type = (string)$this->getConfigLoader()->get('schooldata_import_data_source');
        $includePhoto = $this->getConfigLoader()->get('photo_sis_import');

        $report = null;
        $message = self::DEFAULT_UNKNOWN_DATA_SOURCE;

        if ('' !== $type) {
            try {
                $status = (new Endpoint\Status\Factory($host, $key, $includePhoto))->produce($type);

                if ($status) {
                    $report = $status->getReport();
                }
            } catch (\DomainException | \InvalidArgumentException $e) {
                $message = $e->getMessage();
            }
        }

        $this->response = $this->responseFromTemplate(
            '@adminImportIndex/schoolData/statusReport.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminImportIndex.getSchoolDataIndex'),
                'report' => \is_array($report) ? $report : [[
                    'message' => $message,
                    'reason' => '',
                    'status' => ISpecification::STATUS_ERROR
                ]]
            ]
        );
    }


    /**
     * Set up the live sync
     *
     * @throws RouteNotFound
     * @throws \Exception
     */
    public function getPerformUpdate(): void
    {
        $type = $this->getConfigLoader()->get('schooldata_import_data_source');

        /* Cannot do this with No Data Source, or with CSV Files
         * Exit early if either of those are selected
         *
         * You shouldn't be able to get here, but if you do,
         * throw a generic error. You could perhaps get here
         * by bookmarking the url or visiting it directly.
         */
        if ('' === $type) {
            throw $this->generateException(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        $queuedRequests = $this->importQueueMapper->findAll();

        /* Only allow one request at a time, per strat (TODO: implement per-strat limitation)
         * but that's a future evolution; for now, just mimic what we used to have, for Civica
         */
        if (0 === $queuedRequests->count()) {
            $importQueue = $this->importQueueMapper->createModel()
                ->setUser($this->getUser());

            $this->importQueueMapper->save($importQueue);
        }

        // Redirect back to ruleset page, with either an alert message on the page or in notifications
        $this->response = $this->redirectFound($this->routeGenerator->generate('adminImportIndex.getSchoolDataIndex'));
    }


    /* Lock Acquisition
     * cloned from cli land because we can perhaps give user feedback here
     */

    /**
     * Acquires and returns a lock object.
     *
     * @param LoggerInterface $logger
     * @param string          $lockName The name of the lock to acquire.
     *                                  Will be automatically made unique across
     *                                  separate instances.
     * @param float|null      $timeout  The amount of time (in seconds) to hold the
     *                                  lock for.
     * @return LockInterface
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \RuntimeException
     */
    protected function acquireLock(LoggerInterface $logger, string $lockName, ?float $timeout): LockInterface
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        /** @var InstanceLockFactory $lockFactory */
        $lockFactory = $this->getDi()->get('lockFactory');
        $lock        = $lockFactory->createLock($lockName, $timeout);

        // Try to acquire this lock
        try {
            $acquired = $lock->acquire();
        } catch (\Exception $e) {
            /* Any weird exception being thrown here - lock-related or otherwise -
             * is going to preclude a proper lock acquisition and you should be
             * prevented from doing whatever you're doing
             */
            $message = 'This process is already running for this instance: trusting it will self-terminate on timeout';

            $logger->warning($message);

            throw new \RuntimeException($message);
        }

        /* You didn't die, but you also couldn't acquire a lock
         * Drop a nice message because this is an intended behaviour
         */
        if (!$acquired) {
            $message = sprintf(
                'Could not acquire %s lock',
                $lockName
            );

            $logger->warning($message);

            throw new \RuntimeException($message);
        }

        return $lock;
    }


    /**
     * Spawn a Logger instance, for the lock mechanism
     *
     * @return Logger
     */
    private function spawnLogger(): Logger
    {
        return new Logger(STDOUT, STDOUT, \Monolog\Logger::INFO, [], true, 'suds');
    }


    /**
     * Exports the contents of sis_event into a csv format
     *
     * @return void
     * @throws PDOException
     * @throws \InvalidArgumentException
     * @api /adminv2/import/schoolDataImport/timetable/export
     */
    public function getTimetableExport(): void
    {
        $content = (new Timetable($this->eventMapper))->export();

        $this->response = $this->responseFromContent($content)
            ->withHeader('Pragma', ' ')
            ->withHeader('Cache-Control', ' ')
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Length', \strlen($content))
            ->withHeader('Content-Disposition', "attachment; filename=\"export-school-data-timetable.csv\"")
        ;
    }


    /**
     * Exports the contents of sis_class into a csv format
     *
     * @return void
     * @throws PDOException
     * @throws \InvalidArgumentException
     * @api /adminv2/import/schoolDataImport/classes/export
     */
    public function getClassesExport(): void
    {
        $content = (new Classes($this->classEntryMapper))->export();
        $this->response = $this->responseFromContent($content)
            ->withHeader('Pragma', ' ')
            ->withHeader('Cache-Control', ' ')
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Length', \strlen($content))
            ->withHeader('Content-Disposition', "attachment; filename=\"export-school-data-classes.csv\"");
    }


    /**
     * Explicitly hard-delete a storage file corresponding to a liss payload
     *
     * @return void
     */
    public function postDeleteLissPayload(): void
    {
        $hash = $this->requestPost->get('hash');

        if (null === $hash) {
            return;
        }

        /* Delete from the file table, if you dragged it into Resource Browser manually
         */
        try {
            $file = $this->fileMapper->findOneByColumn('hash', $hash);

            $this->fileMapper->hardDelete($file);
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* noop
             * This might have been uploaded by the system, not a human
             */
        }

        try {
            $payload = $this->storageMapper->findByHash($hash);

            $this->storageMapper->actualDelete($payload);
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* noop
             * Hash not found, for some reason; maybe deleted via another tab
             */
        }
    }
}
