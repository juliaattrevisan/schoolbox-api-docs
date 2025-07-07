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
        'blog_image_backup'            => [['column' => 'hash']],
        'bookmarks'                    => [['column' => 'url']],
        'component_config_definition'  => [['column' => 'default_value']],
        'component_config_instance'    => [['column' => 'value']],
        'link'                         => [['column' => 'url']],
        'menu_side'                    => [['column' => 'url']],
        'menu_top'                     => [['column' => 'url']],
        'profile_button'               => [['column' => 'url']],
        'rubric_descriptor'            => [['column' => 'description']],
        'sis_user_profile'             => [['column' => 'portrait_hash']],
        'socialstream_post'            => [['column' => 'thumbnail'], ['column' => 'url']],
        'sportsfixtures_division'      => [['column' => 'url']],
        'sportsfixtures_import_errors' => [['column' => 'hash']],
        'sportsfixtures_sport'         => [['column' => 'url']],
        'sportsfixtures_team'          => [['column' => 'url']],
        'summative_report'             => [['column' => 'footer']],
        'task'                         => [['column' => 'link']],
        'tile'                         => [['column' => 'link']],
        'ups_log'                      => [['column' => 'hash']],
    ];

    /**
     * Builds a list of tables and columns which represent character field
     * relations.
     *
     * @return array<string, array<array{column: string}>>
     */
    protected function buildTableColumnList(): array
    {
        return self::FIELDS;
    }

    /**
     * Build a query for finding usage of a hash.
     *
     * @param string $table      The table to search within.
     * @param array  $columnData `column`: the column to search within
     * @return IStatement A statement which accepts a storage hash as its `hash`
     *                    parameter.
     */
    protected function buildFindHashQuery(string $table, array $columnData): IStatement
    {
        return $this->db->prepareQuery("
            SELECT COUNT(*)
            FROM {$this->db->getQuoter()->quoteIdentifier($table)} t
            WHERE INSTR({$this->db->getQuoter()->quoteIdentifier($columnData['column'])}, :hash) > 0
        ");
    }
}
