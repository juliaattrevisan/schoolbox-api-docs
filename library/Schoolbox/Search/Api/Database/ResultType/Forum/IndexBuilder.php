<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Forum;

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
            fp.postID as id,
            ft.title,
            fp.modified AS date,
            fp.userID AS created_by,
            ft.categoryID AS folder_id,
            fp.body AS fulltext_index
            FROM forumThread ft
            LEFT JOIN forumPost fp ON fp.threadID = ft.threadID
            WHERE fp.modified >= :date
            AND fp.deleted = 'false'
            AND ft.deleted = 'false'
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
                                ->setFulltextIndex(strip_tags($row['fulltext_index'] ?? ''))
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
        // Forum posts or threads don't have deletion dates:
        $query = "SELECT
            fp.postID as id
            FROM forumThread ft
            LEFT JOIN forumPost fp ON fp.threadID = ft.threadID
            WHERE fp.deleted = 'true' OR ft.deleted = 'true'
        ";
        return $this->connector->executeQuery($query)->fetchColumn();
    }
}
