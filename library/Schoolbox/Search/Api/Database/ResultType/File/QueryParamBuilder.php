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
    protected function buildBaseWhereParams(string $keyword, string $paramKeySuffix = ''): array
    {
        $whereParts         = [
            'f.deleted_at IS NULL',
            'fo.deleted_at IS NULL',
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
    protected function buildRelevanceExpression(string $paramKeySuffix = ''): string
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
    protected function buildTitleExpression(string $paramKeySuffix = ''): string
    {
        return 'f.realname';
    }

    /**
     * @return string
     */
    protected function getType(): string
    {
        return Hydrator::BASE_TYPE;
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
    protected function buildTypeExpression(string $paramKeySuffix = ''): string
    {
        // Depends on the mime's file type:
        // derived from a similar list in
        // `\Schoolbox\Resource\File\Mapper::findForFolders`
        return "CONCAT(
            '" . Hydrator::BASE_TYPE . ".',
            IF(
                LOCATE('pdf', aft.mime)
                OR LOCATE('msword', aft.mime)
                OR LOCATE('vnd.ms', aft.mime)
                OR LOCATE('rtf', aft.mime)
                OR LOCATE('text', aft.mime)
                OR LOCATE('document', aft.mime)
                OR LOCATE('powerpoint', aft.mime),
                'document',
                IF(
                    LOCATE('image', aft.mime),
                    'image',
                    IF(
                        LOCATE('audio', aft.mime),
                        'audio',
                        IF(
                            LOCATE('video', aft.mime)
                            OR LOCATE('flash', aft.mime)
                            OR LOCATE('realmedia', aft.mime),
                            'video',
                            'other'
                        )
                    )
                )
            )
        )";
    }

    /**
     * The file's ID is simply in its `id` column.
     *
     * @return string
     */
    protected function buildIdExpression(string $paramKeySuffix = ''): string
    {
        return 'f.id';
    }

    /**
     * The file's date is its modified date.
     *
     * @return string
     */
    protected function buildDateExpression(string $paramKeySuffix = ''): string
    {
        return 'f.modified';
    }

    /**
     * The file's creator is its `owner` column.
     *
     * @return string
     */
    protected function buildCreatorExpression(string $paramKeySuffix = ''): string
    {
        return 'f.owner';
    }

    /**
     * The file's folder is its `category` column.
     *
     * @return string
     */
    public function buildFolderExpression(string $paramKeySuffix = ''): string
    {
        return 'f.category';
    }

    /**
     * Files are fetched from the `file` table: mimetypes will be derived from
     * `allowedFileTypes`.
     *
     * @return string
     */
    protected function buildFromStatement(string $paramKeySuffix = ''): string
    {
        return '
            FROM file f
            INNER JOIN folder               fo  ON f.category = fo.id
            INNER JOIN storage              s   ON f.hash = s.hash
            INNER JOIN allowedFileExtension afe ON s.ext = afe.extension
            INNER JOIN allowedFileTypes     aft ON afe.fileTypeID = aft.fileTypeID
        ';
    }
}
