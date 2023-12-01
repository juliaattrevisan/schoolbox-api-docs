<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Event;

use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;

class QueryParamBuilder extends BaseQueryParamBuilder
{
    /**
     * @see \Calendar::getDayEvents
     * @inheritDoc
     */
    protected function buildBaseWhereParams(string $keyword, string $paramKeySuffix = ''): array
    {
        $whereParts = [
            'e.deleted_at IS NULL',
            'f.deleted_at IS NULL',
        ];
        $params = [
            "eventNow$paramKeySuffix"     => Alaress_Date_Format::now()->getDatabase(),
            "eventKeyword$paramKeySuffix" => $keyword,
        ];
        $keywordSearchParts = [
            "MATCH(e.title, e.detail, e.location) AGAINST (:eventKeyword$paramKeySuffix)",
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
            ":eventNow$paramKeySuffix",
            'e.start_datetime',
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
        return 'e.title';
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
        return 'e.id';
    }

    /**
     * @inheritDoc
     */
    protected function buildDateExpression(string $paramKeySuffix = ''): string
    {
        return 'e.start_datetime';
    }

    /**
     * @inheritDoc
     */
    protected function buildCreatorExpression(string $paramKeySuffix = ''): string
    {
        return 'e.author_id'; // TODO: owner??
    }

    /**
     * @inheritDoc
     */
    protected function buildFromStatement(string $paramKeySuffix = ''): string
    {
        return '
            FROM event e
            LEFT JOIN event_folder evf ON e.id = evf.event_id
            LEFT JOIN folder f ON evf.folder_id = f.id
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
