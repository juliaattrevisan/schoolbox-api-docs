<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Search\Index;

/**
 * Index builder for text boxes.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * @inheritdoc
     */
    public function buildUpdates(Alaress_Date_Format $date): iterable
    {
        // Pass 1: text boxes within a component
        // TODO does this unnecessarily exclude heading from index?
        //      may need to fetch that index separately
        $query = "SELECT
            CONCAT(tb.textBoxID, '-', cci.component_instance_id, '-', fo.id) AS id,
            tb.heading AS title,
            fo.id AS folder_id,
            tb.updated_at AS date,
            tb.created_by AS created_by,
            tb.body AS fulltext_index
            FROM component_instance ci
            JOIN component_config_instance cci ON cci.component_instance_id=ci.id
            JOIN component_config_definition ccd ON (
                ccd.key='" . TextboxMapper::HOMEPAGE_COMPONENT_KEY . "' AND
                ccd.id=cci.component_config_definition_id
            )
            JOIN textBox tb ON CAST(tb.textBoxID AS NCHAR)=cci.value
            JOIN folder fo ON fo.id=ci.identifier
            WHERE ci.module='" . \Component_Definition::MODULE_HOMEPAGE . "'
            AND tb.updated_at >= :date
            AND tb.deleted_at IS NULL
            AND fo.deleted_at IS NULL
        ";

        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        foreach ($this->connector->executeQuery($query, $params) as $row) {
            $identifier = (string)$row['id'];
            $model = (new Index\Model())
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
            yield $model;
        }

        // Pass 2: text boxes without a component
        $query = "SELECT
            CONCAT(tb2.textBoxID, '--') AS id,
            tb2.heading AS title,
            NULL AS folder_id,
            tb2.updated_at AS date,
            tb2.created_by AS created_by,
            tb2.body AS fulltext_index
            FROM textBox tb2
            LEFT JOIN component_config_instance cci2 ON (
                cci2.component_config_definition_id = (
                    SELECT id
                    FROM component_config_definition ccd2
                    WHERE ccd2.key = '" . TextboxMapper::HOMEPAGE_COMPONENT_KEY . "'
                ) AND
                cci2.value = CAST(tb2.textBoxID AS NCHAR)
            )
            WHERE tb2.deleted_at IS NULL
            AND tb2.updated_at >= :date
            AND cci2.id IS NULL
        ";
        foreach ($this->connector->executeQuery($query, $params) as $row) {
            $identifier = (string)$row['id'];
            $model = (new Index\Model())
                ->setIdentifier($identifier)
                ->setTitle($row['title'])
                ->setType(Hydrator::BASE_TYPE)
                ->setDate(Alaress_Date_Format::newDatabaseDate($row['date']))
                ->setIsRelevanceDateAffected(true)
                ->setCreatedBy($row['created_by'])
                ->setFolders(new GenericCollection(Folder\Model::class))
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
            yield $model;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(Alaress_Date_Format $date): iterable
    {
        // Only fully deleted text boxes count as deleted for the purpose of
        // index building:
        // components are not soft deleted, so I can't tell if an index for
        // those no longer exists.
        $query  = "SELECT CONCAT(textBoxID, '--') FROM textBox WHERE deleted_at >= :date";
        $params = [
            'date' => $date->getDatabaseDate(),
        ];
        return $this->connector->executeQuery($query, $params)->fetchColumn();
    }
}
