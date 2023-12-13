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
        $this->logger->info('Finding blog posts...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d blog posts', $query->rowCount()));
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
