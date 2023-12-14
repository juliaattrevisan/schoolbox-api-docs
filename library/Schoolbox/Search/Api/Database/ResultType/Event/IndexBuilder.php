<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Event;

use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;

/**
 * Index builder for events.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * @param Alaress_Date_Format|null $toDate
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate): iterable
    {
        $folderQuery = $this->connector->prepareQuery("
            SELECT folder_id
            FROM event_folder evf
            INNER JOIN folder f ON evf.folder_id = f.id
            WHERE f.deleted_at IS NOT NULL
            AND evf.event_id = :eventId
        ");

        $queryString = "
            SELECT
                e.id,
                e.title,
                e.start_datetime AS date,
                e.author_id AS created_by,
                e.detail AS fulltext_index1,
                e.location AS fulltext_index2
            FROM event e
            WHERE e.deleted_at IS NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString        .= ' AND e.start_datetime >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString      .= ' AND e.start_datetime <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding events...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d events', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['title'],
                Hydrator::BASE_TYPE,
                $row['date'],
                true,
                $row['created_by'],
            );

            // TODO remove this: events shouldn't be associated with folders
            //      at all in search results
            $folderQuery->execute(['eventId' => $row['id']]);
            foreach ($folderQuery as $folderRow) {
                $index = $this->addFolder($index, $folderRow['folder_id']);
            }

            $index = $this->addFulltextIndex($index, $row['fulltext_index1']);
            $index = $this->addFulltextIndex($index, $row['fulltext_index2']);
            $index = $this->addTextIndex($index, $row['title']);
            yield $index;
        }
    }

    /**
     * @param Alaress_Date_Format|null $toDate
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate): iterable
    {
        $queryString = "SELECT
            e.id
            FROM event e
            LEFT JOIN event_folder evf ON e.id = evf.event_id
            LEFT JOIN folder f ON evf.folder_id = f.id
            WHERE COALESCE(e.deleted_at, f.deleted_at) IS NOT NULL
        ";
        $params      = [];
        if ($fromDate) {
            $queryString        .= ' AND COALESCE(e.deleted_at, f.deleted_at) >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString      .= ' AND COALESCE(e.deleted_at, f.deleted_at) <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $query->execute($params);
        $this->logger->info(sprintf('Deleting search indices for %d events', $query->rowCount()));
        return $query->fetchColumnArray('id');
    }
}
