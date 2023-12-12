<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\News;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Search\Index;

/**
 * Index builder for news.
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
                n.id,
                n.title,
                n.publish_at AS date,
                n.author_id AS created_by,
                n.body AS fulltext_index1,
                n.blurb AS fulltext_index2
            FROM comms_news_article n
            WHERE n.deleted_at IS NULL
            AND n.accepted_at IS NOT NULL
            AND n.publish_at >= :date
            AND n.rejected_at IS NULL
        ");
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        $query->execute($params);
        $this->logger->notice(sprintf('Building search indices for %d news articles', $query->rowCount()));
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
                )
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
        // Not sure how news deletions should be handled:
        // IMO articles rejected should be removed from the index,
        // but don't know how to handle that and also deletion.
        $query  = "SELECT
            n.id
            FROM comms_news_article n
            WHERE n.deleted_at >= :date
        ";
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        return $this->connector->executeQuery($query, $params)->fetchColumn();
    }
}
