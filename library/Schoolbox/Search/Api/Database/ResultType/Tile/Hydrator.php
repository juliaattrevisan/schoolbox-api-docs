<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Tile;

use Component_Config_Collapse;
use Component_Config_ShowComponentTitle;
use InvalidArgumentException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Tile\Model as TileModel;
use Schoolbox\Tile\Mapper as TileMapper;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\SearchFilter;
use Schoolbox\User;

/**
 * Hydrator for link results.
 */
class Hydrator extends BaseHydrator
{
    /**
     * Base type for this type of result.
     */
    public const BASE_TYPE = 'tile';

    /**
     * Constructor
     *
     * @param TileMapper       $tileMapper       Used to fetch tiles for hydration.
     * @param FolderMapper     $folderMapper     Used to fetch folders to help with tile hydration.
     * @param Access           $access           Used to determine if the current user may view this page.
     */
    public function __construct(
        private TileMapper $tileMapper,
        private FolderMapper $folderMapper,
        private Access $access,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function hydrate(string $keyword, SearchFilter\DataObject $filter, array $matches, User $viewer): array
    {
        // Find all tiles that match $matches.
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        if (count($acceptedMatches) === 0) {
            return [];
        }

        // Get arrays of tileIds and componentInstanceIds.
        $tileIds = [];
        $componentInstanceIds = [];
        foreach ($acceptedMatches as $match) {
            [$tileId, $componentInstanceId, $folderId] = self::explodeTileInstanceId($match->id);
            $tileIds[] = $tileId;
            $componentInstanceIds[] = $componentInstanceId;
        }

        // Find the matching tiles.
        $tiles = $this->tileMapper->findByColumn('id', $tileIds);

        // Get config info for each component instance for later lookup.
        $configByComponentInstanceId = $this->getConfigByComponentInstanceId($componentInstanceIds);

        $fullResults = [];

        foreach ($acceptedMatches as $key => $match) {
            [$tileId, $componentInstanceId, $folderId] = self::explodeTileInstanceId($match->id);

            if ($componentInstanceId === null) {
                // This tile is not used in any homepage component, so it is of no interest.
                continue;
            }

            $tile = $tiles->getBy('id', $tileId);
            if (!($tile instanceof TileModel)) {
                continue;
            }

            // Which folder should be used (from search index or from tile layout)?
            // ------------------------------------------------------------------------
            // The folderId from tile->getTileLayout()->getFolder() will normally match the folderId obtained via
            // $match->id above (from the search_index table).  However, for tiles in alt-sourced components,
            // $tile->getTileLayout()->getFolder() will be the folder of the tile in its original location.  We want
            // the folder from the new location, so we continue to use the $folderId from $match->id.

            if ($folderId === null) {
                // This tile is not linked to a folder, so it is of no interest.
                continue;
            }

            // Check if the user has access to the tile's folder.
            try {
                $tileAccess = $this->access->getFolderAccess($viewer, $folderId);
            } catch (MapperException) {
                $tileAccess = UserAccess::ACCESS_DENY;
            }

            if ($tileAccess < UserAccess::ACCESS_READ) {
                continue;
            }

            try {
                $tileFolder = $this->folderMapper->findById($folderId);
            } catch (MapperException $e) {
                // The tile's folder could not be found for some reason.
                // This is a slightly different situation to situation where tile is not linked to folder.
                // In this case the result might be of interest, so return a result but with tileFolder null.
                $tileFolder = null;
            }

            $titleText = trim($tile->getTitleText());

            if ($titleText === '') {
                // Tiles without a title should not appear in search results (AC for SA-8841).
                continue;
            }

            // $componentConfig (if not null) will be an array with the following optional keys:
            //  * Component_Config_Collapse::KEY,
            //  * Component_Config_ShowComponentTitle::KEY
            $componentConfig = $configByComponentInstanceId[$componentInstanceId] ?? null;

            // Get relevant component config values.
            // Note Component_Config_ShowComponentTitle is poorly named.  The UI makes clear that this option applies
            // only to read-only users.  To confirm, go to a homepage, and edit the configuration of a tile component.
            // The checkbox label is 'Show Title to Read-Only Users'.
            $componentIsCollapsed = boolval($componentConfig[Component_Config_Collapse::KEY] ?? 0); // Default is not collapsed.
            $showTitleToReadOnlyUsers = boolval($componentConfig[Component_Config_ShowComponentTitle::KEY] ?? 1); // Default is to show title.

            // Tiles that have been hidden to read-only users (by having title hidden and body collapsed)
            // should appear in search results only for users with write access.
            if (!$showTitleToReadOnlyUsers && $componentIsCollapsed && $tileAccess < UserAccess::ACCESS_WRITE) {
                continue;
            }

            $fullResults[$key] = new FullResult(
                $match,
                $match->id,
                $titleText,
                $tile->getLink(),
                $match->type,
                'Tile',
                $match->relevance,
                $tile->getModifiedAt(),
                $tileFolder,
                null,
                (
                    $tile->getBackgroundImage()
                    ? $this->buildStorageImageUrl($tile->getBackgroundImage())->__toString()
                    : null
                ),
                'tiles',
                null,
                null,
                ($tileFolder === null) ? [] : [$tileFolder],
                [],
            );
        }

        return $fullResults;
    }

    /**
     * @param string $tileInstanceId - Expected to be a string like "$tileId-$componentInstanceId-$folderId".
     * @return array
     */
    private static function explodeTileInstanceId(string $tileInstanceId): array
    {
        $tokens = explode('-', $tileInstanceId);

        if (count($tokens) !== 3) {
            throw new InvalidArgumentException('String $tileInstanceId is in unexpected format.');
        }

        return [
            '' === $tokens[0] ? null : (int)$tokens[0], // tileId,
            '' === $tokens[1] ? null : (int)$tokens[1], // componentInstanceId,
            '' === $tokens[2] ? null : (int)$tokens[2]  // folderId.
        ];
    }

    /**
     * @param array $componentInstanceIds
     * @return array
     */
    private function getConfigByComponentInstanceId(array $componentInstanceIds): array
    {
        $db = $this->tileMapper->getDatabase();

        $rows = $db->executeQuery(
            'SELECT cci.component_instance_id, ccd.key, cci.value
            FROM component_config_instance cci
            LEFT JOIN component_config_definition ccd ON ccd.id=cci.component_config_definition_id
            WHERE cci.component_instance_id IN (' . implode(',', array_map('intval', $componentInstanceIds)) . ')
            AND ccd.key IN (:configCollapseKey, :showComponentTitleKey)',
            [
                ':configCollapseKey' => Component_Config_Collapse::KEY,
                ':showComponentTitleKey' => Component_Config_ShowComponentTitle::KEY,
            ]
        )->fetchAll();

        $configByComponentInstanceId = [];

        foreach ($rows as $row) {
            $componentInstanceId = $row['component_instance_id'];

            if (!array_key_exists($componentInstanceId, $configByComponentInstanceId)) {
                $configByComponentInstanceId[$componentInstanceId] = [];
            }

            $configByComponentInstanceId[$componentInstanceId][$row['key']] = $row['value'];
        }

        return $configByComponentInstanceId;
    }
}
