<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Link;

use Alaress_Date_Format;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;

/**
 * Index builder for links.
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
                l.id,
                l.description AS title,
                l.modified AS date,
                l.owner AS created_by,
                l.category AS folder_id,
                l.comment AS fulltext_index
            FROM link l
            INNER JOIN folder fo ON l.category = fo.id
            WHERE l.modified >= :date
            AND l.deleted_at IS NULL
            AND fo.deleted_at IS NULL
        ");
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        $this->logger->info('Finding links...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d links', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['title'],
                Hydrator::BASE_TYPE,
                $row['date'],
                true,
                $row['created_by'],
            );
            $index = $this->addFolder($index, $row['folder_id']);
            $index = $this->addFulltextIndex($index, $row['fulltext_index']);
            $index = $this->addTextIndex($index, $row['title']);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(Alaress_Date_Format $date): iterable
    {
        $query  = "SELECT
            l.id
            FROM link l
            INNER JOIN folder fo ON l.category = fo.id
            WHERE COALESCE(l.deleted_at, fo.deleted_at) >= :date
        ";
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        return $this->connector->executeQuery($query, $params)->fetchColumn();
    }
}
