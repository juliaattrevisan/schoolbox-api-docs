<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Blog;

use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;

class QueryParamBuilder extends BaseQueryParamBuilder
{
    /**
     * Constructor
     *
     * @param IQuoter $quoter
     */
    public function __construct(
        private IQuoter $quoter
    ) {
    }

    /**
     * @see \Calendar::getDayEvents
     * @inheritDoc
     */
    protected function buildBaseWhereParams(string $keyword, string $paramKeySuffix = ''): array
    {
        $whereParts = [
            'b.deleted_at IS NULL',
            'f.deleted_at IS NULL',
        ];
        $params = [
            "blogNow$paramKeySuffix" => Alaress_Date_Format::now()->getDatabase(),
            "blogKeywordLike$paramKeySuffix" => '%' . $this->quoter->escapeForLike($keyword) . '%'
        ];
        $keywordSearchParts = [
            "b.heading LIKE :blogKeywordLike$paramKeySuffix",
            "b.body LIKE :blogKeywordLike$paramKeySuffix",
        ];

        $whereParts[] = '(' . implode(' OR ', $keywordSearchParts) . ')';
        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function buildRelevanceExpression(string $paramKeySuffix = ''): string
    {
        return $this->buildLinearDecayExpression(
            ":blogNow$paramKeySuffix",
            'b.created_at',
            7,
            56,
        );
    }

    /**
     * @inheritDoc
     */
    protected function isOrderedByRelevance(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    protected function buildTitleExpression(string $paramKeySuffix = ''): string
    {
        return 'b.heading';
    }

    /**
     * @inheritdoc
     */
    protected function getType(): string
    {
        return Hydrator::BASE_TYPE;
    }

    /**
     * @inheritDoc
     */
    protected function buildTypeExpression(string $paramKeySuffix = ''): string
    {
        return '"' . Hydrator::BASE_TYPE . '"';
    }

    /**
     * @inheritDoc
     */
    protected function buildIdExpression(string $paramKeySuffix = ''): string
    {
        return 'b.id';
    }

    /**
     * @inheritDoc
     */
    protected function buildDateExpression(string $paramKeySuffix = ''): string
    {
        return 'b.created_at';
    }

    /**
     * @inheritDoc
     */
    protected function buildCreatorExpression(string $paramKeySuffix = ''): string
    {
        return 'b.owner_id';
    }

    /**
     * @inheritDoc
     */
    protected function buildFromStatement(string $paramKeySuffix = ''): string
    {
        return '
            FROM blog b
            JOIN folder f ON b.folder_id = f.id
        ';
    }

    /**
     * @inheritdoc
     */
    protected function buildFolderExpression(string $paramKeySuffix = ''): ?string
    {
        return 'f.id';
    }
}
