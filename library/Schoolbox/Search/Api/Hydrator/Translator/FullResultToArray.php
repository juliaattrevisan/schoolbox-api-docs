<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Hydrator\Translator;

use Schoolbox\Resource\Folder\Translator\IModelToArray as FolderTranslator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\User\Translator\Author\ModelToArray as UserTranslator;

/**
 * Translator for `FullResult` instances to an array representation.
 */
class FullResultToArray
{
    /**
     * Constructor
     *
     * @param UserTranslator   $userTranslator   User translator: required for rendering the result's creator.
     * @param FolderTranslator $folderTranslator Folder translator: required for rendering the result's context.
     */
    public function __construct(
        private UserTranslator $userTranslator,
        private FolderTranslator $folderTranslator,
    ) {
    }

    /**
     * Translate a `FullResult` instance to an array.
     *
     * @param FullResult $result
     * @return array
     */
    public function __invoke(FullResult $result): array
    {
        // URLs should be fully-qualified:
        // that should be done at the hydrator level.
        return [
            'id'          => $result->id,
            'type'        => $result->type,
            'typeTitle'   => $result->typeTitle,
            'date'        => $result->date?->toRfc3339String(),
            'url'         => $result->url,
            'title'       => $result->title,
            'meta'        => $result->meta,
            'image'       => $result->image,
            'description' => $result->description,
            'creator'     => $result->creator
                ? call_user_func($this->userTranslator, $result->creator)
                : null,
            'context'     => array_map($this->folderTranslator, $result->context),
            'icon'        => $result->icon,
            '_links'      => $result->links,
        ];
    }
}
