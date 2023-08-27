<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @textbox      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Textbox as TextboxModel;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\User;

/**
 * Hydrator for textbox results.
 */
class Hydrator extends BaseHydrator
{
    /**
     * Base type for this type of result.
     */
    public const BASE_TYPE = 'textbox';

    /**
     * Constructor
     *
     * @param TextboxMapper $textboxMapper Used to fetch textboxes for hydration.
     * @param Access        $access        Used to determine if the current user may view this page.
     */
    public function __construct(
        private TextboxMapper $textboxMapper,
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
        // Find all textboxes that match $matches.
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        $ids = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($ids)) {
            return [];
        }

        // Find the matching textboxes.
        $textboxes = $this->textboxMapper->findByColumn('id', $ids);

        // And map to a full result.
        $fullResults = array_map(
            function (RawResult $match) use ($textboxes, $viewer) {
                $textbox = $textboxes->getBy('id', $match->id);
                if ($textbox instanceof TextboxModel) {
                    // Check if user has access to textbox's folder
                    $textboxAccess = $this->access->getFolderAccess($viewer, $textbox->getFolder()->getId());
                    if ($textboxAccess < UserAccess::ACCESS_READ) {
                        return null;
                    }
                    if ($textboxAccess < UserAccess::ACCESS_WRITE && $textbox->isHidden()) {
                        return null;
                    }
                    return new FullResult(
                        $match,
                        $match->id,
                        $textbox->getComment(),
                        $this->buildBaseUrl()
                             ->withPath('send.php')
                             ->withQuery(http_build_query(['id' => $textbox->getId()]))
                             ->__toString(),
                        $match->type,
                        $match->relevance,
                        $textbox->getModified(),
                        $textbox->getFolder(),
                        null,
                        // Image? show thumbnail, otherwise show mimetype thumb
                        null,
                        'textbox',
                        $textbox->getDescription(),
                        // Should this be modified by instead?
                        $textbox->getOwner(),
                        // Context: textbox's folder
                        [$textbox->getFolder(),],
                        [],
                    );
                }

                return null;
            },
            $matches
        );

        // Filter out anything missing
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }
}
