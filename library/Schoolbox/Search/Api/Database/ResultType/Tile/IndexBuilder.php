<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Tile;

use Alaress_Date_Format;
use Component_Config;
use Component_Config_Componentname;
use Component_Instance;
use Schoolbox\Resource\Folder;
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Search\Api\Database\Index\BuildType;
use Schoolbox\Tile;

/**
 * Index builder for tiles.
 */
class IndexBuilder extends BaseBuilder
{
    /**
     * This is a cache, so repeated calls to accepts() do not cause repeated database queries to look up the value.
     * @var int|null
     */
    private ?int $tileComponentConfigDefinitionId = null;

    /**
     * This builder works with:
     * * tile objects
     * * tile components
     * * component configs, if it is for a tile
     * * folders, when they are deleted
     *
     * @param object    $subject
     * @param BuildType $buildType
     * @return bool
     */
    protected function accepts(object $subject, BuildType $buildType): bool
    {
        if ($subject instanceof Tile\Model) {
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
            $this->tileComponentConfigDefinitionId ??= $this->getTileLayoutIdComponentConfigDefinitionId();
            return $this->tileComponentConfigDefinitionId === $subject->getConfigDefinitionId();
        }

        if ($buildType === BuildType::DELETE && $subject instanceof Folder\Model) {
            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    protected function augmentQueryParams(object $subject, BuildType $buildType, string $baseQuery, array $baseParams): array
    {
        if ($subject instanceof Tile\Model) {
            return [
                $baseQuery . ' AND t.id = :id',
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
        if ($buildType === BuildType::DELETE && $subject instanceof Folder\Model) {
            return [
                $baseQuery . ' AND f.id = :id',
                $baseParams + ['id' => $subject->getId()],
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

        // Notes:
        // * Tiles without a title should not appear in search results (AC for SA-8841)
        // * Only tiles in homepages should be included in the index (not dashboards, not templates)
        // * Tiles only in deleted folders should not be indexed
        // * Check the related code in buildDeletes() after making changes to this query.
        $queryString = "
            SELECT
            CONCAT(t.id, '-', ci.id, '-', f.id) AS id,
            t.title_text,
            t.link,
            t.modified_at,
            f.id AS folder_id
            FROM component_definition cd
            JOIN component_instance ci ON ci.component_definition_id = cd.id
            JOIN folder f ON f.id = ci.identifier
            JOIN component_config_definition ccd_layoutId ON (
                ccd_layoutId.component_definition_id = cd.id AND
                ccd_layoutId.key = :homepageComponentKey
            )
            LEFT JOIN component_config_instance cci_layoutId ON (
                cci_layoutId.component_config_definition_id = ccd_layoutId.id AND
                cci_layoutId.component_instance_id = ci.id
            )
            JOIN tile_layout tl ON (CAST(tl.id AS NCHAR) = cci_layoutId.value)
            JOIN tile t ON t.tile_layout_id = tl.id
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
            AND tl.deleted_at IS NULL
            AND t.deleted_at IS NULL
            AND t.title_text<>''
        ";
        $params = [
            'homepageComponentKey'            => Tile\Layout\Mapper::HOMEPAGE_COMPONENT_KEY,
            'componentConfigComponentNameKey' => Component_Config_Componentname::KEY,
            'componentClass'                  => self::getComponentClass(),
        ];
        if ($fromDate) {
            $queryString .= ' AND t.modified_at >= :fromDate';
            $params['fromDate'] = $fromDate->getDatabase();
        }
        if ($toDate) {
            $queryString .= ' AND t.modified_at <= :toDate';
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            [$queryString, $params] = $this->augmentQueryParams($subject, BuildType::UPDATE, $queryString, $params);
        }
        $query = $this->connector->prepareQuery($queryString);
        $this->logger->info('Finding tiles...');
        $query->execute($params);
        $this->logger->info(sprintf('Building search indices for %d tiles', $query->rowCount()));
        foreach ($query as $row) {
            $index = $this->buildIndex(
                (string)$row['id'],
                $row['title_text'],
                Hydrator::BASE_TYPE,
                $row['modified_at'],
                true,
                null,
                $row['folder_id'],
            );
            $index = $this->addFulltextIndex($index, $row['link'], self::INDEX_STRATEGY_TEXT);
            yield $index;
        }
    }

    /**
     * @inheritdoc
     */
    public function buildDeletes(
        ?Alaress_Date_Format $fromDate,
        ?Alaress_Date_Format $toDate,
        ?object $subject
    ): iterable {
        if ($subject !== null && !$this->accepts($subject, BuildType::DELETE)) {
            return;
        }

        // Examine and clean up every search index for tiles.
        $searchIndexQueryString = "
            SELECT identifier
            FROM search_index
            WHERE type = :type
        ";
        $searchIndexParams = ['type' => Hydrator::BASE_TYPE];

        // Also add subject filters here, in a convoluted way.
        if ($subject instanceof Tile\Model) {
            $searchIndexQueryString .= ' AND identifier LIKE :tileIdentifier';
            $searchIndexParams['tileIdentifier'] = $subject->getId() . '-%';
        } elseif ($subject instanceof Component_Instance) {
            $searchIndexQueryString .= ' AND identifier LIKE :componentIdentifier';
            $searchIndexParams['componentIdentifier'] = '%-' . $subject->getId() . '-%';
        } elseif ($subject instanceof Component_Config) {
            $searchIndexQueryString .= ' AND identifier LIKE :componentIdentifier';
            $searchIndexParams['componentIdentifier'] = '%-' . $subject->getInstanceId() . '-%';
        } elseif ($subject instanceof Folder\Model) {
            $searchIndexQueryString .= ' AND identifier LIKE :componentIdentifier';
            $searchIndexParams['componentIdentifier'] = '%-' . $subject->getId();
        } elseif ($subject !== null) { // Note $subject will be null when this is run from tools/search/buildIndex.php
            return;
        }
        $searchIndexQuery = $this->connector->prepareQuery($searchIndexQueryString);

        // Set up getTileLayoutId and componentExists queries.
        $tileLayoutIdByTileId = $this->connector->executeQuery(
            "SELECT t.id AS tileId, tl.id AS tileLayoutId
            FROM tile t
            JOIN tile_layout tl ON tl.id = t.tile_layout_id
            WHERE t.deleted_at IS NULL
            AND tl.deleted_at IS NULL"
        )->fetchColumnArray('tileLayoutId', 'tileId');

        $componentExistsQuery = $this->connector->prepareQuery(
            "SELECT cci.component_instance_id
            FROM component_config_instance cci
            INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
            WHERE cci.value = :tileLayoutId
            AND   ccd.key   = :key"
        );
        $componentExistsCache = [];
        $nIndicesRemoved = 0;

        $searchIndexQuery->execute($searchIndexParams);
        $this->logger->info("Examining {$searchIndexQuery->rowCount()} tile search indices.");
        foreach ($searchIndexQuery->fetchColumnArray('identifier') as $identifier) {
            $removeIndex = false;
            [$tileId, $componentId, $folderIdIgnored] = Hydrator::explodeTileInstanceId($identifier);

            // Check if this tile/tile_layout exists.  If not, delete its index.
            if (!isset($tileLayoutIdByTileId[$tileId])) {
                $this->logger->debug("Tile '$identifier' or its linked tile_layout no longer exists. removing index.");
                $removeIndex = true;
            } else {
                $tileLayoutId = $tileLayoutIdByTileId[$tileId];

                // Does this index have an associated component?
                if (!isset($componentExistsCache[$tileId])) {
                    $componentExistsQuery->execute([
                        'tileLayoutId' => $tileLayoutId,
                        'key' => Tile\Layout\Mapper::HOMEPAGE_COMPONENT_KEY,
                    ]);
                    $componentExistsCache[$tileId] = (
                        $componentExistsQuery->fetchColumnArray('component_instance_id')
                    );
                }
                if ($componentId === '') {
                    // This index has no component.  Remove it if any components exist for this tile.
                    if (!empty($componentExistsCache[$tileId])) {
                        $this->logger->debug(
                            "Tile $tileId is associated with a component.  Removing componentless index '$identifier'."
                        );
                        $removeIndex = true;
                    }
                } else {
                    // This index does have a component.  Remove it if it is not associated with that component.
                    if (!in_array($componentId, $componentExistsCache[$tileId])) {
                        $this->logger->debug(
                            "Tile $tileId is no longer associated with component $componentId." .
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

        $this->logger->info("Deleting search indices for $nIndicesRemoved tiles");
    }

    /**
     * Find the config definition ID for tiles.
     *
     * @return int|null
     */
    private function getTileLayoutIdComponentConfigDefinitionId(): ?int
    {
        // Look it up if not yet set
        $componentConfigDefinitionId = $this->connector->executeQuery(
            "SELECT ccd.id
            FROM component_config_definition ccd
            INNER JOIN component_definition cd ON ccd.component_definition_id = cd.id
            WHERE ccd.key = :homepageComponentKey
            AND   cd.class = :class",
            [
                'class' => self::getComponentClass(),
                'homepageComponentKey' => Tile\Layout\Mapper::HOMEPAGE_COMPONENT_KEY
            ]
        )->fetchColumn();

        return filter_var($componentConfigDefinitionId, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Retrieve and trim the homepage component class name, for use in queries.
     *
     * @return string
     */
    private static function getComponentClass(): string
    {
        return trim(Tile\Component\HomepageTileController::class, '\\');
    }
}
