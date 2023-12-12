<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Blog;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Search\Index;

/**
 * Index builder for blogs.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * @inheritdoc
     */
    public function buildUpdates(Alaress_Date_Format $date): iterable
    {
        $query = $this->connector->prepareQuery("
            SELECT
                b.id,
                b.heading AS title,
                b.created_at AS date,
                b.owner_id AS created_by,
                f.id AS folder_id,
                b.body AS fulltext_index
            FROM blog b
            JOIN folder f ON b.folder_id = f.id
            WHERE b.created_at >= :date
            AND b.deleted_at IS NULL
            AND f.deleted_at IS NULL
        ");
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        $query->execute($params);
        $this->logger->notice(sprintf('Building search indices for %d blog posts', $query->rowCount()));
        foreach ($query as $row) {
            $identifier = (string)$row['id'];
            yield (new Index\Model())
                ->setIdentifier($identifier)
                ->setTitle($row['title'])
                ->setType(Hydrator::BASE_TYPE)
                ->setDate(Alaress_Date_Format::newDatabaseDate($row['date']))
                ->setIsRelevanceDateAffected(true)
                ->setCreatedBy($row['created_by'])
                ->setFolders(
                    (new GenericCollection(Folder\Model::class))
                        ->add((new Folder\Model())->setId($row['folder_id']))
                )
                ->setFulltextIndices(
                    (new GenericCollection(Index\Fulltext\Model::class))
                        ->add(
                            (new Index\Fulltext\Model())
                                ->setFulltextIndex(strip_tags($row['fulltext_index']))
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
            b.id
            FROM blog b
            JOIN folder f ON b.folder_id = f.id
            WHERE COALESCE(b.deleted_at, f.deleted_at) >= :date
        ";
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        return $this->connector->executeQuery($query, $params)->fetchColumn();
    }
}
