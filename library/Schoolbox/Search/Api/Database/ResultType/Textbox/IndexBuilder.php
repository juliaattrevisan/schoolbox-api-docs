<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Alaress_Date_Format;
use Component_Config_Componentname;
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
        //  * The displayed heading of a textbox component on a homepage will be the textbox heading if non-blank,
        //    otherwise the component name.  So that is the heading we must use for the search index.
        $queryString = "
            SELECT
            CONCAT(tb.textBoxID, '-', ci.id, '-', f.id) AS id,
            f.id AS folder_id,
            IF(tb.heading IS NULL OR tb.heading = '', cci_componentname.value, tb.heading) AS title,
            tb.updated_at AS date,
            tb.created_by AS created_by,
            tb.body AS fulltext_index
            FROM component_definition cd
            JOIN component_instance ci ON ci.component_definition_id = cd.id
            JOIN folder f ON f.id = ci.identifier
            JOIN component_config_definition ccd_textbox ON (
                ccd_textbox.component_definition_id = cd.id AND
                ccd_textbox.key = '" . TextboxMapper::HOMEPAGE_COMPONENT_KEY . "'
            )
            LEFT JOIN component_config_instance cci_textbox ON (
                cci_textbox.component_config_definition_id = ccd_textbox.id AND
                cci_textbox.component_instance_id = ci.id
            )
            JOIN textBox tb ON CAST(tb.textBoxID AS NCHAR)=cci_textbox.value
            JOIN component_config_definition ccd_componentname ON (
                ccd_componentname.component_definition_id = cd.id AND
                ccd_componentname.key = '" . Component_Config_Componentname::KEY . "'
            )
            LEFT JOIN component_config_instance cci_componentname ON (
                cci_componentname.component_config_definition_id = ccd_componentname.id AND
                cci_componentname.component_instance_id = ci.id
            )
            WHERE cd.`class` = 'Schoolbox\\\Resource\\\Textbox\\\Component\\\Homepage\\\Controller'
            AND f.deleted_at IS NULL
            AND tb.deleted_at IS NULL
        ";
        $params = [];
        if ($fromDate) {
            $queryString .= ' AND tb.updated_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND tb.updated_at <= :toDate';
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
        // Examine and clean up every search index for textboxes
        $searchIndexQuery = $this->connector->prepareQuery("
            SELECT identifier
            FROM search_index
            WHERE type = :type
        ");
        $textboxExistsQueryString = "
            SELECT textBoxID
            FROM textBox
            WHERE deleted_at IS NULL
            AND textBoxID = :textboxId
        ";
        $textboxExistsParams = [];
        if ($fromDate) {
            $textboxExistsQueryString        .= ' AND deleted_at >= :fromDate';
            $textboxExistsParams['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $textboxExistsQueryString      .= ' AND deleted_at <= :toDate';
            $textboxExistsParams['toDate'] = $toDate->getDatabase();
        }
        $textboxExistsQuery = $this->connector->prepareQuery($textboxExistsQueryString);
        $textboxExistsCache = [];
        $componentExistsQuery = $this->connector->prepareQuery("
            SELECT cci.component_instance_id
            FROM component_config_instance cci
            INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
            WHERE cci.value = :textboxId
            AND   ccd.key   = :key
        ");
        $componentExistsCache = [];

        $searchIndexQuery->execute(['type' => Hydrator::BASE_TYPE]);
        $this->logger->info(sprintf('Examining and cleaning up %d text box search indices', $searchIndexQuery->rowCount()));
        foreach ($searchIndexQuery->fetchColumnArray('identifier') as $identifier) {
            $removeIndex = false;
            [$textboxId, $componentId] = explode('-', $identifier);

            // Check if this textbox exists:
            // if not, delete its index
            if (!isset($textboxExistsCache[$textboxId])) {
                $textboxExistsQuery->execute($textboxExistsParams + ['textboxId' => $textboxId]);
                $textboxExistsCache[$textboxId] = $textboxExistsQuery->rowCount() > 0;
            }
            if (!$textboxExistsCache[$textboxId]) {
                $this->logger->debug(
                    sprintf('Textbox %d no longer exists: removing index', $textboxId),
                );
                $removeIndex = true;
            } else {
                // Does this index have an associated component?
                if (!isset($componentExistsCache[$textboxId])) {
                    $componentExistsQuery->execute([
                        'textboxId' => $textboxId,
                        'key'       => TextboxMapper::HOMEPAGE_COMPONENT_KEY,
                    ]);
                    $componentExistsCache[$textboxId] = $componentExistsQuery->fetchColumnArray('component_instance_id');
                }
                if ($componentId === '') {
                    // This index has no component:
                    // remove it if any components exist for this textbox
                    if (!empty($componentExistsCache[$textboxId])) {
                        $this->logger->debug(
                            sprintf(
                                'Textbox %d is associated with a component: removing componentless index',
                                $textboxId,
                            ),
                        );
                        $removeIndex = true;
                    }
                } else {
                    if (!in_array($componentId, $componentExistsCache[$textboxId])) {
                        if (!$componentExistsCache[$textboxId]) {
                            $this->logger->debug(
                                sprintf(
                                    'Textbox %d is no longer associated with component %d: removing index',
                                    $textboxId,
                                    $componentId,
                                ),
                            );
                            $removeIndex = true;
                        }
                    }
                }
            }

            if ($removeIndex) {
                yield $this->buildIndex(
                    $identifier,
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
