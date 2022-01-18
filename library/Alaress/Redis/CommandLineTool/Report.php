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
        self::COLUMN_TOTAL_COUNT    => 'Total Count',
        self::COLUMN_NO_TTL_COUNT   => 'No TTL Count',
        self::COLUMN_WITH_TTL_COUNT => 'With TTL Count',
        self::COLUMN_MIN_TTL        => 'Min TTL',
        self::COLUMN_MAX_TTL        => 'Max TTL',
        self::COLUMN_AVERAGE_TTL    => 'Average TTL',
        self::COLUMN_DATA_SIZE      => 'Data Size',
    ];

    /**
     * Recognised key patterns
     */
    private const KEY_PATTERNS = [
        '^msg:' . INSTANCE_ID . ':m:.+',
        '^pad:g\.',
        '^msg:' . INSTANCE_ID . ':q:\D.+',
        // Old remember me format
        "^[0-9a-f]{40}$",
        // New remember me format
        "^remember:" . INSTANCE_ID . ":[0-9a-f]{40}$",
        '^msg:' . INSTANCE_ID . ':s:.+',
        '^token2author:',
        '^author2sessions:',
        '^mapper2author:',
        '^group2sessions:g\.',
        // Old and new scrub
        '^scrub.html:',
        '^scrub.htmlAbsoluteUri:',
        '^fc:' . INSTANCE_ID . ':scrub.html:',
        '^fc:' . INSTANCE_ID . ':scrub.htmlAbsoluteUri:',
        // Old and new Verso API
        '^verso_api',
        '^fc:' . INSTANCE_ID . ':verso_api:',
        '^sessionstorage:',
        '^globalAuthor:',
        '^ueberDB:keys:',
        '^PHPREDIS_SESSION:',
        '^pad2readonly:g\.',
        '^readonly2pad:r\.',
        '^group:g\.',
        '^session:s\.',
        '^msg:' . INSTANCE_ID . ':q:\d+:unread',
        '^msg:' . INSTANCE_ID . ':q:\d+:listed',
        '^msg:' . INSTANCE_ID . ':q:\d+:digest',
        "^m:" . INSTANCE_ID . ":routes",
        "^schedule:" . INSTANCE_ID . ":index",
        "^schedule:" . INSTANCE_ID . ":[0-9a-f]{40}",
        "^groups$",
        // Old and new external
        "^External_.+",
        '^fc:' . INSTANCE_ID . ':External_',
        "^vcal.[0-9a-f]{64}",
        '^accessToken:' . INSTANCE_ID . ':.+',
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

        $redis = Redis::getClientInstance();
        if (!$redis) {
            $this->getLogger()->error("Unable to fetch redis connection");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $rawData    = $this->scanKeys($redis);
        $aggregated = $this->aggregateData($rawData);
        $this->renderResults($aggregated);
    }


    /**
     * Scans all keys in redis, groups by key pattern, and gets each key's TTL and data size
     *
     * @param Predis\ClientInterface $redis
     * @return array
     */
    private function scanKeys(Predis\ClientInterface $redis): array
    {
        $rawData = [];
        $cursor  = null;
        $count   = 1;
        while ($cursor !== '0') {
            [$cursor, $elements] = $redis->scan($cursor, ['MATCH' => '*', 'COUNT' => 10000]);
            foreach ($elements as $str_key) {
                // Log every 1000 at a higher level to show progression at less verbosity
                $level = $count % 1000 === 0 ? Logger::NOTICE : Logger::DEBUG;
                $this->getLogger()->log($level, "$count) $str_key");
                [$keyPattern, $payload, $ttl, $length] = $this->processKey($redis, $str_key);
                $rawData[$keyPattern][$payload][] = [
                    'ttl'    => $ttl,
                    'length' => $length,
                ];
                $count++;
            }
        }
        return $rawData;
    }


    /**
     * Get TTL + DataSize for a single redis key, aggregate by key pattern
     *
     * @param Predis\ClientInterface $redis
     * @param string                 $key
     * @return array
     */
    private function processKey(Predis\ClientInterface $redis, string $key): array
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
            default:
                $this->getLogger()->warning("Unrecognised payload: '{$type->getPayload()}'");
                $length = 0;
        }
        $ttl = $redis->ttl($key);
        $this->getLogger()->info("payload: {$type->getPayload()}, ttl: $ttl, length: $length");


        $keyPattern = 'unrecognised';
        foreach (self::KEY_PATTERNS as $pattern) {
            if (preg_match("/$pattern/", $key)) {
                $keyPattern = $pattern;
                break;
            }
        }
        if ($keyPattern === 'unrecognised') {
            $this->getLogger()->warning("Unrecognised key: $key");
        }
        return [$keyPattern, $type->getPayload(), $ttl, $length];
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
                foreach ($matches as $row) {
                    $sumLength += $row['length'];
                    $ttl       = $row['ttl'];
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
        foreach (array_keys($aggregated[0]) as $idx => $columnTitle) {
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
     * @param array $aggregated
     */
    private function renderResults(array $aggregated): void
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
        foreach (array_keys($aggregated[0]) as $columnKey) {
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
        foreach (array_keys($aggregated[0]) as $columnKey) {
            $width       = $columnWidths[$columnKey];
            $columnValue = $aggregated[count($aggregated) - 1][$columnKey];

            $table[$nextRow][]     = str_pad('', $width, '-');
            $table[$nextRow + 1][] = str_pad((string)$columnValue, $width, ' ', self::ALIGNMENTS[$columnKey]);
            $table[$nextRow + 2][] = str_pad('', $width, '_');
        }

        foreach ($table as $idx => $row) {
            $table[$idx] = "|" . implode('|', $row) . '|';
        }

        fwrite($this->getOutStream(), "\n" . implode("\n", $table) . "\n");
    }
}
