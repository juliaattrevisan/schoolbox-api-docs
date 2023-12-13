<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Badge;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Search\Index;

/**
 * Index builder for badges.
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
                b.name AS title,
                b.updated_at AS date,
                b.created_by,
                b.body AS fulltext_index
            FROM badge b
            WHERE b.updated_at >= :date
            AND b.deleted_at IS NULL
        ");
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        $this->logger->info('Finding badges...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d badges', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['title'],
                Hydrator::BASE_TYPE,
                $row['date'],
                true,
                $row['created_by'],
            );
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
        $query  = "SELECT id FROM badge WHERE deleted_at >= :date";
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        return $this->connector->executeQuery($query, $params)->fetchColumn();
    }
}
