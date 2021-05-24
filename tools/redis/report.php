#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Monolog\Logger;
use Schoolbox\Application\CliTool;

require __DIR__ . '/../../conf/core/require.php';

/**
 * Scans all redis data (non blocking) and generates a report of:
 * * key type count
 * * key type TTLs
 * * key type data usage
 */
$app = new class extends CliTool\App {

    // Recognised key patterns
    private const KEY_PATTERNS = [
        '^msg:' . INSTANCE_ID . ':m:.+',
        '^pad:g\.',
        '^msg:' . INSTANCE_ID . ':q:\D.+',
        "^[0-9a-f]{40}$",    // 2fdbf72862ff72dece37045211a43fd285c03286 SHA1 remember me?
        '^msg:' . INSTANCE_ID . ':s:.+',
        '^token2author:',
        '^author2sessions:',
        '^mapper2author:',
        '^group2sessions:g\.',
        '^scrub.html:',
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
        "^External_.+",
    ];


    /** @inheritDoc */
    public function run(): void
    {
        $redis = \Alaress\Redis::getClientInstance();
        if (!$redis) {
            $this->getLogger()->error("Unable to fetch redis connection");
            exit(CliTool\Kernel::EXIT_STATUS_APP_ERROR);
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
                        case -2;
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
                    'Key Pattern'    => $pattern,
                    'Payload'        => $payload,
                    'Total Count'    => count($matches),
                    'No TTL Count'   => $countNoTtl,
                    'With TTL Count' => $countHasTtl,
                    'Min TTL'        => $minTtl ?? '-',
                    'Max TTL'        => $maxTtl ?? '-',
                    'Average TTL'    => $countHasTtl === 0 ? '-' : (int)($sumTtl / $countHasTtl),
                    'Data Size'      => $sumLength,
                ];
            }
        }

        usort($aggregated, static function (array $a, array $b): int {
            return $b['Data Size'] <=> $a['Data Size'];
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
            foreach ($row as $columnTitle => $columnValue) {
                $columnWidths[$columnTitle] = max(strlen((string)$columnValue), $columnWidths[$columnTitle] ?? 0, strlen((string)$columnTitle));
            }
        }

        /** @var string[][] $table */
        $table = [];

        // Column headers
        foreach (array_keys($aggregated[0]) as $columnTitle) {
            $width      = $columnWidths[$columnTitle];
            $table[0][] = str_pad('', $width, '*');
            $table[1][] = str_pad($columnTitle, $width, ' ', STR_PAD_LEFT);
            $table[2][] = str_pad('', $width, '-');
        }

        // Column Data
        $nextRow = count($table);
        foreach ($aggregated as $rowId => $row) {
            if ($rowId === count($aggregated) - 1) {
                break;
            }
            foreach ($row as $columnTitle => $columnValue) {
                $width                      = $columnWidths[$columnTitle];
                $table[$nextRow + $rowId][] = str_pad((string)$columnValue, $width, ' ', STR_PAD_LEFT);
            }
        }

        // Column Footer
        $nextRow = count($table);
        foreach (array_keys($aggregated[0]) as $columnTitle) {
            $width       = $columnWidths[$columnTitle];
            $columnValue = $aggregated[count($aggregated) - 1][$columnTitle];

            $table[$nextRow][]     = str_pad('', $width, '-');
            $table[$nextRow + 1][] = str_pad((string)$columnValue, $width, ' ', STR_PAD_LEFT);
            $table[$nextRow + 2][] = str_pad('', $width, '_');
        }

        foreach ($table as $idx => $row) {
            $table[$idx] = "|" . implode('|', $row) . '|';
        }

        fwrite($this->getOutStream(), "\n" . implode("\n", $table) . "\n");
    }
};

(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Logger::NOTICE]))->runApp();
