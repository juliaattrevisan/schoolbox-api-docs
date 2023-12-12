<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Page;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Search\Index;

/**
 * Index builder for pages.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * @inheritdoc
     */
    public function buildUpdates(Alaress_Date_Format $date): iterable
    {
        // Text indices: may be built from folder codes as well
        $folderCodeQuery = $this->connector->prepareQuery("
            SELECT code
            FROM folder_code
            WHERE deleted_at IS NULL
            AND folder_id = :folderId
        ");

        // Queries for type selection
        $courseSpecQuery = $this->connector->prepareQuery("
            SELECT id
            FROM course_specification
            WHERE deleted_at IS NULL
            AND folder_id = :folderId
        ");
        $unitSpecQuery = $this->connector->prepareQuery("
            SELECT id
            FROM course_unit_specification
            WHERE deleted_at IS NULL
            AND folder_id = :folderId
        ");
        $groupQuery = $this->connector->prepareQuery("
            SELECT cid
            FROM userGroups
            WHERE cid = :folderId
        ");
        $homepageQuery = $this->connector->prepareQuery("
            SELECT template
            FROM homepage_config
            WHERE cid = :folderId
        ");

        $query = "SELECT DISTINCT
            f.id,
            f.name AS title,
            f.updated_at AS date,
            f.created_by
            FROM folder f
            WHERE f.deleted_at IS NULL
            AND f.updated_at >= :date
        ";
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        foreach ($this->connector->executeQuery($query, $params) as $row) {
            $identifier = (string)$row['id'];

            // Execute each query in turn above, and set type accordingly

            yield (new Index\Model())
                ->setIdentifier($identifier)
                ->setTitle($row['title'])
                ->setType($row['type'])
                ->setDate(Alaress_Date_Format::newDatabaseDate($row['date']))
                ->setIsRelevanceDateAffected(false)
                ->setCreatedBy($row['created_by'])
                ->setFolders(
                    (new GenericCollection(Folder\Model::class))
                )
                ->setFulltextIndices(
                    (new GenericCollection(Index\Fulltext\Model::class))
                )
                ->setTextIndices(
                    (new GenericCollection(Index\Text\Model::class))
                        ->add(
                            (new Index\Text\Model())
                                ->setTextIndex($row['title'])
                        )
                        ->add(
                            (new Index\Text\Model())
                                ->setTextIndex($row['codes'])
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
