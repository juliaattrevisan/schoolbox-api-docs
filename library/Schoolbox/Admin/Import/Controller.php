<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Import;

use Alaress\Async\Notification\Instance\Builder as NotificationInstanceBuilder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Queue\Composite;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Lock\InstanceLockFactory;
use Alaress\Lumberjack\Logger;
use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Aura\Router\Exception\RouteNotFound;
use PDOException;
use Predis\ClientInterface;
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
use Schoolbox\External\Connector\Log\Mapper;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Connector\Watchlist;
use Schoolbox\External\Event\Export\Classes;
use Schoolbox\External\Event\Export\Timetable;
use Schoolbox\External\Importer\Endpoint;
use Schoolbox\External\Importer\Endpoint\Factory;
use Schoolbox\External\Importer\Endpoint\Scheduler;
use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Schoolbox\External\Importer\Endpoint\ValidUsesScheduleTimetable;
use Schoolbox\Mapper\Exception;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\MessageList\Listed;
use Schoolbox\Realtime\MessageList\Unread;
use Symfony\Component\Lock\LockInterface;
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
     * @param ConfigMapper                       $configMapper
     * @param FieldStrategyFactory               $fieldStrategyFactory
     * @param Rebuilder                          $rebuilder
     * @param CacheInterface                     $oidcCache
     * @param Mapper                             $importLogMapper
     * @param ClientInterface                    $redisStorage
     * @param \Schoolbox\External\Event\Mapper   $eventMapper
     * @param ClassEntryMapper                   $classEntryMapper
     * @param \Schoolbox\Resource\Storage\Mapper $storageMapper
     */
    public function __construct(
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache,
        protected \Schoolbox\External\Connector\Log\Mapper $importLogMapper,
        private ClientInterface $redisStorage,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\ClassEntryMapper $classEntryMapper,
        private \Schoolbox\Resource\Storage\Mapper $storageMapper
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

        if ('' !== $type && Factory::CSV !== $type) {
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

        $this->response = $this->responseFromTemplate(
            '@adminImportIndex/schoolData/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminImportIndex.getSchoolDataIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->routeGenerator->generate('adminImportIndex.getSchoolDataIndex')),
                'allNavigation'  => (new SchoolData())->buildNavigation($this->routeGenerator)->getAllNodes(),
                'dataSource' => $dataSource,
                'configContext' => $configContext,
                'hasIndicator' => $hasIndicator,
                'statusIndicatorRoute' => $this->routeGenerator->generate('adminImportIndex.getStatusIndicator'),
                'importLogs' => $this->importLogMapper->findLogsWithDuration(),
                /* Find any LISS input payloads that we might have logged
                 */
                'lissInputPayloads' => (new PayloadList($this->storageMapper))->generate(),
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
        if ('' === $type || Factory::CSV === $type) {
            throw $this->generateException(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        // Redirect back to ruleset page, with either an alert message on the page or in notifications
        $this->response = $this->redirectFound($this->routeGenerator->generate('adminImportIndex.getSchoolDataIndex'));

        /* Spawn a logger out of darkness
         * Was there a particular way to do this?
         */
        $logger = $this->spawnLogger();

        // Not the best place to put it but it will do for now
        $this->postOutputCallbacks[] = function () use ($logger) {
            $this->syncCallback($logger);
        };
        $this->response              = $this->responseEmpty();
    }


    /**
     * Performs an actual sync.
     */
    public function syncCallback(LoggerInterface $logger): void
    {
        /* Disable the mapper cache universally, by imitating a cli request.
         *
         * The mapper cache is disabled in the cli, but not in a web request.
         * $cacheEnabled and disableCache() are protected methods by strict
         * intent, to avoid a change to the base mapper, I can instead
         * do a little code cosplay here. Unhygienic but the alternative is
         * just as disgusting to contemplate
         */
        unset($_SERVER['REQUEST_METHOD']);

        try {
            $host        = $this->getConfigLoader()->get('schooldata_import_endpoint_host');
            $key         = $this->getConfigLoader()->get('schooldata_import_endpoint_key');
            $frequency   = Scheduler::HOURLY;
            $customHours = [];
            $type        = $this->getConfigLoader()->get('schooldata_import_data_source');
            $file        = null;
            $timeout     = (float)($this->getConfigLoader()->get('schooldata_import_timeout') ?: Scheduler::DEFAULT_TIMEOUT);

            /* Cannot do this with No Data Source, or with CSV Files
             * Exit early if either of those are selected
             */
            if ('' === $type || Factory::CSV === $type) {
                return;
            }

            /* Nobody is on the watchlist in this context
             */
            $watchlist   = (new Watchlist())->filter('');

            try {
                $lock = $this->acquireLock($logger, Scheduler::LOCK_NAME, $timeout);
            } catch (\RuntimeException $e) {
                return;
            }

            /* Explicitly set a time limit to conclude this sync
             * while running in a detached process. runPostOutputCallbacks()
             * arbitrarily sets a time limit so you have to override that
             */
            set_time_limit((int)$timeout);

            /* Get this particular db instance
             */
            $importLogMapper = \Schoolbox\External\Connector\Log\Mapper::getInstance();

            /* Split the transaction in half for suds connectors that can actually do that
             * Otherwise just import everything like it always did
             */
            if (\in_array($type, [Factory::TASS_API, Factory::COMPASS], true)) {
                $splitStrats = [
                    ValidStrats::FAMILIES . ',' . ValidStrats::STAFF,
                    ValidStrats::TIMETABLE
                ];
            } else {
                $splitStrats = [''];
            }

            /* The time when both parts of the import ran
             * Could do REQUEST_TIME but this is good enough
             */
            $time = time();

            foreach ($splitStrats as $currentStrat) {
                /* Each part of a Segregated Import gets its own run id
                 */
                $runId = (new \Schoolbox\External\Connector\Log\Guid())->generate();

                /* ValidStrats is now a cli option
                 */
                $validStrats = new ValidStrats($currentStrat);

                /* Execute scheduler: sis stuff
                 */
                $sisFactory = new Factory($host, $key, true, $logger, $watchlist, $validStrats);

                /* Importer strat gets suffixed onto the end
                 */
                $subtitle = '';

                if ('' !== $currentStrat) {
                    $subtitle = " ({$currentStrat})";
                }

                (new Scheduler(
                    $importLogMapper->getDatabase(),
                    $frequency,
                    $customHours,
                    $sisFactory,
                    $type,
                    $file,
                    $logger,
                    $importLogMapper
                ))->run($time, $runId, Scheduler::MANUAL_SIS_SCRIPT_NAME . $subtitle);
            }

            /* Execute scheduler: calendar stuff
             * Not all connectivities contain a calendar importer, just some
             */
            $calendarFactory = new Endpoint\CalendarFactory($host, $key, true, $logger);

            (new Scheduler(
                $importLogMapper->getDatabase(),
                $frequency,
                $customHours,
                $calendarFactory,
                $type,
                $file,
                $logger,
                $importLogMapper
            ))->run($time, $runId, Scheduler::MANUAL_CALENDAR_SCRIPT_NAME);

            /* Go to the "Previous Imports" area of SUDS Control Panel
             */
            $redirect = $this->routeGenerator->generate('adminImportIndex.getSchoolDataIndex') . '#previous';

            /* Arbitrarily inject and broadcast a notification,
             * bypassing all the usual Mr Matt mechanisms
             *
             * As recommended (for now) by Mr Sannang and Tomoko
             */
            $title = sprintf(
                'School Data Sync Live Run has finished at %s',
                \Alaress_Date_Format::now()->getLocal()
            );

            $bob  = new NotificationInstanceBuilder();
            $bob->setTitle($title)
                ->setAllowSelfSend(true)
                ->setSeverity(\Monolog\Logger::INFO)
                /* Alternatively I could make my own namespace...but should I?
                 * Namespace controls the icon selection but that's about it...?
                 */
                ->setNamespace(NSMapper::NS_MESSAGE)
                ->setIcon('/images/icon.php?size=64&icon=' . NSMapper::NS_MESSAGE)
                /* Go to suds import log
                 */
                ->setHref($redirect)
                ->addTopic(sprintf('%s.user.%d', Instance::DELIVERY_INSTANT, $this->getUser()->getId()));

            $composite = new Composite(
                new Listed($this->redisStorage, INSTANCE_ID, $this->getUser()->getId()),
                new Unread($this->redisStorage, INSTANCE_ID, $this->getUser()->getId())
            );

            $composite->push($bob->build());
        } catch (\Exception $e) {
            // the client has already detached - not much we can do
        }
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
     * @api /adminv2/import/schoolDataImport/timetable/export
     * @return void
     * @throws PDOException
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
     * @api /adminv2/import/schoolDataImport/classes/export
     * @return void
     * @throws PDOException
     */
    public function getClassesExport(): void
    {
        $content = (new Classes($this->classEntryMapper))->export();
        $this->response = $this->responseFromContent($content)
                               ->withHeader('Pragma', ' ')
                               ->withHeader('Cache-Control', ' ')
                               ->withHeader('Content-Type', 'text/csv')
                               ->withHeader('Content-Length', \strlen($content))
                               ->withHeader('Content-Disposition', "attachment; filename=\"export-school-data-classes.csv\"")
        ;
    }
}
