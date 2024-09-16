<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Alaress_Date_Format;
use Component_Config_Componentname;
use Schoolbox\Resource\Textbox;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;

/**
 * Index builder for text boxes.
 *
 * Indices are built for:
 * * extant textboxes with an associated component
 * * extant textboxes with no associated component: these get a special
 *   "no component" result
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * This builder works with textbox objects.
     *
     * TODO It could also work with component objects, if the component links to
     *      a textbox?
     *
     * @param object $subject
     * @return int|string|null
     */
    public function extractItemId(object $subject): int|string|null
    {
        if ($subject instanceof Textbox\Model) {
            return $subject->getId();
        }
        return null;
    }

    /**
     * This builder works with textbox objects.
     *
     * TODO It could also work with component objects, if the component links to
     *      a textbox?
     *
     * @param object $subject
     * @return bool
     */
    protected function accepts(object $subject): bool
    {
        return $subject instanceof Textbox\Model;
    }

    /**
     * @inheritdoc
     */
    protected function augmentQueryParams(object $subject, string $baseQuery, array $baseParams): array
    {
        return [
            $baseQuery . ' AND tb.textBoxID = :id',
            [$baseParams + ['id' => $subject->getId()]],
        ];
    }

    /**
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        // Pass 1: textboxes within a component
        //  * A similar query exists in library/Schoolbox/Assessment/Course/Plan/Controller.php.
        //  * The displayed heading of a textbox component on a homepage will be:
        //     * The component name if non-empty,
        //     * otherwise the textbox heading if non-empty,
        //     * otherwise Component_Homepage_Controller::DEFAULT_COMPONENT_HEADING.
        //    (see library/Schoolbox/Resource/Textbox/Component/Homepage/Controller.php)
        //    So that is the heading we must use for the search index.
        $queryString = "
            SELECT
            CONCAT(tb.textBoxID, '-', ci.id, '-', f.id) AS id,
            f.id AS folder_id,
            COALESCE(
                IF(cci_componentname.value = '', NULL, cci_componentname.value),
                IF(tb.heading = '', NULL, tb.heading),
                :homepageDefaultComponentHeading
            ) AS title,
            tb.updated_at AS date,
            tb.created_by AS created_by,
            tb.body AS fulltext_index
            FROM component_definition cd
            JOIN component_instance ci ON ci.component_definition_id = cd.id
            JOIN folder f ON f.id = ci.identifier
            JOIN component_config_definition ccd_textbox ON (
                ccd_textbox.component_definition_id = cd.id AND
                ccd_textbox.key = :homepageComponentKey
            )
            LEFT JOIN component_config_instance cci_textbox ON (
                cci_textbox.component_config_definition_id = ccd_textbox.id AND
                cci_textbox.component_instance_id = ci.id
            )
            JOIN textBox tb ON CAST(tb.textBoxID AS NCHAR)=cci_textbox.value
            JOIN component_config_definition ccd_componentname ON (
                ccd_componentname.component_definition_id = cd.id AND
                ccd_componentname.key = :componentConfigComponentNameKey
            )
            LEFT JOIN component_config_instance cci_componentname ON (
                cci_componentname.component_config_definition_id = ccd_componentname.id AND
                cci_componentname.component_instance_id = ci.id
            )
            WHERE cd.`class` = 'Schoolbox\\\Resource\\\Textbox\\\Component\\\Homepage\\\Controller'
            AND f.deleted_at IS NULL
            AND tb.deleted_at IS NULL
        ";
        $params = [
            'homepageDefaultComponentHeading' => Textbox\Component\Homepage\Controller::DEFAULT_COMPONENT_HEADING,
            'homepageComponentKey'            => Textbox\Mapper::HOMEPAGE_COMPONENT_KEY,
            'componentConfigComponentNameKey' => Component_Config_Componentname::KEY
        ];
        if ($fromDate) {
            $queryString .= ' AND tb.updated_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND tb.updated_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            if (!$this->accepts($subject)) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, $queryString, $params);
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
                    WHERE ccd2.key = :homepageComponentKey
                ) AND
                cci2.value = CAST(tb2.textBoxID AS NCHAR)
            )
            WHERE tb2.deleted_at IS NULL
            AND cci2.id IS NULL
        ";
        $params = [
            'homepageComponentKey' => Textbox\Mapper::HOMEPAGE_COMPONENT_KEY,
        ];
        if ($fromDate) {
            $queryString .= ' AND tb2.updated_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND tb2.updated_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            // Don't need to re-check if subject is accepted, as it was checked
            // in an earlier query.
            [$queryString, $params] = $this->augmentQueryParams($subject, $queryString, $params);
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
            $index = $this->addFulltextIndex($index, $row['fulltext_index'], self::INDEX_STRATEGY_HTML);
            yield $index;
        }
    }

    /**
     * Deletes textbox search indices which are no longer relevant, e.g. for:
     * * deleted textboxes: all indices are deleted
     * * textboxes with no component: all indices for components are deleted
     * * textboxes with a component: the "no component" index is deleted
     *
     * Note that index deletion for textboxes is not date-boxed: there's no
     * benefit to date-boxing, because this query needs to search the entire set
     * of textbox components to find whether a search index should be deleted.
     *
     * @param Alaress_Date_Format|null $fromDate Ignored
     * @param Alaress_Date_Format|null $toDate   Ignored
     * @param object|null              $subject
     * @return iterable
     */
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
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
        $textboxExistsParams  = [];
        $textboxExistsQuery   = $this->connector->prepareQuery($textboxExistsQueryString);
        $textboxExistsCache   = [];
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
                        'key'       => Textbox\Mapper::HOMEPAGE_COMPONENT_KEY,
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
