<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
use Component_Config_Componentname;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;
use Component_Definition;

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
        return [
            'where'  => [], // All the where conditions are specified in buildFromStatement().
            'params' => [
                'textboxNow'         => Alaress_Date_Format::now()->getDatabase(),
                'textboxKeywordLike' => '%' . $this->quoter->escapeForLike($keyword) . '%',
            ]
        ];
    }

    /**
     * The relevance of a matched textbox is determined by its updated_at time.
     *
     * @return string
     */
    protected function buildRelevanceExpression(): string
    {
        return '`relevance`';
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
        return '`title`';
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
        return '`textboxInstanceId`';
    }

    /**
     * A textbox's date is its updated_at date.
     *
     * @return string
     */
    protected function buildDateExpression(): string
    {
        return '`textboxUpdatedAt`';
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
        return '`folderId`';
    }

    /**
     * Textboxes are fetched from the `textBox` table.
     *
     * @return string
     */
    protected function buildFromStatement(): string
    {
        $linearDecayExpression1 = $this->buildLinearDecayExpression(
            ':textboxNow',
            'tb.updated_at',
            7,
            56
        );
        $linearDecayExpression2 = $this->buildLinearDecayExpression(
            ':textboxNow',
            'tb2.updated_at',
            7,
            56
        );

        // Notes on 1st UNIONED subquery:
        //  * this subquery gets all textbox instances matching the keyword
        //  * a textbox instance is a textbox in a particular component in a particular page
        //  * a textbox instance's title is the component name if set, otherwise the textbox heading
        //  * results that match on textbox heading when textbox heading has been overridden by component name will be
        //    eliminated in the Hydrator.
        //
        // Notes on 2nd UNIONED subquery:
        //  * gets all textboxes that are not used in any component
        //
        // Each textbox instance (found in either subquery) will be uniquely identified by the following compound ID:
        //  * "$textboxId-$componentInstanceId-$folderId"
        //  * Example with integer IDs: "1-4081-80"
        //  * Example with integer IDs and not used in any component "94--"
        return '
            FROM (
                SELECT
                CONCAT(tb.textBoxID, "-", cci.component_instance_id, "-", fo.id) AS textboxInstanceId,
                tb.heading AS title,
                ' . $linearDecayExpression1 . ' AS relevance,
                fo.id AS folderId,
                tb.updated_at AS textboxUpdatedAt
                FROM component_config_instance cci
                JOIN component_instance ci ON (
                    ci.module="' . Component_Definition::MODULE_HOMEPAGE . '" AND
                    ci.id=cci.component_instance_id
                )
                JOIN component_config_definition ccd ON (
                    ccd.key="' . TextboxMapper::HOMEPAGE_COMPONENT_KEY . '" AND
                    ccd.id=cci.component_config_definition_id
                )
                JOIN textBox tb ON CAST(tb.textBoxID AS NCHAR)=cci.value
                JOIN folder fo ON fo.id=ci.identifier
                WHERE tb.deleted_at IS NULL
                AND fo.deleted_at IS NULL
                AND (
                    tb.heading LIKE :textboxKeywordLike OR
                    tb.body LIKE :textboxKeywordLike OR
                    EXISTS(
                        SELECT *
                        FROM component_config_instance cci2
                        JOIN component_config_definition ccd2 ON ccd2.id=cci2.component_config_definition_id
                        WHERE cci2.component_instance_id=ci.id
                        AND ccd2.key = "' . Component_Config_Componentname::KEY . '"
                        AND cci2.value LIKE :textboxKeywordLike
                    )
                )
                UNION
                SELECT
                CONCAT(tb2.textBoxID, "--") AS textboxInstanceId,
                tb2.heading AS title,
                ' . $linearDecayExpression2 . ' AS relevance,
                NULL AS folderId,
                tb2.updated_at AS textboxUpdatedAt
                FROM textBox tb2
                WHERE tb2.deleted_at IS NULL
                AND (
                   tb2.heading LIKE :textboxKeywordLike OR
                   tb2.body LIKE :textboxKeywordLike
                )
                AND tb2.textBoxID NOT IN (
                    SELECT CAST(cci2.value AS INT)
                    FROM component_config_instance cci2
                    JOIN component_instance ci2 ON (
                        ci2.module="' . Component_Definition::MODULE_HOMEPAGE . '" AND
                        ci2.id=cci2.component_instance_id
                    )
                    WHERE cci2.value = CAST(tb2.textBoxID AS NCHAR)
                )
            ) AS dummy';
    }
}
