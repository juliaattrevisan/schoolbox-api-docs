<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Alaress_Date_Format;
use Component_Config_Componentname;
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
        // Pass 1: textboxes within a component
        //  * A similar query exists in library/Schoolbox/Assessment/Course/Plan/Controller.php.
        //  * The displayed heading of a textbox component will be the component name if present,
        //    otherwise the textbox heading - hence the need to fetch the component name below.
        $queryString = "
            SELECT
            CONCAT(cci_textbox.textBoxID, '-', ci.id, '-', f.id) AS id,
            f.id AS folder_id,
            IFNULL(cci_componentname.value, cci_textbox.heading) AS title,
            cci_textbox.updated_at AS date,
            cci_textbox.created_by AS created_by,
            cci_textbox.body AS fulltext_index
            FROM component_definition cd
            JOIN component_instance ci ON ci.component_definition_id = cd.id
            JOIN folder f ON f.id=ci.identifier
            JOIN (
                SELECT cci1.component_instance_id, tb.textBoxID, tb.heading, tb.updated_at, tb.created_by, tb.deleted_at, tb.body
                FROM component_config_instance cci1
                JOIN component_config_definition ccd1 ON (
                    ccd1.key='" . TextboxMapper::HOMEPAGE_COMPONENT_KEY . "' AND
                    ccd1.id=cci1.component_config_definition_id
                )
                JOIN textBox tb ON CAST(tb.textBoxID AS NCHAR)=cci1.value
            ) AS cci_textbox ON (cci_textbox.component_instance_id=ci.id)
            LEFT JOIN (
                SELECT cci2.component_instance_id, cci2.value
                FROM component_config_instance cci2
                JOIN component_config_definition ccd2 ON (
                    ccd2.key = '" . Component_Config_Componentname::KEY . "' AND
                    ccd2.id=cci2.component_config_definition_id
                )
            ) AS cci_componentname ON (cci_componentname.component_instance_id=ci.id)
            WHERE cd.`class` = 'Schoolbox\\\Resource\\\Textbox\\\Component\\\Homepage\\\Controller'
            AND ci.module='" . Component_Definition::MODULE_HOMEPAGE . "'
            AND f.deleted_at IS NULL
            AND cci_textbox.deleted_at IS NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND cci_textbox.updated_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND cci_textbox.updated_at <= :toDate';
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

        // Pass 2: textboxes without a component
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
        // Only fully deleted text boxes count as deleted for the purpose of
        // index building:
        // components are not soft deleted, so I can't tell if an index for
        // those no longer exists.
        $queryString = "SELECT CONCAT(textBoxID, '--') AS id FROM textBox WHERE deleted_at IS NOT NULL";
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
        $this->logger->info(sprintf('Deleting search indices for %d text boxes', $query->rowCount()));
        foreach ($query as $row) {
            yield $this->buildIndex(
                (string)$row['id'],
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
