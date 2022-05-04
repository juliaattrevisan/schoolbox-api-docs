<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Home;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Connector\NullConnector;
use Alaress\Database\Exception as AlaressDatabaseException;
use Alaress\Web\Controller\Base;
use Alaress_Byte_Format;
use Aws\CloudWatch\CloudWatchClient;
use Aws\S3\S3Client;
use Component_DefinitionMapper;
use Component_Homepage_EtherpadController;
use GuzzleHttp;
use InvalidArgumentException;
use Schoolbox\Admin\Navigation\Subnav;
use Psr\Log\AbstractLogger;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Database;
use Schoolbox\External\Connector\Log\Mapper as ImportLogMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\FilesystemFactory;
use Healthcheck;
use Schoolbox\Resource\Storage\S3\DisasterRecovery;
use Schoolbox\Version\Version;
use Schoolbox\InstanceRegistry\InstanceRegistry;
use UnexpectedValueException;

use function array_column;
use function array_map;
use function array_merge;
use function array_sum;
use function preg_filter;
use function str_replace;
use function strpos;
use function trim;

class Controller extends Base
{
    /**
     * @var Database\Factory
     */
    private $databaseFactory;

    /**
     * @var null|IConnector
     */
    private $databaseExternal;

    /**
     * @var ConfigMapper
     */
    private ConfigMapper $configMapper;

    /**
     * @var ImportLogMapper
     */
    protected ImportLogMapper $importLogMapper;


    /**
     * Constructor
     *
     * @param Database\Factory $databaseFactory
     * @param IConnector|null  $databaseExternal Will be NULL if external is disabled in config, or if unable to connect
     * @param ConfigMapper     $configMapper
     * @param ImportLogMapper  $importLogMapper
     */
    public function __construct(
        Database\Factory $databaseFactory,
        ?IConnector $databaseExternal,
        ConfigMapper $configMapper,
        ImportLogMapper $importLogMapper
    ) {
        $this->databaseFactory  = $databaseFactory;
        $this->databaseExternal = ($databaseExternal instanceof NullConnector) ? null : $databaseExternal;
        $this->configMapper     = $configMapper;
        $this->importLogMapper  = $importLogMapper;
    }

    /**
     * save that the currently logged in administrator does not want to be reminded
     * to upgrade to the current major version
     *
     * @api GET /adminv2/home/dismiss-upgrade-reminder
     */
    public function dismissUpgradeReminder()
    {
        $latestVersionMajor = (new InstanceRegistry($this->getConfigLoader()))->getLatestVersionMajor();
        $configDismissUpgradeReminder = $this->configMapper->findOneByColumn('name', 'dismiss_version');

        //update the config item with the current instance major version
        $configDismissUpgradeReminder->setValue($latestVersionMajor);
        $this->configMapper->save($configDismissUpgradeReminder);

        $this->response->status->set(200);
        $this->response->content->set(
            $this->buildJsonOutput(
                [
                    'success'  => true,
                    'messages' => []
                ]
            )
        );
    }

