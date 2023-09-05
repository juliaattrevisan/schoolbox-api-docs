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
                    if ($match->folderId === null) {
                        // This tile is not linked to a folder, so it is of no interest.
                        return null;
                    }

                    try {
                        // Check if the user has access to the tile's folder.
                        $tileAccess = $this->access->getFolderAccess($viewer, $match->folderId);
                        if ($tileAccess < UserAccess::ACCESS_READ) {
                            return null;
                        }

                        $tileFolder = $this->folderMapper->findById($match->folderId);
                    } catch (MapperException $e) {
                        // Tile folder could not be found for some reason.
                        // This is a slightly different situation to situation where tile is not linked to folder.
                        // In this case the result might be of interest, so return a result but with tileFolder null.
                        $tileFolder = null;
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
                        $tile->getBackgroundImage()?->getFilename(),
                        'tile',
                        '',
                        null,
                        ($tileFolder === null) ? [] : [$tileFolder],
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
}
