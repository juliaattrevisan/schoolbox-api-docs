<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\File;

use Alaress_Date_Format;
use Schoolbox\Resource\File\Model;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Search\Api\Database\Index\BuildType;

/**
 * Index builder for files.
 */
class IndexBuilder extends BaseBuilder
{
    private const SUBTYPE_EXPRESSION = "
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
    ";

    private const JOIN_STATEMENT = "
        INNER JOIN folder               fo  ON f.category = fo.id
        INNER JOIN storage              s   ON f.hash = s.hash
        INNER JOIN allowedFileExtension afe ON s.ext = afe.extension
        INNER JOIN allowedFileTypes     aft ON afe.fileTypeID = aft.fileTypeID
    ";

    /**
     * This builder works with file objects, and with folders on delete.
     *
     * @param object    $subject
     * @param BuildType $buildType
     * @return bool
     */
    protected function accepts(object $subject, BuildType $buildType): bool
    {
        return match ($buildType) {
            BuildType::DELETE => $subject instanceof Model || $subject instanceof FolderModel,
            default           => $subject instanceof Model,
        };
    }

    /**
     * @inheritdoc
     */
    protected function augmentQueryParams(object $subject, BuildType $buildType, string $baseQuery, array $baseParams): array
    {
        if ($subject instanceof Model) {
            return [
                $baseQuery . ' AND f.id = :id',
                $baseParams + ['id' => $subject->getId()],
            ];
        }
        if ($subject instanceof FolderModel) {
            return [
                $baseQuery . ' AND fo.id = :id',
                $baseParams + ['id' => $subject->getId()],
            ];
        }
        return [$baseQuery, $baseParams];
    }

    /**
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        $subtypeExpression = self::SUBTYPE_EXPRESSION;
        $joinStatement     = self::JOIN_STATEMENT;

        // IMPORTANT:
        //  * file.description is the title of the file as seen by Schoolbox users.
        //  * file.realname is the name of the uploaded file.  Schoolbox users only see this name if they download the file.
        $queryString = "
            SELECT
                f.id,
                f.description AS title,
                $subtypeExpression AS sub_type,
                f.modified AS date,
                f.owner AS created_by,
                f.category AS folder_id,
                f.realname AS fulltext_index1,
                f.comment AS fulltext_index2
            FROM file f
            $joinStatement
            WHERE f.deleted_at IS NULL
            AND fo.deleted_at IS NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND f.modified >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND f.modified <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            if (!$this->accepts($subject, BuildType::UPDATE)) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, BuildType::UPDATE, $queryString, $params);
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding files...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d files', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['title'],
                Hydrator::BASE_TYPE . '.' . $row['sub_type'],
                $row['date'],
                true,
                $row['created_by'],
                $row['folder_id'],
            );
            $index = $this->addFulltextIndex($index, $row['fulltext_index1'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['fulltext_index2'], self::INDEX_STRATEGY_TEXT);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        $subtypeExpression = self::SUBTYPE_EXPRESSION;
        $joinStatement     = self::JOIN_STATEMENT;
        $queryString       = "
            SELECT
                f.id,
                $subtypeExpression AS sub_type
                FROM file f
                $joinStatement
                WHERE COALESCE(f.deleted_at, fo.deleted_at) IS NOT NULL
        ";
        $params      = [];
        if ($fromDate) {
            $queryString        .= ' AND COALESCE(f.deleted_at, fo.deleted_at) >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString      .= ' AND COALESCE(f.deleted_at, fo.deleted_at) <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            if (!$this->accepts($subject, BuildType::DELETE)) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, BuildType::DELETE, $queryString, $params);
        }
        $query = $this->connector->prepareQuery($queryString);
        $query->execute($params);
        $this->logger->info(sprintf('Deleting search indices for %d files', $query->rowCount()));
        foreach ($query as $row) {
            yield $this->buildIndex(
                (string)$row['id'],
                null,
                Hydrator::BASE_TYPE . '.' . $row['sub_type'],
                null,
                true,
                null,
                null,
            );
        }
    }
}