    /**
     * Display admin settings homepage
     */
    public function getIndex()
    {
        // Disk usage
        $adapter      = $this->getConfigLoader()->get(ConfigMapper::NAME_OTFS_ADAPTER);
        $usageInGraph = $adapter === FilesystemFactory::FILE_SYSTEM_ADAPTER_LOCAL;

        if ($usageInGraph) {
            $disk_total      = disk_total_space($this->getConfigLoader()->get('storage_dir'));
            $disk_free       = disk_free_space($this->getConfigLoader()->get('storage_dir'));
            $disk_used       = Alaress_Byte_Format::bytesToString($disk_total - $disk_free);
            $disk_percentage = round((($disk_total - $disk_free) / $disk_total) * 100);
        } else {
            $disk_total      = null;
            $disk_free       = null;
            $disk_percentage = null;
            $s3info          = $this->buildDisasterRecoveryContext(true);
            $disk_used       = $s3info['bucketSize'] ?? null;
        }

        // Memory Usage

        $m = [];

        if (is_readable('/proc/meminfo')) {
            foreach (file('/proc/meminfo') as $ri) {
                $m[strtok($ri, ':')] = str_replace(' kB', '', \trim(strtok(''))) * 1024;
            }

            $memory_total = $m['MemTotal'];
            $memory_used = $memory_total - $m['MemAvailable'];
            $memory_percentage = round($memory_used / $m['MemTotal'] * 100);
            $memory_gradient = 10 - floor($memory_percentage / 10);
        } else {
            $memory_total = '';
            $memory_used = '';
            $memory_percentage = '';
            $memory_gradient = '';
        }

        // check ssh status
        if (file_exists('/etc/schoolbox/fleet-connected')) {
            $last_touched = filemtime('/etc/schoolbox/fleet-connected');
            $touched_diff = time() - $last_touched;
            if ($touched_diff < 7200) {
                $ssh_light  = 'success';
                $ssh_status = 'Accessible';
            } elseif ($touched_diff < 86400) {
                $ssh_light  = 'warning';
                $ssh_status = 'Offline >2hrs';
            } else {
                $ssh_light  = 'alert';
                $ssh_status = 'Offline >24hrs';
            }
        } else {
            $ssh_light  = 'alert';
            $ssh_status = 'Offline';
        }

        $version = new Version($this->getConfigLoader());

        /* Decide if we have any SUDS import indicators to render
         * If you're running csv, no suds selected, or running a live connector,
         * not much point showing this thing
         */
        $sudsEnabled = !in_array((string)$this->getConfigLoader()->get('schooldata_import_data_source'), ['', \Schoolbox\External\Importer\Endpoint\Factory::CSV], true);

        /* Find the most recent import logs for each of the possibilities
         */
        $importLogs = null;

        if ($sudsEnabled) {
            $importLogs = $this->importLogMapper->status();
        }

        $this->response->content->set(
            array(
                'template' => '@adminHome/index.twig',
                'data' => array(
                    'pageCurrent' => $this->router->generate('adminHome.getIndex'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminHome.getIndex')),
                    'usageInGraph' => $usageInGraph,
                    'showUpgradeReminder'  => $version->showUpgradeReminder(),
                    'major_site_version'   => $version->getMajor(),
                    'major_latest_version' => (new InstanceRegistry($this->getConfigLoader()))->getLatestVersionMajor(),
                    'disk_used' => $disk_used,
            //                    'disk_used' => Alaress_Byte_Format::bytesToString($disk_total - $disk_free),
                    'disk_total' => Alaress_Byte_Format::bytesToString($disk_total),
                    'disk_percentage' => Alaress_Byte_Format::bytesToString($disk_percentage),
                    'memory_used' => Alaress_Byte_Format::bytesToString($memory_used),
                    'memory_total' => Alaress_Byte_Format::bytesToString($memory_total),
                    'memory_percentage' => $memory_percentage,
                    'memory_gradient' => $memory_gradient,
                    'external_db' => ucfirst($this->getConfigLoader()->get('external_type')),
                    'ldap_enabled' => $this->getConfigLoader()->get('ldap_enable'),
                    'backupsEnabled' => $this->getDisasterRecoveryBackupsEnabled(),
                    'collabDocsEnabled' => $this->collabDocsEnabled(),
                    'ssh_light' => $ssh_light,
                    'ssh_status' => $ssh_status,
                    'is_cloud'  => $this->isCloudHosted(),
                    'api_url'   => $this->getConfigLoader()->get('schoolbox_api_url'),

                    /* SUDS panel
                     */
                    'suds_import_data_enabled' => $sudsEnabled,
                    'suds_import_logs' => $importLogs,
                    'show_port_80_availability_check' => $this->getConfigLoader()->get('show_port_80_availability_check'),
                    'saml_enabled' => $this->getConfigLoader()->get('saml_enabled'),
                ),
            )
        );
    }

    /**
     * Provides a view of the current external DB health, linked from the admin dashboard
     */
    public function getHealthcheck()
    {
        // Anonymous class that just captures the log messages
        $logger = new class extends AbstractLogger {
            private array $log = [];

            public function log($level, $message, array $context = []): void
            {
                $this->log[] = $message;
            }

            public function getLog(): string
            {
                return implode("\n", $this->log);
            }
        };
        try {
            $healthCheck = new Healthcheck(
                $this->databaseExternal,
                $this->databaseFactory,
                $this->getConfigLoader(),
                $logger
            );
            $healthCheck->run();
            $output = $healthCheck->results();
            $output['log'] = $logger->getLog();
        } catch (InvalidArgumentException | AlaressDatabaseException $e) {
            $output = [
                'errors' => [
                    $e->getMessage()
                ]
            ];
        }

        $this->response->content->set(
            array(
                'template' => '@adminHome/healthcheck.twig',
                'data' => array(
                    'pageCurrent' => $this->router->generate('adminHome.getIndex'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminHome.getIndex')),
                    'output' => $output
                )
            )
        );
    }


    /**
     * Displays the 'Disaster Recovery Backups' page
     *
     * @since        20.1
     * @api          /adminv2/home/disaster-recovery
     * @noinspection PhpUnused
     * @throws InvalidArgumentException
     */
    public function getDisasterRecovery(): void
    {
        // Data only fetched if feature is enabled
        $isEnabled = $this->getDisasterRecoveryBackupsEnabled();
        if ($isEnabled) {
            $enabledData = $this->buildDisasterRecoveryContext();
        } else {
            $enabledData = [];
        }

        $this->response->content->set(
            [
                'template' => '@adminHome/disaster-recovery.twig',
                'data'     => array_merge(
                    [
                        'pageCurrent'    => $this->router->generate('adminHome.getIndex'),
                        'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminHome.getIndex')),
                        'enabled'        => $isEnabled,
                    ],
                    $enabledData
                ),
            ]
        );
    }


