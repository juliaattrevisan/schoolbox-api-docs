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

    /**
     * Report only on a single Redis instance
     * (must be in `self::REDIS_INSTANCES`)
     */
    private const OPT_REDIS_INSTANCE = 'redis-instance:';

    /**
     * Group results from all instances together?
     */
    private const OPT_GROUP_RESULTS = 'group-results';

    /**
     * Output JSON?
     */
    private const OPT_JSON = 'json';

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
     * Const for Redis keys without an instance ID.
     *
     * This should only include keys for sessions and other systems.
     */
    private const NULL_INSTANCE_ID = 'null';

    /**
     * Const for the instance ID to render results in, if results are grouped.
     */
    private const GROUPED_INSTANCE_ID = 'INSTANCE_ID';

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
        return [
            self::OPT_LONG_HELP,
            self::OPT_SORT_BY_KEY_PATTERN,
            self::OPT_REDIS_INSTANCE,
            self::OPT_GROUP_RESULTS,
            self::OPT_JSON,
        ];
    }

    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            echo <<<HELP
            Synopsis:
                Report upon Redis usage.

            Usage:
                ./report.php [OPTIONS]

            Options:
                --sort-by-key-pattern:
                    If provided, sort the report alphabetically by key pattern:
                    otherwise, sort by the data size (largest first).
                --redis-instance=[storage,cache]:
                    If provided, report only on the given Redis instance:
                    * storage: the persistent storage Redis instance
                    * cache: the ephemeral cache Redis instance
                --group-results:
                    If provided, group results for all Schoolbox instances together;
                    otherwise, provide separate results for each Schoolbox instance.
                --json:
                    If provided, output the results as JSON; otherwise, as human-readable text.
                    When outputting as JSON, the results are keyed by format
                    `\$redisInstance.\$schoolboxInstance`, and do not include totals
                    (when working with machine-readable formats, calculate totals yourself).

                -h, --help:
                    Print this help text.


            HELP;
            exit;
        }

        // Which Redis instances to report on?
        $redisInstance = $this->getOpt(self::OPT_REDIS_INSTANCE);
        if ($redisInstance) {
            if (!in_array($redisInstance, self::REDIS_INSTANCES)) {
                $this->getLogger()->error(
                    sprintf(
                        "Redis instance does not exist: must be one of [%s]",
                        implode(self::REDIS_INSTANCES),
                    )
                );
                exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
            }
            $redisInstances = [$redisInstance];
        } else {
            $redisInstances = self::REDIS_INSTANCES;
        }

        // Output JSON?
        $jsonOutput = $this->getOpt(self::OPT_JSON);

        // Are results grouped across all instances,
        // or separated by instance ID?
        $groupResults = $this->getOpt(self::OPT_GROUP_RESULTS);
        $aggregated   = [];
        foreach ($redisInstances as $redisInstanceName) {
            $redis = Redis::getClientInstance($redisInstanceName);
            if (!$redis) {
                $this->getLogger()->error("Unable to fetch redis connection: $redisInstanceName");
                exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
            }

            $this->getLogger()->notice("Scanning redis connection: $redisInstanceName");
            $rawData                   = $this->scanKeys($redis, $redisInstanceName, $groupResults);
            foreach ($rawData as $instanceId => $rawDataByInstanceId) {
                $aggregated[$redisInstanceName][$instanceId] = $this->aggregateData($rawDataByInstanceId, $jsonOutput);
            }
        }

        // Sort aggregated data by instance ID
        foreach ($redisInstances as $redisInstanceName) {
            uksort($aggregated[$redisInstanceName], $this->sortByInstanceId(...));
        }

        // And finally output
        if ($jsonOutput) {
            echo json_encode($aggregated);
        } else {
            foreach ($redisInstances as $redisInstanceName) {
                foreach ($aggregated[$redisInstanceName] as $instanceId => $aggregatedByInstanceId) {
                    $this->renderResults($aggregatedByInstanceId, $redisInstanceName, $instanceId, $groupResults);
                }
            }
        }
    }


    /**
     * Scans all keys in redis, groups by key pattern, and gets each key's TTL and data size
     *
     * @param Predis\ClientInterface   $redis
     * @param string&Redis::INSTANCE_* $redisInstanceName The name of the Redis instance to scan: used to filter the
     *                                                    list of keys to process.
     * @param bool $groupResults                          true: group results for all Schoolbox instances,
     *                                                    false: segregate by instance.
     * @return array
     */
    private function scanKeys(Predis\ClientInterface $redis, string $redisInstanceName, bool $groupResults): array
    {
        $rawData = [];
        $cursor  = 0;
        $count   = 1;
        while ($cursor !== '0') {
            [$cursor, $elements] = $redis->scan($cursor, ['MATCH' => '*', 'COUNT' => 10000]);
            foreach ($elements as $key) {
                // Log every 1000 at a higher level to show progression at less verbosity
                $level = $count % 1000 === 0 ? Logger::NOTICE : Logger::DEBUG;
                $this->getLogger()->log($level, "$count) $key");
                $processed = $this->processKey($redis, $key, $redisInstanceName, $groupResults);
                if (isset($processed)) {
                    [$keyPattern, $instanceId, $payload, $ttl, $length, $status] = $processed;
                    if ($groupResults) {
                        $instanceId = self::GROUPED_INSTANCE_ID;
                    }
                    $rawData[$instanceId][$keyPattern][$payload][] = [
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
     * @param Predis\ClientInterface   $redis
     * @param string                   $key                The key to process.
     * @param string&Redis::INSTANCE_* $redisInstanceName  The name of the Redis instance to scan: used to filter the
     *                                                     list of keys to process.
     * @param bool                     $groupResults       true: group results for all Schoolbox instances,
     *                                                     false: segregate by instance.
     * @return array{string, string, string, int, int, string}|null
     *         [key pattern, instance ID, payload type, ttl, length, status],
     *         or null if this key is to be ignored completely in the report.
     *         (This happens if this key is not in `$instanceName`'s keys, but
     *          is in an alias' keys.)
     *         The key pattern will always have the instance ID within it.
     *         If grouping results: the instance ID will always be
     *         `self::GROUPED_INSTANCE_ID`.
     */
    private function processKey(
        Predis\ClientInterface $redis,
        string $key,
        string $redisInstanceName,
        bool $groupResults,
    ): ?array {
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
        $instanceId = self::NULL_INSTANCE_ID;
        foreach (Registry::KEY_PATTERNS[$redisInstanceName] as $pattern) {
            if (preg_match("/$pattern/", $key, $matches)) {
                $keyPattern = $pattern;
                $status     = 'ok';
                $instanceId = $matches['instanceId'] ?? self::NULL_INSTANCE_ID;
                break;
            }
        }

        // Not found?
        // Check the other instance's key patterns: if it's found in another
        // instance, report on it
        if (!isset($keyPattern)) {
            foreach (Registry::KEY_PATTERNS as $keyPatternInstanceName => $patterns) {
                if ($keyPatternInstanceName === $redisInstanceName) {
                    continue;
                }
                foreach ($patterns as $pattern) {
                    if (preg_match("/$pattern/", $key, $matches)) {
                        // Check each alias
                        // @phpstan-ignore-next-line: this is currently empty, but may not always be
                        foreach (self::REDIS_INSTANCE_ALIASES as $alias) {
                            if (in_array($redisInstanceName, $alias) && in_array($keyPatternInstanceName, $alias)) {
                                // This key pattern is referred to in an alias:
                                // ignore it completely
                                $this->getLogger()->debug(
                                    sprintf(
                                        'Aliased key: %s found in %s (expected in alias %s)',
                                        $key,
                                        $redisInstanceName,
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
                                $redisInstanceName,
                                $keyPatternInstanceName,
                            )
                        );
                        $keyPattern = $pattern;
                        $status     = 'misp';
                        $instanceId = $matches['instanceId'] ?? self::NULL_INSTANCE_ID;
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

        // Replace the instance ID regexp match in the key pattern
        if ($instanceId) {
            if ($groupResults) {
                $instanceId = self::GROUPED_INSTANCE_ID;
            }
            $keyPattern = str_replace(Registry::INSTANCE_ID_MATCH, $instanceId, $keyPattern);
        }
        return [$keyPattern, $instanceId, $type->getPayload(), $ttl, $length, $status];
    }


    /**
     * Aggregates raw data
     *
     * @param array $rawData
     * @param bool  $jsonOutput If true, outputting JSON and thus do not include
     *                          totals.
     * @return array
     */
    private function aggregateData(array $rawData, bool $jsonOutput): array
    {
        // Don't replace null values with - characters in JSON report
        $nullReplacement = $jsonOutput ? null : '-';
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
                    self::COLUMN_MIN_TTL        => $minTtl ?? $nullReplacement,
                    self::COLUMN_MAX_TTL        => $maxTtl ?? $nullReplacement,
                    self::COLUMN_AVERAGE_TTL    => $countHasTtl === 0
                        ? $nullReplacement
                        : (int)($sumTtl / $countHasTtl),
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


        // Column totals: exclude for JSON output
        $countRows = count($aggregated);
        if (!$jsonOutput) {
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
     * @param string $redisInstanceName The name of the Redis instance
     * @param string $instanceId        The ID of the Schoolbox instance, or
     *                                  `self::NULL_INSTANCE_ID` if this data is
     *                                  not associated with an instance.
     * @param bool   $groupResults      true: group results for all Schoolbox
     *                                  instances, false: segregate by instance.
     */
    private function renderResults(
        array $aggregated,
        string $redisInstanceName,
        string $instanceId,
        bool $groupResults,
    ): void {
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

        if ($groupResults) {
            $instanceIdOutput = '';
        } else {
            $instanceIdOutput = $instanceId === self::NULL_INSTANCE_ID
                ? ', no Schoolbox instance'
                : sprintf(', Schoolbox instance "%s"', $instanceId);
        }
        fwrite(
            $this->getOutStream(),
            sprintf(
                (empty($table)
                    ? "\nNo usage for Redis instance \"%s\"%s found."
                    : "\nUsage for instance \"%s\"%s:"),
                $redisInstanceName,
                $instanceIdOutput,
            )
        );
        fwrite($this->getOutStream(), "\n" . implode("\n", $table) . "\n");
    }

    /**
     * Sort method for Schoolbox instance IDs: sort alphabetically, but ensure
     * the null instance ID is at the end of the list.
     *
     * @param string $instanceIdA
     * @param string $instanceIdB
     * @return int
     */
    private function sortByInstanceId(string $instanceIdA, string $instanceIdB): int
    {
        if ($instanceIdA === $instanceIdB) {
            return 0;
        }
        if ($instanceIdA === self::NULL_INSTANCE_ID) {
            return 1;
        }
        if ($instanceIdB === self::NULL_INSTANCE_ID) {
            return -1;
        }
        return strcmp($instanceIdA, $instanceIdB);
    }
}
