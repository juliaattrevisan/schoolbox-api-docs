<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Home;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception as AlaressDatabaseException;
use Alaress\Web\Controller\Base;
use Alaress_Byte_Format;
use Alaress_Date_Format;
use Aws\CloudWatch\CloudWatchClient;
use Aws\S3\S3Client;
use InvalidArgumentException;
use Schoolbox\Admin\Navigation\Subnav;
use Psr\Log\AbstractLogger;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Database;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\LogMapper;
use Proxy\Server as ProxyServer;
use Healthcheck;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\Resource\Storage\S3\DisasterRecovery;

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

    /** @var Kami\IConnector */
    private $kamiConnector;

    /** @var Database\Factory */
    private $databaseFactory;

    /** @var null|IConnector */
    private $databaseExternal;


    /**
     * @param Kami\IConnector  $kamiConnector
     * @param Database\Factory $databaseFactory
     * @param IConnector|null  $databaseExternal Will be NULL if external is disabled in config, or if unable to connect
     */
    public function __construct(Kami\IConnector $kamiConnector, Database\Factory $databaseFactory, ?IConnector $databaseExternal)
    {
        $this->kamiConnector    = $kamiConnector;
        $this->databaseFactory  = $databaseFactory;
        $this->databaseExternal = $databaseExternal;
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

        // External database checked

        $external_db_version = '';

        if ($this->getConfigLoader()->get('external_enable')) {
            $external_ping = new \External_Ping();

            if ($external_ping->isResponsive()) {
                $external_db = ucfirst($this->getConfigLoader()->get('external_type'));
                $external_db_light = 'success';
                $external_db_status = 'Online';
                $external_db_version = $external_ping->getVersion();
            } else {
                $external_db = ucfirst($this->getConfigLoader()->get('external_type'));
                $external_db_light = 'alert';
                $external_db_status = 'Offline';
            }
        } else {
            $external_db = ucfirst($this->getConfigLoader()->get('external_type'));
            $external_db_light = 'alert';
            $external_db_status = 'Disabled';
        }

        // Email status

        if ($this->getConfigLoader()->get('mail_enable') && $this->getConfigLoader()->get('mail_smtp_host')) {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 1, 'usec' => 0));

            if (@socket_connect($socket, $this->getConfigLoader()->get('mail_smtp_host'), $this->getConfigLoader()->get('mail_smtp_port'))) {
                $email_light = 'success';
                $email_status = 'Online';
            } else {
                $email_light = 'alert';
                $email_status = 'Offline';
            }

            socket_close($socket);
        } else {
            $email_light = 'alert';
            $email_status = 'Disabled';
        }

        // LDAP status

        $ldap_light = 'alert';
        $ldap_status = 'Disabled';

        $ldap_alt_light = 'alert';
        $ldap_alt_status = 'Disabled';

        if ($this->getConfigLoader()->get('ldap_enable')) {
            $tempsession = new \Session();

            // Check primary LDAP server bind

            if (trim($this->getConfigLoader()->get('ldap_host')) != "") {
                $ds = ldap_connect($this->getConfigLoader()->get('ldap_host'));
                ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
                ldap_set_option($ds, LDAP_OPT_TIMELIMIT, 1);
                $ldapbind = @ldap_bind($ds, $tempsession->ldap_escape($this->getConfigLoader()->get('ldap_user')), utf8_encode($this->getConfigLoader()->get('ldap_pass')));

                if ($ldapbind) {
                    $ldap_light = 'success';
                    $ldap_status = 'Online';
                } else {
                    $ldap_light = 'alert';
                    $ldap_status = 'Offline';
                }

                unset($ds, $ldapbind);
            }

            // Check secondary LDAP server bind

            if (trim($this->getConfigLoader()->get('ldap_host_alt')) != "") {
                $ds = ldap_connect($this->getConfigLoader()->get('ldap_host_alt'));
                ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($ds, LDAP_OPT_REFERRALS, 0);
                ldap_set_option($ds, LDAP_OPT_TIMELIMIT, 1);
                $ldapbind = @ldap_bind($ds, $tempsession->ldap_escape($this->getConfigLoader()->get('ldap_user_alt')), utf8_encode($this->getConfigLoader()->get('ldap_pass')));

                if ($ldapbind) {
                    $ldap_alt_light = 'success';
                    $ldap_alt_status = 'Online';
                } else {
                    $ldap_alt_light = 'alert';
                    $ldap_alt_status = 'Offline';
                }

                unset($ds, $ldapbind);
            }
        }

        // Check WWW

        $context = ProxyServer::getInstance()->getContext('http', true);
        $context['http']['timeout'] = 1;

        if (@file_get_contents('https://schoolbox.com.au/schoolboxinstaller/status.html', null, stream_context_create($context))) {
            $www_light = 'success';
            $www_status = 'Available';
        } else {
            $www_light = 'alert';
            $www_status = 'Inaccessible';
        }

        // Get logs

        $logs = [];
        $logsRecent = LogMapper::getInstance()->findRecent();

        foreach ($logsRecent as $log) {
            $action = '';

            switch ($log['login']) {
                case '1':
                    $action = 'Logged in';
                    break;
                case '0':
                    $action = 'Failed login';
                    break;
                case 'modify':
                    $action = 'Modified ' . $log['ip'];
                    break;
                case 'move':
                    $action = 'Moved ' . $log['ip'];
                    break;
                case 'access':
                    $action = 'Accessed ' . $log['ip'];
                    break;
                case 'create':
                    $action = 'Added ' . $log['ip'];
                    break;
                case 'delete':
                    $action = 'Deleted ' . $log['ip'];
                    break;

                default:  //If unknown, skip output
                    continue 2;
            }

            if ($action != '') {
                $date = new Alaress_Date_Format($log['occurred'], Alaress_Date_Format::DATABASE_FORMAT);

                $logs[] = [
                    'date' => $date->getShort(),
                    'action' => $action,
                    'name' => "{$log['firstname']} {$log['lastname']}"
                ];
            }
        }

        // Get the navigation

        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminHome/index.twig',
                'data' => array(
                    'pageCurrent' => $this->router->generate('adminHome.getIndex'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminHome.getIndex')),
                    'usageInGraph' => $usageInGraph,
                    'disk_used' => $disk_used,
            //                    'disk_used' => Alaress_Byte_Format::bytesToString($disk_total - $disk_free),
                    'disk_total' => Alaress_Byte_Format::bytesToString($disk_total),
                    'disk_percentage' => Alaress_Byte_Format::bytesToString($disk_percentage),
                    'memory_used' => Alaress_Byte_Format::bytesToString($memory_used),
                    'memory_total' => Alaress_Byte_Format::bytesToString($memory_total),
                    'memory_percentage' => $memory_percentage,
                    'memory_gradient' => $memory_gradient,
                    'external_db' => $external_db,
                    'external_db_version' => $external_db_version,
                    'external_db_light' => $external_db_light,
                    'external_db_status' => $external_db_status,
                    'email_light' => $email_light,
                    'email_status' => $email_status,
                    'ldap_light' => $ldap_light,
                    'ldap_status' => $ldap_status,
                    'ldap_alt_light' => $ldap_alt_light,
                    'ldap_alt_status' => $ldap_alt_status,
                    'www_light' => $www_light,
                    'www_status' => $www_status,
                    'logs' => $logs,
                    'kamiEnabled' => $this->kamiConnector->isEnabled(),
                    'kamiOnline' => $this->kamiConnector->isOnline(),
                    'backupsEnabled' => $this->getDisasterRecoveryBackupsEnabled(),
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

        $this->getResponse()->content->set(
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

        $this->getResponse()->content->set(
            [
                'template' => '@adminHome/disaster-recovery.twig',
                'data'     => array_merge(
                    [
                        'pageCurrent' => $this->router->generate('adminHome.getIndex'),
                        'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminHome.getIndex')),
                        'enabled'     => $isEnabled,
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
        } catch (\UnexpectedValueException $e) {
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
}
