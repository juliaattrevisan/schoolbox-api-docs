<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GC\Check;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
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
        $queries = $this->buildQueries();
        $stale   = [];
        foreach ($previous as $hash) {
            $isStale = true;
            foreach ($queries as $foreignKeyQuery) {
                $foreignKeyQuery->execute(['hash' => $hash]);
                $count = $foreignKeyQuery->fetchColumn();
                if ($count > 0) {
                    $isStale = false;
                    break;
                }
            }
            if ($isStale) {
                $stale[] = $hash;
            }
        }

        return $stale;
    }

    /**
     * Builds a list of queries for finding usages of a hash within character
     * fields.
     *
     * @return array<IQuery> A list of queries, each of which accepts a storage
     *                       hash as its `:hash` parameter.
     */
    private function buildQueries(): array
    {
        $queries = [];
        foreach (self::FIELDS as $table => $columns) {
            foreach ($columns as $column) {
                $this->logger->debug(
                    sprintf(
                        'Searching in character field: %s.%s',
                        $table,
                        $column,
                    ),
                );

                $queries[] = $this->db->prepareQuery("
                    SELECT COUNT(*)
                    FROM {$this->db->getQuoter()->quoteIdentifier($table)} t
                    WHERE INSTR({$this->db->getQuoter()->quoteIdentifier($column)}, :hash) > 0
                ");
            }
        }

        return $queries;
    }
}
