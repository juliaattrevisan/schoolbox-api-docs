<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Tile;

use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Tile\Model as TileModel;
use Schoolbox\Tile\Mapper as TileMapper;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
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
     * @param TileMapper $tileMapper Used to fetch tiles for hydration.
     * @param Access     $access     Used to determine if the current user may view this page.
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
    protected function getBaseType(): string
    {
        return self::BASE_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function hydrate(string $keyword, array $matches, User $viewer): array
    {
        // Find all links that match $matches.
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        $ids = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($ids)) {
            return [];
        }

        // Find the matching tiles.
        $tiles = $this->tileMapper->findByColumn('id', $ids);

        // And map to a full result.
        $fullResults = array_map(
            function (RawResult $match) use ($tiles, $viewer) {
                $tile = $tiles->getBy('id', $match->id);
                if ($tile instanceof TileModel) {
                    // FolderId is found this way because tile_layout details are not hydrated in $tile.
                    $folderId = self::getFolderIdMatchingTileLayoutId($tile->getTileLayout()->getId());

                    try {
                        $tileFolder = $this->folderMapper->findById($folderId);
                    } catch (MapperException $e) {
                        $tileFolder = null;
                    }

                    if ($tileFolder === null) {
                        // This tile is not linked to a folder, so it is of no interest.
                        return null;
                    }

                    // Check if the user has access to the tile's folder.
                    $tileAccess = $this->access->getFolderAccess($viewer, $tileFolder->getId());
                    if ($tileAccess < UserAccess::ACCESS_READ) {
                        return null;
                    }

                    return new FullResult(
                        $match,
                        $match->id,
                        $tile->getTitleText(),
                        $tile->getLink(),
                        $match->type,
                        $match->relevance,
                        $tile->getModifiedAt(),
                        $tileFolder,
                        null,
                        $tile->getBackgroundImage(),
                        'tile',
                        '',
                        null,
                        [$tileFolder],
                        [],
                    );
                }

                return null;
            },
            $matches
        );

        // Filter out anything missing.
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }

    /**
     * @param int $tileLayoutId
     * @return int|null
     */
    private static function getFolderIdMatchingTileLayoutId(int $tileLayoutId): int|null
    {
        static $folderIdByTileLayoutId = null;

        if ($folderIdByTileLayoutId === null) {
            $database = FolderMapper::getInstance()->getDatabase();
            // Fetching all tile_layout_for_folder rows here is not ideal, but the alternative of a separate query
            // for each tile would be too slow for a search that returns many tiles.
            $rows = $database->executeQuery('SELECT tile_layout_id, folder_id FROM tile_layout_for_folder')->fetchAll();

            foreach ($rows as $row) {
                $folderIdByTileLayoutId[$row['tile_layout_id']] = $row['folder_id'];
            }
        }

        return array_key_exists($tileLayoutId, $folderIdByTileLayoutId) ? $folderIdByTileLayoutId[$tileLayoutId] : null;
    }
}
