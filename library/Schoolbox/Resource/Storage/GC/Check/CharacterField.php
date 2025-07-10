<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GC\Check;

use Alaress\Database\Statement\IStatement;

/**
 * Check for whether the storage hash is contained within a prescribed set of
 * `CHAR` or `VARCHAR` fields.
 */
class CharacterField extends Base
{
    /**
     * Prescribed set of `CHAR` or `VARCHAR` fields to check.
     * * key: table name
     * * value: list of column names
     *
     * @var array<string, array<array{column: string}>>
     */
    private const FIELDS = [
        // Notes:
        // * any field which contains `hash` should maybe be a FK?
        // * any field which contains `url` or `link` should be checked?
        'blog_image_backup'            => [['columns' => ['hash']]],
        'bookmarks'                    => [['columns' => ['url']]],
        'component_config_definition'  => [['columns' => ['default_value']]],
        'component_config_instance'    => [['columns' => ['value']]],
        'link'                         => [['columns' => ['url']]],
        'menu_side'                    => [['columns' => ['url']]],
        'menu_top'                     => [['columns' => ['url']]],
        'profile_button'               => [['columns' => ['url']]],
        'rubric_descriptor'            => [['columns' => ['description']]],
        'sis_user_profile'             => [['columns' => ['portrait_hash']]],
        'socialstream_post'            => [['columns' => ['thumbnail', 'url']]],
        'sportsfixtures_division'      => [['columns' => ['url']]],
        'sportsfixtures_import_errors' => [['columns' => ['hash']]],
        'sportsfixtures_sport'         => [['columns' => ['url']]],
        'sportsfixtures_team'          => [['columns' => ['url']]],
        'summative_report'             => [['columns' => ['footer']]],
        'task'                         => [['columns' => ['link']]],
        'tile'                         => [['columns' => ['link']]],
        'ups_log'                      => [['columns' => ['hash']]],
    ];

    /**
     * Builds a list of tables and columns which represent character fields
     * which may contain storage hashes, but are not foreign keys.
     */
    protected function buildTableColumnList(): array
    {
        return self::FIELDS;
    }

    /**
     * @inheritdoc
     */
    protected function buildFindHashQuery(string $table, array $columns, ?array $extra): IStatement
    {
        // Build an AND statement for each column
        $whereParts = [];
        foreach ($columns as $column) {
            $whereParts[] = "INSTR({$this->db->getQuoter()->quoteIdentifier($column)}, :hash) > 0";
        }
        $wherePart = implode(' OR ', $whereParts);

        return $this->db->prepareQuery("
            SELECT COUNT(*)
            FROM {$this->db->getQuoter()->quoteIdentifier($table)} t
            WHERE {$wherePart}
        ");
    }
}
