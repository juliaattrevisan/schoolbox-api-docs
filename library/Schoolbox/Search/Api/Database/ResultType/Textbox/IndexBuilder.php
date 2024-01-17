<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Alaress_Date_Format;
use Component_Definition;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;

/**
 * Index builder for text boxes.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate): iterable
    {
        // Pass 1: text boxes within a component
        // TODO does this unnecessarily exclude heading from index?
        //      may need to fetch that index separately
        $queryString = "
            SELECT
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
            WHERE ci.module='" . Component_Definition::MODULE_HOMEPAGE . "'
            AND tb.deleted_at IS NULL
            AND fo.deleted_at IS NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString        .= ' AND tb.updated_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString      .= ' AND tb.updated_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding text boxes (with components)...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d text boxes (with components)', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['title'],
                Hydrator::BASE_TYPE,
                $row['date'],
                true,
                $row['created_by'],
                $row['folder_id'],
            );
            $index = $this->addFulltextIndex($index, $row['title'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['fulltext_index'], self::INDEX_STRATEGY_HTML);
            yield $index;
        }

        // Pass 2: text boxes without a component
        $queryString = "
            SELECT
                CONCAT(tb2.textBoxID, '--') AS id,
                tb2.heading AS title,
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
            AND cci2.id IS NULL
        ";

        // Don't need new params for the second pass
        if ($fromDate) {
            $queryString .= ' AND tb2.updated_at >= :fromDate';
        }
        if ($toDate) {
            $queryString .= ' AND tb2.updated_at <= :toDate';
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding text boxes (without components)...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d text boxes (without components)', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['title'],
                Hydrator::BASE_TYPE,
                $row['date'],
                true,
                $row['created_by'],
                null,
            );
            $index = $this->addFulltextIndex($index, $row['title'], self::INDEX_STRATEGY_TEXT);
            $index = $this->addFulltextIndex($index, $row['fulltext_index'], self::INDEX_STRATEGY_HTML);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate): iterable
    {
        // Search for indices which match a given pattern
        $searchIndexQuery = $this->connector->prepareQuery("
            SELECT identifier
            FROM search_index
            WHERE type = :type
            AND   identifier LIKE :identifierPattern
        ");

        // Pass 1: delete indices for deleted text boxes
        // Only fully deleted text boxes count as deleted for the purpose of
        // index building:
        // components are not soft deleted, so I can't tell if an index for
        // those no longer exists.
        $queryString = "SELECT textBoxID AS id FROM textBox WHERE deleted_at IS NOT NULL";
        $params      = [];
        if ($fromDate) {
            $queryString        .= ' AND deleted_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString      .= ' AND deleted_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        $query = $this->connector->prepareQuery($queryString);
        $query->execute($params);
        $this->logger->info(sprintf('Deleting search indices for %d deleted text boxes', $query->rowCount()));
        foreach ($query as $row) {
            $searchIndexQuery->execute([
                'type'              => Hydrator::BASE_TYPE,
                'identifierPattern' => $row['id'] . '-%',
            ]);
            foreach ($searchIndexQuery as $searchIndex) {
                yield $this->buildIndex(
                    (string)$searchIndex['identifier'],
                    null,
                    Hydrator::BASE_TYPE,
                    null,
                    true,
                    null,
                    null,
                );
            }
        }
    }
}
