<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Tile;

use Alaress_Date_Format;
use Component_Config_Componentname;
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
     * This builder works with:
     * * tile objects
     * * tile layouts
     * * folders, when they are deleted
     *
     * @param object    $subject
     * @param BuildType $buildType
     * @return bool
     */
    protected function accepts(object $subject, BuildType $buildType): bool
    {
        if ($subject instanceof Tile\Model || $subject instanceof Tile\Layout\Model) {
            return true;
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
        if ($subject instanceof Tile\Layout\Model) {
            return [
                $baseQuery . ' AND tl.id = :id',
                $baseParams + ['id' => $subject->getId()],
            ];
        }
        if ($subject instanceof Folder\Model) {
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
        // Notes:
        // * Tiles without a title should not appear in search results (AC for SA-8841)
        // * Only tiles in homepages should be included in the index (not dashboards, not templates)
        // * Tiles only in deleted folders should not be indexed
        // * Check the related query in buildDeletes() after making changes to this query.
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
            if (!$this->accepts($subject, BuildType::UPDATE)) {
                return;
            }
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
    public function buildDeletes(?Alaress_Date_Format $fromDate, ?Alaress_Date_Format $toDate, ?object $subject): iterable
    {
        // What database updates could cause a tile that was included
        // in the buildUpdates() query to be excluded from that query?
        //  * tlf row could be deleted or updated so it no longer links -+- No need to consider these cases because rows
        //  * f   row could be deleted or updated so it no longer links /   from these tables are never hard-deleted by
        //  * tile.deleted_at        could be set to non-null               the application or updated in such a way
        //  * tile_layout.deleted_at could be set to non-null               that would break the links.
        //  * folder.deleted_at      could be set to non-null
        //  * tile.title_text        could be set to blank
        $queryString = "
            SELECT t.id
            FROM tile t
            JOIN tile_layout tl ON tl.id=t.tile_layout_id
            JOIN tile_layout_for_folder tlf ON tlf.tile_layout_id=tl.id
            JOIN folder f ON tlf.folder_id=f.id
            WHERE (COALESCE(t.deleted_at, tl.deleted_at, f.deleted_at) IS NOT NULL OR t.title_text='')
        ";
        $params = [];
        if ($fromDate && $toDate) {
            $queryString .= (
                ' AND (
                    t.deleted_at BETWEEN :fromDate AND :toDate OR
                    tl.deleted_at BETWEEN :fromDate AND :toDate OR
                    f.deleted_at BETWEEN :fromDate AND :toDate
                )'
            );
            $params['fromDate'] = $fromDate->getDatabase();
            $params['toDate'] = $toDate->getDatabase();
        } elseif ($fromDate) {
            $queryString .= (
                ' AND (
                    t.deleted_at >= :fromDate OR
                    tl.deleted_at >= :fromDate OR
                    f.deleted_at >= :fromDate
                )'
            );
            $params['fromDate'] = $fromDate->getDatabase();
        } elseif ($toDate) {
            $queryString .= (
                ' AND (
                    t.deleted_at <= :toDate OR
                    tl.deleted_at <= :toDate OR
                    f.deleted_at <= :toDate
                )'
            );
            $params['toDate'] = $toDate->getDatabase();
        }
        if ($subject) {
            if (!$this->accepts($subject, BuildType::DELETE)) {
                return;
            }
            [$queryString, $params] = $this->augmentQueryParams($subject, BuildType::DELETE, $queryString, $params);
        }
        $query = $this->connector->prepareQuery($queryString);
        $query->execute($params);
        $this->logger->info(sprintf('Deleting search indices for %d tiles', $query->rowCount()));
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
