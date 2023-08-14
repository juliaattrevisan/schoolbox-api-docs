<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\File;

use Alaress\Database\Quoter\IQuoter;
use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\BaseQueryParamBuilder;

/**
 * Query parameter builder for file searches.
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
     * * deleted files are never fetched
     * * hidden files _are_ fetched (as whether the viewer can view the file
     *   will be determined in the hydrator)
     * * files whose name, description or comment contain `$keyword` are fetched
     *
     * @param string $keyword
     * @return array
     */
    protected function buildBaseWhereParams(string $keyword): array
    {
        $whereParts         = [
            'f.deleted_at IS NULL',
        ];
        $keywordSearchParts = [
            'f.realname LIKE :fileKeywordLike',
            'f.description LIKE :fileKeywordLike',
            'f.comment LIKE :fileKeywordLike',
        ];
        $whereParts[]       = '(' . implode(' OR ', $keywordSearchParts) . ')';
        $params             = [
            'fileNow'         => Alaress_Date_Format::now()->getDatabase(),
            'fileKeywordLike' => '%' . $this->quoter->escapeForLike($keyword) . '%',
        ];

        return [
            'where'  => $whereParts,
            'params' => $params,
        ];
    }

    /**
     * The relevance of a file is its modified time.
     *
     * @return string
     */
    protected function buildRelevanceExpression(): string
    {
        return $this->buildLinearDecayExpression(
            ':fileNow',
            'f.modified',
            7,
            56,
        );
    }

    /**
     * Files are ordered by relevance, as they have a modified time.
     *
     * @return bool
     */
    protected function isOrderedByRelevance(): bool
    {
        return true;
    }

    /**
     * The file's title is its real name.
     *
     * @return string
     */
    protected function buildTitleExpression(): string
    {
        return 'f.realname';
    }

    /**
     * A file's type depends on its mimetype:
     * * audio
     * * video
     * * image
     * * document
     * * other
     *
     * @return string
     */
    protected function buildTypeExpression(): string
    {
        return "'file'";
    }

    /**
     * The file's ID is simply in its `id` column.
     *
     * @return string
     */
    protected function buildIdExpression(): string
    {
        return 'f.id';
    }

    /**
     * The file's date is its modified date.
     *
     * @return string|null
     */
    protected function buildDateExpression(): ?string
    {
        return 'f.modified';
    }

    /**
     * The file's creator is its `owner` column.
     *
     * @return string|null
     */
    protected function buildCreatorExpression(): ?string
    {
        return 'f.owner';
    }

    /**
     * Files are fetched from the `file` table: mimetypes will be derived from
     * `allowedFileTypes`.
     *
     * @return string
     */
    protected function buildFromStatement(): string
    {
        return '
            FROM file f
            INNER JOIN storage              s   ON f.hash = s.hash
            INNER JOIN allowedFileExtension afe ON s.ext = afe.extension
            INNER JOIN allowedFileTypes     aft ON afe.fileTypeID = aft.fileTypeID
        ';
    }
}
