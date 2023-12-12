<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Event;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Search\Index;

/**
 * Index builder for events.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * @inheritdoc
     */
    public function buildUpdates(Alaress_Date_Format $date): iterable
    {
        $folderQuery = $this->connector->prepareQuery("
            SELECT folder_id
            FROM event_folder evf
            INNER JOIN folder f ON evf.folder_id = f.id
            WHERE f.deleted_at IS NOT NULL
            AND evf.event_id = :eventId
        ");

        $query = $this->connector->prepareQuery("
            SELECT
                e.id,
                e.title,
                e.start_datetime AS date,
                e.author_id AS created_by,
                e.detail AS fulltext_index1,
                e.location AS fulltext_index2
            FROM event e
            WHERE e.start_datetime >= :date
            AND e.deleted_at IS NULL
        ");
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d events', $query->rowCount()));
        foreach ($query as $row) {
            $identifier = (string)$row['id'];
            $folderQuery->execute(['eventId' => $row['id']]);
            $folders   = new GenericCollection(Folder\Model::class);
            foreach ($folderQuery as $folderRow) {
                $folders = $folders->add((new Folder\Model())->setId($folderRow['folder_id']));
            }
            yield (new Index\Model())
                ->setIdentifier($identifier)
                ->setTitle($row['title'])
                ->setType(Hydrator::BASE_TYPE)
                ->setDate(Alaress_Date_Format::newDatabaseDate($row['date']))
                ->setIsRelevanceDateAffected(true)
                ->setCreatedBy($row['created_by'])
                ->setFolders($folders)
                ->setFulltextIndices(
                    (new GenericCollection(Index\Fulltext\Model::class))
                        ->add(
                            (new Index\Fulltext\Model())
                                ->setFulltextIndex(strip_tags($row['fulltext_index1']))
                        )
                        ->add(
                            (new Index\Fulltext\Model())
                                ->setFulltextIndex(strip_tags($row['fulltext_index2']))
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
            e.id
            FROM event e
            LEFT JOIN event_folder evf ON e.id = evf.event_id
            LEFT JOIN folder f ON evf.folder_id = f.id
            WHERE COALESCE(e.deleted_at, f.deleted_at) >= :date
        ";
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        return $this->connector->executeQuery($query, $params)->fetchColumn();
    }
}
