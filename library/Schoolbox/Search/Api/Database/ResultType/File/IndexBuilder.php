<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\File;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
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
        $query = $this->connector->prepareQuery("
            SET STATEMENT max_statement_time=0 FOR SELECT
                f.id,
                f.realname AS title,
                f.modified AS date,
                f.owner AS created_by,
                f.category AS folder_id,
                f.description AS fulltext_index1,
                f.comment AS fulltext_index2
            FROM file f
            INNER JOIN folder fo ON f.category = fo.id
            WHERE f.modified >= :date
            AND f.deleted_at IS NULL
            AND fo.deleted_at IS NULL
        ");
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        $this->logger->info('Finding files...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d files', $query->rowCount()));
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
            $index = $this->addFulltextIndex($index, $row['fulltext_index1']);
            $index = $this->addFulltextIndex($index, $row['fulltext_index2']);
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
            f.id
            FROM file f
            INNER JOIN folder fo ON f.category = fo.id
            WHERE COALESCE(f.deleted_at, fo.deleted_at) >= :date
        ";
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        return $this->connector->executeQuery($query, $params)->fetchColumn();
    }
}
