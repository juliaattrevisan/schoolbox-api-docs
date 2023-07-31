<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Result;

use Alaress_Date_Format;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;

/**
 * Data object for search results.
 */
class DataObject
{
    /**
     * Constructor
     *
     * @param int                      $id          The ID of the search result subject.
     * @param string                   $title       The title of the search result.
     * @param string                   $url         A link to the search result.
     * @param string                   $type        The type of search result.
     * @param Alaress_Date_Format|null $date        The date (if any) of the search result.
     * @param string|null              $meta        An optional brief context line for the search result.
     * @param string|null              $image       A link to an image to display next to this search result.
     * @param string|null              $icon        The name of an icon to display next to this search result
     *                                              (if `$image` is null).
     * @param string|null              $description An optional further description of the search result.
     * @param User|null                $creator     An optional creator of this search result.
     * @param array<FolderModel>       $context     A folder structure which this result lives within:
     *                                              may be empty if there are no folders.
     * @param array                    $links       A set of supplementary contextual links for this search result,
     *                                              for actions which the search user may perform on or for that result.
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $url,
        public string $type,
        public ?Alaress_Date_Format $date = null,
        public ?string $meta = null,
        public ?string $image = null,
        public ?string $icon = null,
        public ?string $description = null,
        public ?User $creator = null,
        public array $context = [],
        public array $links = [],
    ) {
    }
}
