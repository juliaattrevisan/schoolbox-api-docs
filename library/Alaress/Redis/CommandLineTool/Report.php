<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Redis\CommandLineTool;

use Alaress\Redis;
use Monolog\Logger;
use Predis;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

/**
 * Scans all redis data (non blocking) and generates a report of:
 * * key type count
 * * key type TTLs
 * * key type data usage
 */
class Report extends CliTool\App
{
    /**
     * Print help
     */
    private const OPT_SHORT_HELP = 'h';

    /**
     * Print help
     */
    private const OPT_LONG_HELP  = 'help';

    /**
     * sort by key pattern, instead of by size
     */
    private const OPT_SORT_BY_KEY_PATTERN = 'sort-by-key-pattern';

    private const COLUMN_KEY_PATTERN = 'keyPattern';
    private const COLUMN_PAYLOAD = 'payload';
    private const COLUMN_STATUS = 'status';
    private const COLUMN_TOTAL_COUNT = 'totalCount';
    private const COLUMN_NO_TTL_COUNT = 'noTtlCount';
    private const COLUMN_WITH_TTL_COUNT = 'withTtlCount';
    private const COLUMN_MIN_TTL = 'minTtl';
    private const COLUMN_MAX_TTL = 'maxTtl';
    private const COLUMN_AVERAGE_TTL = 'averageTtl';
    private const COLUMN_DATA_SIZE = 'dataSize';

    /**
     * Column alignments
     */
    private const ALIGNMENTS = [
        self::COLUMN_KEY_PATTERN    => STR_PAD_RIGHT,
        self::COLUMN_PAYLOAD        => STR_PAD_RIGHT,
        self::COLUMN_STATUS         => STR_PAD_RIGHT,
        self::COLUMN_TOTAL_COUNT    => STR_PAD_LEFT,
        self::COLUMN_NO_TTL_COUNT   => STR_PAD_LEFT,
        self::COLUMN_WITH_TTL_COUNT => STR_PAD_LEFT,
        self::COLUMN_MIN_TTL        => STR_PAD_LEFT,
        self::COLUMN_MAX_TTL        => STR_PAD_LEFT,
        self::COLUMN_AVERAGE_TTL    => STR_PAD_LEFT,
        self::COLUMN_DATA_SIZE      => STR_PAD_LEFT,
    ];

    /**
     * Column labels
     */
    private const LABELS = [
        self::COLUMN_KEY_PATTERN    => 'Key Pattern',
        self::COLUMN_PAYLOAD        => 'Payload',
        self::COLUMN_STATUS         => 'Status',
        self::COLUMN_TOTAL_COUNT    => 'Total Count',
        self::COLUMN_NO_TTL_COUNT   => 'No TTL Count',
        self::COLUMN_WITH_TTL_COUNT => 'With TTL Count',
        self::COLUMN_MIN_TTL        => 'Min TTL',
        self::COLUMN_MAX_TTL        => 'Max TTL',
        self::COLUMN_AVERAGE_TTL    => 'Average TTL',
        self::COLUMN_DATA_SIZE      => 'Data Size',
    ];

    /**
     * Which Redis instances exist?
     *
     * (Note that INSTANCE_CACHE and INSTANCE_QUEUE_PUBLISH are currently the
     *  same)
     */
    private const REDIS_INSTANCES = [
        Redis::INSTANCE_CACHE,
        Redis::INSTANCE_STORAGE,
//        Redis::INSTANCE_SIGNAL_QUEUE,
    ];

    /**
     * Redis instances which are aliases of one another.
     *
     * Keys which are found in an instance but aren't in `self::KEY_PATTERNS`
     * for that instance are ignored if they are in one of the aliases key
     * patterns.
     */
    private const REDIS_INSTANCE_ALIASES = [
//        [
//            Redis::INSTANCE_CACHE,
//            Redis::INSTANCE_SIGNAL_QUEUE,
//        ]
    ];