    /**
     * Builds the context data for the Disaster Report
     *
     * @param bool $bucketSizeOnly True to get bucket size
     * @return array
     * @throws InvalidArgumentException
     */
    private function buildDisasterRecoveryContext(bool $bucketSizeOnly = false): array
    {
        $enabledData = [];

        $enabledData['mostRecentSyncStatus'] = $this->getConfigLoader()->get(DisasterRecovery::CONFIG_DR_SYNC_STATUS);
        $enabledData['fqdn']                 = "{$this->getConfigLoader()->get('site_hostname')}.{$this->getConfigLoader()->get('site_domain')}";
        try {
            $domainTtl                = (new TtlFetcher())->getTtl($enabledData['fqdn']);
            $enabledData['domainTtl'] = $domainTtl;
        } catch (UnexpectedValueException $e) {
            // We want to know about this, but continue to render the rest of the page.
            $this->captureException($e);
        }

        $s3AccessKey = trim($this->getConfigLoader()->get('otfs_s3_cred_key') ?? "");
        $s3SecretKey = trim($this->getConfigLoader()->get('otfs_s3_cred_secret') ?? "");
        $s3Region    = trim($this->getConfigLoader()->get('otfs_s3_region') ?? "");
        $s3Version   = trim($this->getConfigLoader()->get('otfs_s3_version') ?? "");
        $s3Bucket    = trim($this->getConfigLoader()->get('otfs_s3_bucket') ?? "");

        $s3CredentialsMissing =
            $s3AccessKey === '' ||
            $s3SecretKey === '' ||
            $s3Region === '' ||
            $s3Version === '' ||
            $s3Bucket === '';

        if ($s3CredentialsMissing) {
            /** @noinspection UnsupportedStringOffsetOperationsInspection */
            $enabledData['errors'][] = 'AWS S3 credentials are invalid';
            return $enabledData;
        }

        $s3Client = new S3Client([
            'credentials' => [
                'key'    => $s3AccessKey,
                'secret' => $s3SecretKey,
            ],
            'region'      => $s3Region,
            'version'     => $s3Version,
            'http'    => [
                GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => FilesystemFactory::getConnectTimeout(),
            ],
        ]);

        $cloudWatchClient = new CloudWatchClient([
            'credentials' => [
                'key'    => $s3AccessKey,
                'secret' => $s3SecretKey,
            ],
            'region'      => $s3Region,
            'version'     => $s3Version,
        ]);

        $bucketSize = 0;
        // List all bucket size metrics for this bucket. There may be 0 or more.
        $metrics      = $cloudWatchClient->listMetrics([
            'Namespace'  => 'AWS/S3',
            'MetricName' => 'BucketSizeBytes',
            'Dimensions' => [
                [
                    'Name'  => 'BucketName',
                    'Value' => $s3Bucket,
                ],
            ],
        ]);
        $storageTypes = [];
        foreach ($metrics->get('Metrics') as $metric) {
            foreach ($metric['Dimensions'] as $dimension) {
                if ($dimension['Name'] === 'StorageType') {
                    $storageTypes[] = $dimension['Value'];
                }
            }
        }
        foreach ($storageTypes as $storageType) {
            $bucketSize += (new CloudWatchMetrics($cloudWatchClient))->getBucketSize($s3Bucket, $storageType);
        }


        if ($bucketSizeOnly) {
            return ['bucketSize' => Alaress_Byte_Format::bytesToString($bucketSize)];
        }
        $objectCount = (new CloudWatchMetrics($cloudWatchClient))->getNumberOfObjects($s3Bucket);

        $objects = $s3Client->getIterator('ListObjects', [
            'Bucket' => $s3Bucket,
            'Prefix' => DisasterRecovery::PREFIX_BACKUPS,
        ]);

        $backupFileCount = 0;
        $backupFileUsage = 0;
        $files           = [];
        foreach ($objects as $object) {
            $backupFileCount++;
            $backupFileUsage += $object['Size'];
            $prefix          = DisasterRecovery::PREFIX_BACKUPS;
            $date            = preg_filter("#$prefix(\d{4}-\d{2}-\d{2})/.+#", '$1', $object['Key']);

            if (strpos($object['Key'], "conf_{$date}.php") !== false) {
                $files[$date]['conf'] = $object['Size'];
            } elseif (strpos($object['Key'], "etc_{$date}.tar.gz") !== false) {
                $files[$date]['etc'] = $object['Size'];
            } elseif (strpos($object['Key'], "redis_{$date}.rdb.gz") !== false) {
                $files[$date]['redis'] = $object['Size'];
            } elseif (strpos($object['Key'], "{$date}.sql.gz") !== false) {
                $files[$date]['mariaDb'] = $object['Size'];
            }
        }
        foreach ($files as $date => $dateFiles) {
            $files[$date]['total'] =
                ($dateFiles['conf'] ?? 0) +
                ($dateFiles['etc'] ?? 0) +
                ($dateFiles['redis'] ?? 0) +
                ($dateFiles['mariaDb'] ?? 0);
        }

        $fileTotals = [
            'conf'    => Alaress_Byte_Format::bytesToString(array_sum(array_column($files, 'conf'))),
            'etc'     => Alaress_Byte_Format::bytesToString(array_sum(array_column($files, 'etc'))),
            'redis'   => Alaress_Byte_Format::bytesToString(array_sum(array_column($files, 'redis'))),
            'mariaDb' => Alaress_Byte_Format::bytesToString(array_sum(array_column($files, 'mariaDb'))),
            'total'   => Alaress_Byte_Format::bytesToString(array_sum(array_column($files, 'total'))),
        ];

        foreach ($files as $date => $dateFiles) {
            $files[$date] = array_map(static function (string $size): string {
                return Alaress_Byte_Format::bytesToString($size);
            }, $dateFiles);
        }

        // Sort most recent backup first
        uksort($files, static function (string $left, string $right): int {
            return strcmp($right, $left);
        });

        $enabledData['backupFiles']      = $files;
        $enabledData['backupFileTotals'] = $fileTotals;


        // Count of storage files is equal to total objects minus backups
        $enabledData['bucketSize']       = Alaress_Byte_Format::bytesToString($bucketSize);
        $enabledData['storageFileCount'] = $objectCount - $backupFileCount;
        $enabledData['storageFileUsage'] = Alaress_Byte_Format::bytesToString($bucketSize - $backupFileUsage);

        return $enabledData;
    }


    /**
     * Returns whether the disaster recovery backups feature is running on this instance
     *
     * @return bool
     */
    private function getDisasterRecoveryBackupsEnabled(): bool
    {
        return $this->getConfigLoader()->get('aws_s3_dr_backup_enabled');
    }

    /**
     * Returns whether etherpad is enabled
     *
     * @return bool
     */
    private function collabDocsEnabled(): bool
    {
        // try catch to prevent Schoolbox\Mapper\Exception\NotFound if it is disabled
        try {
            $collabDocsDefinition = Component_DefinitionMapper::getInstance()->findOneByColumn(
                "class",
                Component_Homepage_EtherpadController::class
            );
        } catch (Exception $e) {
            $this->captureException($e);
            return false;
        }
        return $collabDocsDefinition->getEnabled();
    }

    private function isCloudHosted(): bool
    {
        return $this->getConfigLoader()->get('site_environment') === Version::SITE_ENVIRONMENT_CLOUD_SAAS;
    }
}
