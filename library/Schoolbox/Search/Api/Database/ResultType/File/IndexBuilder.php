<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\File;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Search\Index;

/**
 * Index builder for files.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * @inheritdoc
     */
    public function buildUpdates(Alaress_Date_Format $date): iterable
    {
        $query = "SELECT
            f.id,
            f.realname AS title,
            f.modified AS date,
            f.owner AS created_by,
            f.category AS folder_id,
            f.description AS fulltext_index1,
            f.comment AS fulltext_index2
            FROM file f
            INNER JOIN folder               fo  ON f.category = fo.id
            INNER JOIN storage              s   ON f.hash = s.hash
            INNER JOIN allowedFileExtension afe ON s.ext = afe.extension
            INNER JOIN allowedFileTypes     aft ON afe.fileTypeID = aft.fileTypeID
            WHERE f.modified >= :date
            AND f.deleted_at IS NULL
            AND fo.deleted_at IS NULL
        ";
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        foreach ($this->connector->executeQuery($query, $params) as $row) {
            $identifier = (string)$row['id'];
            yield (new Index\Model())
                ->setIdentifier($identifier)
                ->setTitle($row['title'])
                ->setType(Hydrator::BASE_TYPE)
                ->setDate(Alaress_Date_Format::newDatabaseDate($row['date']))
                ->setIsRelevanceDateAffected(true)
                ->setCreatedBy($row['created_by'])
                ->setFolderId($row['folder_id'])
                ->setFulltextIndices(
                    (new GenericCollection(Index\Fulltext\Model::class))
                        ->add(
                            (new Index\Fulltext\Model())
                                ->setFulltextIndex(strip_tags($row['fulltext_index1'] ?? ''))
                        )
                        ->add(
                            (new Index\Fulltext\Model())
                                ->setFulltextIndex(strip_tags($row['fulltext_index2'] ?? ''))
                        )
                )
                ->setTextIndices(
                    (new GenericCollection(Index\Text\Model::class))
                        ->add(
                            (new Index\Text\Model())
                                ->setTextIndex($row['title'])
                        )
                );
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(Alaress_Date_Format $date): iterable
    {
        $query  = "SELECT
            f.id
            FROM file f
            INNER JOIN folder               fo  ON f.category = fo.id
            INNER JOIN storage              s   ON f.hash = s.hash
            INNER JOIN allowedFileExtension afe ON s.ext = afe.extension
            INNER JOIN allowedFileTypes     aft ON afe.fileTypeID = aft.fileTypeID
            WHERE COALESCE(f.deleted_at, fo.deleted_at) >= :date
        ";
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        return $this->connector->executeQuery($query, $params)->fetchColumn();
    }
}
