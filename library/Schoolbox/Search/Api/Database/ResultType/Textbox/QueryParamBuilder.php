<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
use Component_Config_Collapse;
use Component_Config_ShowComponentTitle;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;

/**
 * Query parameter builder for textbox searches.
 */
class QueryParamBuilder extends BaseQueryParamBuilder
{
    /**
     * Constructor
     *
     * @param IQuoter $quoter
     */
    public function __construct(private IQuoter $quoter)
    {
    }

    /**
     * Build WHERE params such that
     * * deleted textboxes are never fetched
     * * hidden textboxes _are_ fetched (as whether the viewer can view the textbox will be determined in the hydrator)
     * * textboxes whose url or comment or description contain `$keyword` are fetched
     *
     * @param string $keyword
     * @return array
     */
    protected function buildBaseWhereParams(string $keyword): array
    {
        $whereParts         = [
            'ccd.key="' . TextboxMapper::HOMEPAGE_COMPONENT_KEY . '"',
            'ci.module="homepage"',
            'tb.deleted_at IS NULL',
            'fo.deleted_at IS NULL',
        ];
        $keywordSearchParts = [
            'tb.heading LIKE :textboxKeywordLike',
            'tb.body LIKE :textboxKeywordLike',
        ];
        $whereParts[]       = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params             = [
            'textboxNow'         => Alaress_Date_Format::now()->getDatabase(),
            'textboxKeywordLike' => '%' . $this->quoter->escapeForLike($keyword) . '%',
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of a matched textbox is determined by its updated_at time.
     *
     * @return string
     */
    protected function buildRelevanceExpression(): string
    {
        return $this->buildLinearDecayExpression(
            ':textboxNow',
            'tb.updated_at',
            7,
            56,
        );
    }

    /**
     * Textboxes are ordered by relevance, as they have a updated_at time.
     *
     * @return bool
     */
    protected function isOrderedByRelevance(): bool
    {
        return true;
    }

    /**
     * The textbox's title is its `heading`.
     *
     * @return string
     */
    protected function buildTitleExpression(): string
    {
        return 'tb.heading';
    }

    /**
     * @return string
     */
    protected function getType(): string
    {
        return Hydrator::BASE_TYPE;
    }

    /**
     * @return string
     */
    protected function buildTypeExpression(): string
    {
        return '"' . Hydrator::BASE_TYPE . '"';
    }

    /**
     * The textbox's ID is simply in its `textBoxID` column.
     *
     * @return string
     */
    protected function buildIdExpression(): string
    {
        // Return a compound id "$textboxId-$componentInstanceId-$folderId".
        // This value will be unique for each textbox instance - meaning a texbox positioned in a component in a folder.
        return 'CONCAT(tb.textBoxID, "-", cci.component_instance_id, "-", fo.id)';
    }

    /**
     * A textbox's date is its updated_at date.
     *
     * @return string
     */
    protected function buildDateExpression(): string
    {
        return 'tb.updated_at';
    }

    /**
     * Textboxes currently have no creator stored in the database, so return null.
     *
     * @return string|null
     */
    protected function buildCreatorExpression(): ?string
    {
        return 'tb.created_by';
    }

    /**
     * The textbox's folder is its `categoryID` column.
     *
     * @return string
     */
    public function buildFolderExpression(): string
    {
        return 'tb.categoryID';
    }

    /**
     * Textboxes are fetched from the `textBox` table.
     *
     * @return string
     */
    protected function buildFromStatement(): string
    {
        // Each row of this query will represent a textbox instance - that is a textbox in a component in a folder.
        // Each textbox instance will be uniquely identified by the following compound ID:
        //  * CONCAT(tb.textBoxID, "-", cci.component_instance_id, "-", fo.id)
        //  * Example with named variables: "$textboxId-$componentInstanceId-$folderId"
        //  * Example with integer IDs    : "1-4081-80"
        return '
            FROM component_config_instance cci
            JOIN component_instance ci ON ci.id=cci.component_instance_id
            JOIN component_config_definition ccd ON ccd.id=cci.component_config_definition_id
            JOIN textBox tb ON tb.textBoxID=cci.value
            JOIN folder fo ON fo.id=ci.identifier
            LEFT JOIN (
                SELECT
                cci2.component_instance_id,
                JSON_OBJECT(ccd2.key, IFNULL(cci2.value, ccd2.default_value)) AS config_json
                FROM component_config_instance cci2
                LEFT JOIN component_config_definition ccd2 ON ccd2.id=cci2.component_config_definition_id
                WHERE ccd2.key IN ("' . Component_Config_Collapse::KEY . '", "' . Component_Config_ShowComponentTitle::KEY . '")
                GROUP BY cci2.component_instance_id
            ) AS component_configJson ON (component_configJson.component_instance_id=cci.component_instance_id)
        ';
    }
}
