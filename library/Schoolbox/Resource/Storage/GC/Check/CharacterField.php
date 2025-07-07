<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GC\Check;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\IStatement;
use Psr\Log\LoggerInterface;

/**
 * Check for whether the storage hash is contained within a prescribed set of
 * `CHAR` or `VARCHAR` fields.
 */
class CharacterField implements Check
{
    /**
     * Prescribed set of `CHAR` or `VARCHAR` fields to check.
     * * key: table name
     * * value: list of column names
     *
     * @var array<string, array<string>>
     */
    private const FIELDS = [
        // Notes:
        // * any field which contains `hash` should maybe be a FK?
        // * any field which contains `url` or `link` should be checked?
        'blog_image_backup'            => ['hash'],
        'bookmarks'                    => ['url'],
        'component_config_definition'  => ['default_value'],
        'component_config_instance'    => ['value'],
        'link'                         => ['url'],
        'menu_side'                    => ['url'],
        'menu_top'                     => ['url'],
        'profile_button'               => ['url'],
        'rubric_descriptor'            => ['description'],
        'sis_user_profile'             => ['portrait_hash'],
        'socialstream_post'            => ['thumbnail', 'url'],
        'sportsfixtures_division'      => ['url'],
        'sportsfixtures_import_errors' => ['hash'],
        'sportsfixtures_sport'         => ['url'],
        'sportsfixtures_team'          => ['url'],
        'summative_report'             => ['footer'],
        'task'                         => ['link'],
        'tile'                         => ['link'],
        'ups_log'                      => ['hash'],
    ];

    /**
     * Constructor
     *
     * @param IConnector      $db     Database connection
     * @param LoggerInterface $logger Logger
     */
    public function __construct(private IConnector $db, private LoggerInterface $logger)
    {
    }

    /**
     * Filter out hashes from `$previous` which are referenced in any foreign
     * key relation.
     *
     * @param array<string> $previous
     * @return array<string>
     */
    public function getStale(array $previous): array
    {
        $stale = $previous;
        foreach (self::FIELDS as $table => $columns) {
            if (empty($stale)) {
                $this->logger->info('No stale rows left to check: returning');
                return $stale;
            }

            if (str_starts_with($table, '_')) {
                $this->logger->info(sprintf('Skipping deprecated table: %s', $table));
                continue;
            }

            $countQuery = $this->db->executeQuery(
                "SELECT COUNT(*) FROM {$this->db->getQuoter()->quoteIdentifier($table)}",
            );
            $count = (int)$countQuery->fetchColumn();
            $this->logger->info(sprintf('Searching %d rows in table: %s', $count, $table));
            if ($count <= 0) {
                continue;
            }
            foreach ($columns as $column) {
                if (str_starts_with($column, '_')) {
                    $this->logger->info(sprintf('. skipping deprecated column: %s.%s', $table, $column));
                    continue;
                }

                // Build query for this column, exclude non-stale
                $query      = $this->buildFindHashQuery($table, $column);
                $staleCount = count($stale);
                $newStale   = [];
                $this->logger->info(sprintf('. searching column %s.%s... (%d hashes)', $table, $column, $staleCount));
                foreach ($stale as $hash) {
                    $query->execute(['hash' => $hash]);
                    $count = (int)$query->fetchColumn();
                    if ($count <= 0) {
                        $newStale[] = $hash;
                    }
                }

                // Report on usage, and update the stale list with the found
                // hashes excluded
                $newStaleCount = count($newStale);
                $this->logger->info(
                    sprintf(
                        '.. %d of %d non-stale hashes found in %s.%s: %d remaining',
                        ($staleCount - $newStaleCount),
                        $staleCount,
                        $table,
                        $column,
                        $newStaleCount,
                    )
                );
                $stale = $newStale;
            }
        }
        return $stale;
    }

    /**
     * Build a query for finding usage of a hash.
     *
     * @param string $table
     * @param string $column
     * @return IStatement A statement which accepts a storage hash as its
     *                    `hash` parameter.
     */
    private function buildFindHashQuery(string $table, string $column): IStatement
    {
        return $this->db->prepareQuery("
            SELECT COUNT(*)
            FROM {$this->db->getQuoter()->quoteIdentifier($table)} t
            WHERE INSTR({$this->db->getQuoter()->quoteIdentifier($column)}, :hash) > 0
        ");
    }
}