    /**
     * Recognised key patterns
     */
    private const KEY_PATTERNS = [
        // Cache-only patterns go here
        Redis::INSTANCE_CACHE => [
            // Old and new scrub
            '^scrub.html:',
            '^scrub.htmlAbsoluteUri:',
            '^fc:' . INSTANCE_ID . ':scrub.html:',
            '^fc:' . INSTANCE_ID . ':scrub.htmlAbsoluteUri:',

            // Old and new Verso API
            '^verso_api',
            '^fc:' . INSTANCE_ID . ':verso_api:',

            // Old and new external
            "^External_.+",
            '^fc:' . INSTANCE_ID . ':External_',

            // vCal cache: not partitioned by instance ID
            // (it may need to be one day)
            "^vcal.[0-9a-f]{64}",

            // Locks
            '^lock:' . INSTANCE_ID . ':',

            // Atlas API?
            '^atlasApi:' . INSTANCE_ID . '.',
        ],
        // Storage-only patterns go here
        Redis::INSTANCE_STORAGE => [
            // Sessions
            '^PHPREDIS_SESSION:',

            // Old and new remember me
            "^[0-9a-f]{40}$",
            "^remember:" . INSTANCE_ID . ":[0-9a-f]{40}$",

            // Message instance, detailing the content of a message
            // (an `\Alaress\Async\Notification\Instance\Instance` instance)
            '^msg:' . INSTANCE_ID . ':m:.+',

            // Message trigger, detailing when, how and why of a message
            // (a `\Schoolbox\Realtime\Schedule\Trigger\TriggerInterface`
            //  instance)
            '^msg:' . INSTANCE_ID . ':s:.+',

            // Set of message keys relating to a particular object
            '^msg:' . INSTANCE_ID . ':q:\D.+',

            // Set of message keys relating to a particular user
            '^msg:' . INSTANCE_ID . ':q:\d+:unread',
            '^msg:' . INSTANCE_ID . ':q:\d+:listed',
            '^msg:' . INSTANCE_ID . ':q:\d+:digest',
            // Not sure if the below are used
//            '^msg:' . INSTANCE_ID . ':q:\d+:push',
//            '^msg:' . INSTANCE_ID . ':q:\d+:alert',

            // Message route cache
            "^m:" . INSTANCE_ID . ":routes",

            // Scheduled triggers?
            // (Don't know if this is even used, or where it should be if it is)
            "^schedule:" . INSTANCE_ID . ":index",
            "^schedule:" . INSTANCE_ID . ":[0-9a-f]{40}",

            // Access tokens for LTI assessments
            '^accessToken:' . INSTANCE_ID . ':.+',

            // Aura route and module caches
            'routeCache:' . INSTANCE_ID,
            'moduleCache:' . INSTANCE_ID,

            // Etherpad patterns
            '^pad:g\.',
            '^token2author:',
            '^author2sessions:',
            '^mapper2author:',
            '^group2sessions:g\.',
            '^sessionstorage:',
            '^globalAuthor:',
            '^ueberDB:keys:',
            '^pad2readonly:g\.',
            '^readonly2pad:r\.',
            '^group:g\.',
            '^session:s\.',
            "^groups$",
        ],
        // Queue patterns go here
        // (Note that queue is the same instance as cache, so it's not an issue
        //  if queue patterns are found in cache and v.v.)
//        Redis::INSTANCE_SIGNAL_QUEUE => [
//            // Signal queues
//            'scheduled.' . INSTANCE_ID . '.*',
//            'routed.' . INSTANCE_ID . '.*',
//        ],
    ];

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP, self::OPT_SORT_BY_KEY_PATTERN,];
    }

    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            echo <<<HELP
            Synopsis:
                Report upon Redis usage.

            Usage:
                ./report.php [--sort-by-key-pattern] [-h] [--help]

            Options:
                --sort-by-key-pattern:
                    If provided, sort the report alphabetically by key pattern:
                    otherwise, sort by the data size (largest first).
                -h, --help:
                    Print this help text.


            HELP;
            exit;
        }

        $aggregated = [];
        foreach (self::REDIS_INSTANCES as $instanceName) {
            $redis = Redis::getClientInstance($instanceName);
            if (!$redis) {
                $this->getLogger()->error("Unable to fetch redis connection: $instanceName");
                exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
            }

            $this->getLogger()->notice("Scanning redis connection: $instanceName");
            $rawData                   = $this->scanKeys($redis, $instanceName);
            $aggregated[$instanceName] = $this->aggregateData($rawData);
        }
        foreach (self::REDIS_INSTANCES as $instanceName) {
            $this->renderResults($aggregated[$instanceName], $instanceName);
        }
    }


    /**
     * Scans all keys in redis, groups by key pattern, and gets each key's TTL and data size
     *
     * @param Predis\ClientInterface $redis
     * @param string                 $instanceName The name of the instance to
     *                                             scan: used to filter the list
     *                                             of keys to process
     * @return array
     */
    private function scanKeys(Predis\ClientInterface $redis, string $instanceName): array
    {
        $rawData = [];
        $cursor  = null;
        $count   = 1;
        while ($cursor !== '0') {
            [$cursor, $elements] = $redis->scan($cursor, ['MATCH' => '*', 'COUNT' => 10000]);
            foreach ($elements as $key) {
                // Log every 1000 at a higher level to show progression at less verbosity
                $level = $count % 1000 === 0 ? Logger::NOTICE : Logger::DEBUG;
                $this->getLogger()->log($level, "$count) $key");
                $processed = $this->processKey($redis, $key, $instanceName);
                if (isset($processed)) {
                    [$keyPattern, $payload, $ttl, $length, $status] = $processed;
                    $rawData[$keyPattern][$payload][] = [
                        'ttl'    => $ttl,
                        'length' => $length,
                        'status' => $status,
                    ];
                    $count++;
                }
            }
        }
        return $rawData;
    }


    /**
     * Get TTL + DataSize for a single redis key, aggregate by key pattern
     *
     * @param Predis\ClientInterface $redis
     * @param string                 $key
     * @param string                 $instanceName The name of the instance to
     *                                             scan: used to filter the list
     *                                             of keys to process
     * @return array{string, string, int, int, string}|null
     *         [key pattern, payload type, ttl, length, status], or null if this
     *         key is to be ignored completely in the report.
     *         (This happens if this key is not in `$instanceName`'s keys, but
     *          is in an alias' keys.)
     */
    private function processKey(Predis\ClientInterface $redis, string $key, string $instanceName): ?array
    {
        /** @var Predis\Response\Status $type */
        $type = $redis->type($key);
        switch ($type->getPayload()) {
            case 'string':
                $length = $redis->strlen($key);
                break;
            case 'set':
                $length = array_sum(array_map('strlen', $redis->smembers($key)));
                break;
            case 'none':
                $length = 0;
                break;
            case 'zset':
                $length = array_sum(array_map('strlen', $redis->zrange($key, 0, -1)));
                break;
            default:
                $this->getLogger()->warning("Unrecognised payload: '{$type->getPayload()}'");
                $length = 0;
        }
        $ttl = $redis->ttl($key);
        $this->getLogger()->info("payload: {$type->getPayload()}, ttl: $ttl, length: $length");

        // Search this instance's key patterns
        $keyPattern = null;
        $status     = false;
        foreach (self::KEY_PATTERNS[$instanceName] as $pattern) {
            if (preg_match("/$pattern/", $key)) {
                $keyPattern = $pattern;
                $status     = 'ok';
                break;
            }
        }

        // Not found?
        // Check the other instance's key patterns: if it's found in another
        // instance, report on it
        if (!isset($keyPattern)) {
            foreach (self::KEY_PATTERNS as $keyPatternInstanceName => $patterns) {
                if ($keyPatternInstanceName === $instanceName) {
                    continue;
                }
                foreach ($patterns as $pattern) {
                    if (preg_match("/$pattern/", $key)) {
                        // Check each alias
                        // @phpstan-ignore-next-line: this is currently empty, but may not always be
                        foreach (self::REDIS_INSTANCE_ALIASES as $alias) {
                            if (in_array($instanceName, $alias) && in_array($keyPatternInstanceName, $alias)) {
                                // This key pattern is referred to in an alias:
                                // ignore it completely
                                $this->getLogger()->debug(
                                    sprintf(
                                        'Aliased key: %s found in %s (expected in alias %s)',
                                        $key,
                                        $instanceName,
                                        $keyPatternInstanceName,
                                    )
                                );
                                return null;
                            }
                        }

                        $this->getLogger()->warning(
                            sprintf(
                                'Misplaced key: %s found in %s (expected in %s)',
                                $key,
                                $instanceName,
                                $keyPatternInstanceName,
                            )
                        );
                        $keyPattern = $pattern;
                        $status     = 'misp';
                        break;
                    }
                }
            }
        }

        // Still not found? Report this key as unrecognised
        if (!isset($keyPattern)) {
            $keyPattern = 'unrecognised';
            $status     = 'unrec';
            $this->getLogger()->warning("Unrecognised key: $key");
        }
        return [$keyPattern, $type->getPayload(), $ttl, $length, $status];
    }


    /**
     * Aggregates raw data
     *
     * @param array $rawData
     * @return array
     */
    private function aggregateData(array $rawData): array
    {
        $aggregated = [];
        foreach ($rawData as $pattern => $patternData) {
            foreach ($patternData as $payload => $matches) {
                $countNoTtl = $countHasTtl = $sumTtl = $sumLength = 0;
                $minTtl     = $maxTtl = null;
                $status     = null;
                foreach ($matches as $row) {
                    $sumLength += $row['length'];
                    $ttl       = $row['ttl'];
                    $status    = $row['status'];
                    switch ($ttl) {
                        case -2:
                            break;
                        case -1:
                            $countNoTtl++;
                            break;
                        default:
                            $countHasTtl++;
                            $minTtl = $minTtl === null ? $ttl : min($ttl, $minTtl);
                            $maxTtl = $maxTtl === null ? $ttl : max($ttl, $maxTtl);
                            $sumTtl += $ttl;
                    }
                }

                $aggregated[] = [
                    self::COLUMN_KEY_PATTERN    => $pattern,
                    self::COLUMN_PAYLOAD        => $payload,
                    self::COLUMN_STATUS         => $status,
                    self::COLUMN_TOTAL_COUNT    => count($matches),
                    self::COLUMN_NO_TTL_COUNT   => $countNoTtl,
                    self::COLUMN_WITH_TTL_COUNT => $countHasTtl,
                    self::COLUMN_MIN_TTL        => $minTtl ?? '-',
                    self::COLUMN_MAX_TTL        => $maxTtl ?? '-',
                    self::COLUMN_AVERAGE_TTL    => $countHasTtl === 0 ? '-' : (int)($sumTtl / $countHasTtl),
                    self::COLUMN_DATA_SIZE      => $sumLength,
                ];
            }
        }


        $sortByKeyPattern = $this->getOpt(self::OPT_SORT_BY_KEY_PATTERN);
        usort($aggregated, static function (array $a, array $b) use ($sortByKeyPattern): int {
            return $sortByKeyPattern
                ? strnatcasecmp($a[self::COLUMN_KEY_PATTERN], $b[self::COLUMN_KEY_PATTERN])
                : ($b[self::COLUMN_DATA_SIZE] <=> $a[self::COLUMN_DATA_SIZE]);
        });


        // Column totals
        $countRows = count($aggregated);
        foreach (array_keys($aggregated[0] ?? []) as $idx => $columnTitle) {
            if ($idx === 0) {
                $columnValue = "Totals";
            } elseif (stripos($columnTitle, 'count') !== false || stripos($columnTitle, 'size')) {
                $columnValue = array_sum(array_column($aggregated, $columnTitle));
            } else {
                $columnValue = '';
            }
            $aggregated[$countRows][$columnTitle] = $columnValue;
        }

        // Format numbers
        foreach ($aggregated as $rowId => $row) {
            foreach ($row as $colId => $val) {
                if (is_numeric($val)) {
                    $aggregated[$rowId][$colId] = number_format($val);
                }
            }
        }

        return $aggregated;
    }


    /**
     * Renders aggregated raw data into a suitable display format
     *
     * @param array  $aggregated
     * @param string $instanceName
     */
    private function renderResults(array $aggregated, string $instanceName): void
    {
        // Calculate column widths from widest column header or value
        $columnWidths = [];
        foreach ($aggregated as $row) {
            foreach ($row as $columnKey => $columnValue) {
                $columnTitle = self::LABELS[$columnKey];
                $columnWidths[$columnKey] = max(
                    strlen((string)$columnValue),
                    $columnWidths[$columnKey] ?? 0,
                    strlen($columnTitle),
                );
            }
        }

        /** @var string[][] $table */
        $table = [];

        // Column headers
        foreach (array_keys($aggregated[0] ?? []) as $columnKey) {
            $columnTitle = self::LABELS[$columnKey];
            $width       = $columnWidths[$columnKey];
            $table[0][]  = str_pad('', $width, '*');
            $table[1][]  = str_pad($columnTitle, $width, ' ', STR_PAD_BOTH);
            $table[2][]  = str_pad('', $width, '-');
        }

        // Column Data
        $nextRow = count($table);
        foreach ($aggregated as $rowId => $row) {
            if ($rowId === count($aggregated) - 1) {
                break;
            }
            foreach ($row as $columnKey => $columnValue) {
                $width                      = $columnWidths[$columnKey];
                $table[$nextRow + $rowId][] = str_pad((string)$columnValue, $width, ' ', self::ALIGNMENTS[$columnKey]);
            }
        }

        // Column Footer
        $nextRow = count($table);
        foreach (array_keys($aggregated[0] ?? []) as $columnKey) {
            $width       = $columnWidths[$columnKey];
            $columnValue = $aggregated[count($aggregated) - 1][$columnKey];

            $table[$nextRow][]     = str_pad('', $width, '-');
            $table[$nextRow + 1][] = str_pad((string)$columnValue, $width, ' ', self::ALIGNMENTS[$columnKey]);
            $table[$nextRow + 2][] = str_pad('', $width, '_');
        }

        foreach ($table as $idx => $row) {
            $table[$idx] = "|" . implode('|', $row) . '|';
        }

        fwrite(
            $this->getOutStream(),
            sprintf(
                (empty($table)
                    ? "\nNo usage for instance \"%s\" found."
                    : "\nUsage for instance \"%s\":"),
                $instanceName,
            )
        );
        fwrite($this->getOutStream(), "\n" . implode("\n", $table) . "\n");
    }
}
