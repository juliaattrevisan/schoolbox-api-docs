<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Alaress_Date_Format;
use Component_Config;
use Component_Config_Componentname;
use Component_Instance;
use Schoolbox\Resource\Textbox;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Search\Api\Database\Index\BuildType;

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
     * This is a cache, so repeated calls to accepts() do not cause repeated database queries to look up the value.
     * @var int|null
     */
    private ?int $textboxComponentConfigDefinitionId = null;

    /**
     * This builder works with:
     * * textbox objects
     * * textbox components
     * * component configs, if it is for a textbox
     *
     * @param object    $subject
     * @param BuildType $buildType
     * @return bool
     */
    protected function accepts(object $subject, BuildType $buildType): bool
    {
        if ($subject instanceof Textbox\Model) {
            return true;
        }

        if (
            $subject instanceof Component_Instance
            /** @phpstan-ignore-next-line - $subject->getDefinition can return null */
            && $subject->getDefinition()?->getClass() === self::getComponentClass()
        ) {
            return true;
        }

        if ($subject instanceof Component_Config) {
            // Need to look up the component config definition to determine if $subject is relevant.
            $this->textboxComponentConfigDefinitionId ??= $this->getTextboxComponentConfigDefinitionId();
            return $this->textboxComponentConfigDefinitionId === $subject->getConfigDefinitionId();
        }

        return false;
    }

    /**
     * @param BuildType $buildType
     * @inheritdoc
     */
    protected function augmentQueryParams(object $subject, BuildType $buildType, string $baseQuery, array $baseParams): array
    {
        if ($subject instanceof Textbox\Model) {
            return [
                $baseQuery . ' AND tb.id = :id',
                $baseParams + ['id' => $subject->getId()],
            ];
        }
        if ($subject instanceof Component_Instance) {
            return [
                $baseQuery . ' AND ci.id = :id',
                $baseParams + ['id' => $subject->getId()],
            ];
        }
        if ($subject instanceof Component_Config) {
            return [
                $baseQuery . ' AND ci.id = :id',
                $baseParams + ['id' => $subject->getInstanceId()],
            ];
        }
        return [$baseQuery, $baseParams];
    }

    /**
     * @inheritdoc
     */
    public function buildUpdates(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        if ($subject !== null && !$this->accepts($subject, BuildType::UPDATE)) {
            return;
        }

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
            CONCAT(tb.id, '-', ci.id, '-', f.id) AS id,
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
            JOIN textbox tb ON CAST(tb.id AS NCHAR)=cci_textbox.value
            JOIN component_config_definition ccd_componentname ON (
                ccd_componentname.component_definition_id = cd.id AND
                ccd_componentname.key = :componentConfigComponentNameKey
            )
            LEFT JOIN component_config_instance cci_componentname ON (
                cci_componentname.component_config_definition_id = ccd_componentname.id AND
                cci_componentname.component_instance_id = ci.id
            )
            WHERE cd.`class` = :componentClass
            AND f.deleted_at IS NULL
            AND tb.deleted_at IS NULL
        ";
        $params = [
            'homepageDefaultComponentHeading' => Textbox\Component\Homepage\Controller::DEFAULT_COMPONENT_HEADING,
            'homepageComponentKey'            => Textbox\Mapper::HOMEPAGE_COMPONENT_KEY,
            'componentConfigComponentNameKey' => Component_Config_Componentname::KEY,
            'componentClass'                  => self::getComponentClass(),
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
            [$queryString, $params] = $this->augmentQueryParams($subject, BuildType::UPDATE, $queryString, $params);
        }
        // Set max_statement_time at the query level to ensure no timeouts even if global max_statement_time is set.
        // The query will not work as expected without the CAST, even though a float value is supplied.
        $queryString = "SET STATEMENT max_statement_time = CAST(:maxStatementTime AS DECIMAL(4,4)) FOR\n$queryString";
        $params['maxStatementTime'] = BaseBuilder::QUERY_LEVEL_MAX_STATEMENT_TIME;
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding text boxes (with components)...');
        $t0 = microtime(true);
        $query->execute($params);
        $t1 = microtime(true);
        $this->logger->debug('Done.  Time taken: ' . round($t1 - $t0, 4) . ' seconds.');
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
                CONCAT(tb.id, '--') AS id,
                tb.heading AS title,
                tb.updated_at AS date,
                tb.created_by AS created_by,
                tb.body AS fulltext_index
            FROM textbox tb
            LEFT JOIN component_config_instance cci2 ON (
                cci2.component_config_definition_id = (
                    SELECT id
                    FROM component_config_definition ccd2
                    WHERE ccd2.key = :homepageComponentKey
                ) AND
                cci2.value = CAST(tb.id AS NCHAR)
            )
            WHERE tb.deleted_at IS NULL
            AND cci2.id IS NULL
        ";
        $params = [
            'homepageComponentKey' => Textbox\Mapper::HOMEPAGE_COMPONENT_KEY,
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
            // Don't need to re-check if subject is accepted, as it was checked
            // in an earlier query.
            // However: need to exclude component search.
            if ($subject instanceof Component_Instance || $subject instanceof Component_Config) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, BuildType::UPDATE, $queryString, $params);
        }
        // Set max_statement_time at the query level to ensure no timeouts even if global max_statement_time is set.
        // The query will not work as expected without the CAST, even though a float value is supplied.
        $queryString = "SET STATEMENT max_statement_time = CAST(:maxStatementTime AS DECIMAL(4,4)) FOR\n$queryString";
        $params['maxStatementTime'] = BaseBuilder::QUERY_LEVEL_MAX_STATEMENT_TIME;
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding text boxes (without components)...');
        $t0 = microtime(true);
        $query->execute($params);
        $t1 = microtime(true);
        $this->logger->debug('Done.  Time taken: ' . round($t1 - $t0, 4) . ' seconds.');
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
        if ($subject !== null && !$this->accepts($subject, BuildType::DELETE)) {
            return null;
        }

        // Examine and clean up every search index for textboxes
        $searchIndexQueryString = "
            SELECT identifier
            FROM search_index
            WHERE type = :type
        ";
        $searchIndexParams = ['type' => Hydrator::BASE_TYPE];

        // Since search_index.identifier has the format "$textboxId-$componentId-$folderId", a different
        // LIKE condition can be used to filter rows of search_index depending on the type of $subject.
        if ($subject instanceof Textbox\Model) {
            $searchIndexQueryString .= ' AND identifier LIKE :textboxIdentifier';
            $searchIndexParams['textboxIdentifier'] = $subject->getId() . '-%';
        } elseif ($subject instanceof Component_Instance) {
            $searchIndexQueryString .= ' AND identifier LIKE :componentIdentifier';
            $searchIndexParams['componentIdentifier'] = '%-' . $subject->getId() . '-%';
        } elseif ($subject instanceof Component_Config) {
            $searchIndexQueryString .= ' AND identifier LIKE :componentIdentifier';
            $searchIndexParams['componentIdentifier'] = '%-' . $subject->getInstanceId() . '-%';
        } elseif ($subject !== null) { // Note $subject will be null when this is run from tools/search/buildIndex.php
            return null;
        }
        $searchIndexQuery = $this->connector->prepareQuery($searchIndexQueryString);

        // Set up textbox and component exists query
        $textboxExistsQueryString = "
            SELECT id
            FROM textbox
            WHERE deleted_at IS NULL
            AND id = :textboxId
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
        $nIndicesRemoved = 0;

        $searchIndexQuery->execute($searchIndexParams);
        $this->logger->info(sprintf('Examining %d text box search indices', $searchIndexQuery->rowCount()));
        foreach ($searchIndexQuery->fetchColumnArray('identifier') as $identifier) {
            $removeIndex = false;
            [$textboxId, $componentId, $folderIgnored] = Hydrator::explodeTextboxInstanceId($identifier);

            // Check if this textbox exists:
            // if not, delete its index
            if (!isset($textboxExistsCache[$textboxId])) {
                $textboxExistsQuery->execute($textboxExistsParams + ['textboxId' => $textboxId]);
                $textboxExistsCache[$textboxId] = $textboxExistsQuery->rowCount() > 0;
            }
            if (!$textboxExistsCache[$textboxId]) {
                $this->logger->debug("Textbox $textboxId no longer exists.  Removing index '$identifier'.");
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
                if ($componentId === null) {
                    // This index has no component:
                    // remove it if any components exist for this textbox
                    if (!empty($componentExistsCache[$textboxId])) {
                        $this->logger->debug(
                            "Textbox $textboxId is associated with a component." .
                            "  Removing componentless index '$identifier'."
                        );
                        $removeIndex = true;
                    }
                } else {
                    // This index does have a component:
                    // remove it if it is not associated with that component
                    if (!in_array($componentId, $componentExistsCache[$textboxId])) {
                        $this->logger->debug(
                            "Textbox $textboxId is no longer associated with component $componentId." .
                            "  Removing index '$identifier'."
                        );
                        $removeIndex = true;
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
                $nIndicesRemoved++;
            }
        }

        $this->logger->info("Deleting search indices for $nIndicesRemoved textboxes");
    }

    /**
     * Find the config definition ID for textboxes.
     *
     * @return int|null
     */
    private function getTextboxComponentConfigDefinitionId(): ?int
    {
        // Look it up if not yet set
        $query = $this->connector->prepareQuery("
            SELECT ccd.id
            FROM component_config_definition ccd
            INNER JOIN component_definition cd ON ccd.component_definition_id = cd.id
            WHERE ccd.key = 'textbox'
            AND   cd.class = :class
        ");
        $query->execute(['class' => self::getComponentClass()]);
        return filter_var($query->fetchColumn(), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Retrieve and trim the homepage component class name, for use in queries.
     *
     * @return string
     */
    private static function getComponentClass(): string
    {
        return trim(Textbox\Component\Homepage\Controller::class, '\\');
    }
}
