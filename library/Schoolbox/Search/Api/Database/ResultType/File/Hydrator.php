<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\File;

use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\File;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\SearchFilter;
use Schoolbox\User;

/**
 * Hydrator for file results.
 */
class Hydrator extends BaseHydrator
{
    /**
     * Base type for this type of result.
     */
    public const BASE_TYPE = 'file';

    /**
     * Constructor
     *
     * @param File\Mapper $fileMapper Used to fetch files for hydration.
     * @param Access      $access     Used to determine if the current user may view this page.
     */
    public function __construct(
        private File\Mapper $fileMapper,
        private Access $access,
        private PrefixGenerator $prefixGenerator
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
    public function hydrate(string $keyword, SearchFilter\DataObject $filter, array $matches, User $viewer): array
    {
        // Find all news articles which match $matches
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        // TODO make this part generic
        $ids = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($ids)) {
            return [];
        }

        // Find the matching files
        // TODO make generic: have 'id' column as a property of class,
        //      or use mapper's
        $files = $this->fileMapper->findByColumn('id', $ids);

        // And map to a full result
        $fullResults = array_map(
            function (RawResult $match) use ($files, $viewer) {
                $file = $files->getBy('id', $match->id);
                if ($file instanceof File\Model) {
                    // Check if user has access to file's folder
                    $fileAccess = $this->access->getFolderAccess($viewer, $file->getFolder()->getId());
                    if ($fileAccess < UserAccess::ACCESS_READ) {
                        return null;
                    }
                    if ($fileAccess < UserAccess::ACCESS_WRITE && $file->isHidden()) {
                        return null;
                    }
                    return new FullResult(
                        $match,
                        $match->id,
                        $file->getTitle(),
                        $this->buildBaseUrl()
                             ->withPath('send.php')
                             ->withQuery(http_build_query(['id' => $file->getId()]))
                             ->__toString(),
                        $match->type,
                        $match->relevance,
                        $file->getModifiedAt(),
                        $file->getFolder(),
                        null,
                        (
                            // Image? show thumbnail, otherwise show mimetype thumb.
                            (str_contains($file->getMimetype()->__toString(), 'image'))
                            ? $this->buildStorageImageUrl($file)->__toString()
                            : $this->prefixGenerator->generate() . '/images/mime/' . $file->getMimetype()->getIconFile()
                        ),
                        'file',
                        $file->getDescription(),
                        // Should this be modified by instead?
                        $file->getOwner(),
                        // Context: file's folder
                        [$file->getFolder(),],
                        [],
                    );
                }

                return null;
            },
            $matches
        );

        // Filter out anything missing
        // TODO make generic
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }
}
