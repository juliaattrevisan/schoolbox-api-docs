<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GC\Check;

use Alaress\Database\Connector\IConnector;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Model;

/**
 * Check for whether this hash exists somewhere within a storage file.
 *
 * Only files of type `text/html` will be checked.
 */
class Storage implements Check
{
    /**
     * Regular expression used to find hash values in storage files.
     *
     * It's assumed that any 40-character hex sequence is a hash.
     */
    private const string HASH_REGEXP = '/[0-9a-f]{40}/i';

    /**
     * Hashes which have been determined to exist in Schoolbox data.
     *
     * @var array<string, true>|null key: the hash, value: always true if it exists
     */
    private ?array $hashIndex = null;

    /**
     * Columns which may contain storage files.
     */
    private const array STORAGE_FILE_COLUMNS = [
        'student_submission_file' => ['hash'],
    ];

    /**
     * Constructor
     *
     * @param IConnector      $db         Database connection
     * @param LoggerInterface $logger     Logger
     * @param string          $table      The table which stores storage records.
     * @param FilesystemProxy $filesystem The filesystem from which files are read.
     */
    public function __construct(
        private IConnector $db,
        private LoggerInterface $logger,
        private string $table,
        private FilesystemProxy $filesystem,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getStale(array $previous): array
    {
        $stale = $previous;
        if (empty($stale)) {
            $this->logger->info('No stale rows left to check: returning');
            return $stale;
        }

        if (isset($this->hashIndex)) {
            $this->logger->info('Reusing hash index from previous run');
        } else {
            $this->logger->info('Building hash index: this may take a while...');
            $time = microtime(true);
            $this->hashIndex = $this->buildHashIndex();
            $this->logger->info(sprintf('. hash index built in %0.3fs', microtime(true) - $time));
        }

        $staleCount = count($stale);
        $newStale   = array_filter(
            $stale,
            fn ($hash) => !isset($this->hashIndex[strtolower($hash)]),
        );
        $newStaleCount = count($newStale);
        $this->logger->info(
            sprintf(
                '. %d of %d non-stale hashes found: %d remaining',
                ($staleCount - $newStaleCount),
                $staleCount,
                $newStaleCount,
            )
        );
        return $newStale;
    }

    /**
     * Builds the index of hashes which are in use in the Schoolbox instance.
     *
     * @return array<string, true>
     */
    private function buildHashIndex(): array
    {
        $hashIndex = [];

        // Search through all HTML type storage files on the allowed columns
        $storageFiles = [];
        $quoter       = $this->db->getQuoter();
        foreach (self::STORAGE_FILE_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->logger->debug(
                    sprintf('Loading storage hashes for HTML files for column %s.%s...', $table, $column),
                );
                $query = $this->db->prepareQuery("
                    SELECT s.hash
                    FROM {$quoter->quoteIdentifier($table)} t
                    INNER JOIN {$quoter->quoteIdentifier($this->table)} s ON t.{$quoter->quoteIdentifier($column)} = s.hash
                    WHERE s.ext IN ('htm', 'html')
                ");
                $query->execute();
                $this->logger->debug(sprintf('. %d hashes found', $query->rowCount()));
                foreach ($query->fetchColumn() as $hash) {
                    $storageFiles[$hash] = true;
                }
            }
        }
        $this->logger->debug(sprintf('%d unique hashes found', count($storageFiles)));

        // Load storage file content
        foreach ($storageFiles as $hash => $_) {
            $columnHashes = [];
            try {
                $fileContent = $this->filesystem->read(new Model()->setHash($hash));
            } catch (FilesystemException $e) {
                // This file didn't exist after all: skip it
                $this->logger->warning(
                    sprintf('Storage file with hash "%s" could not be read: perhaps it does not exist', $hash),
                    ['exception' => $e],
                );
                continue;
            }
            if (preg_match_all(self::HASH_REGEXP, $fileContent, $matches)) {
                foreach ($matches[0] as $match) {
                    $columnHashes[strtolower($match)] = true;
                }
            }
            $hashIndex += $columnHashes;
        }
        $this->logger->debug(sprintf('.. %d hashes found', count($hashIndex)));
        return $hashIndex;


//        $tableColumnList = $this->buildTableColumnList();
//        foreach ($tableColumnList as $table => $columnsData) {
//            if (str_starts_with($table, '_')) {
//                $this->logger->debug(sprintf('Skipping deprecated table: %s', $table));
//                continue;
//            }
//
//            $countQuery = $this->db->executeQuery(
//                "SELECT COUNT(*) FROM {$this->db->getQuoter()->quoteIdentifier($table)}",
//            );
//            $count = (int)$countQuery->fetchColumn();
//            if ($count <= 0) {
//                $this->logger->debug(sprintf('Skipping empty table: %s...', $table));
//                continue;
//            }
//
//            // Extract data length from the first column:
//            // it will be the same for all columns
//            $dataLength = $columnsData[0]['extra']['search_data_length'] ?? null;
//            $this->logger->debug(
//                sprintf(
//                    'Building hash index for %d rows in table: %s (size: %s)...',
//                    $count,
//                    $table,
//                    isset($dataLength)
//                        ? Alaress_Byte_Format::bytesToString($dataLength, renderIec: true)
//                        : 'unknown',
//                )
//            );
//            $columns = [];
//            foreach ($columnsData as $columnData) {
//                foreach ($columnData['columns'] as $column) {
//                    if (str_starts_with($column, '_')) {
//                        $this->logger->debug(sprintf('. skipping deprecated column: %s', $column));
//                        continue;
//                    }
//                    $columns[] = $column;
//                }
//            }
//
//            if (empty($columns)) {
//                $this->logger->debug(sprintf('. skipping table with no text columns: %s...', $table));
//                continue;
//            }
//
//            // Now, build the hash index for all columns
//            foreach ($columns as $column) {
//                $this->logger->debug(sprintf('. building hash index for column: %s...', $column));
//                $query = $this->db->prepareQuery("
//                    SET STATEMENT max_statement_time = 0 FOR
//                    SELECT {$this->db->getQuoter()->quoteIdentifier($column)} c
//                    FROM {$this->db->getQuoter()->quoteIdentifier($table)}
//                    WHERE {$this->db->getQuoter()->quoteIdentifier($column)} IS NOT NULL
//                ");
//
//                $query->execute();
//                $columnHashes = [];
//                foreach ($query as $row) {
//                    $value = $row['c'];
//                    if (preg_match_all(self::HASH_REGEXP, $value, $matches)) {
//                        foreach ($matches[0] as $match) {
//                            $columnHashes[strtolower($match)] = true;
//                        }
//                    }
//                }
//                $this->logger->debug(sprintf('.. %d hashes found', count($columnHashes)));
//                $hashIndex += $columnHashes;
//            }
//        }
//        return $hashIndex;
    }
}
