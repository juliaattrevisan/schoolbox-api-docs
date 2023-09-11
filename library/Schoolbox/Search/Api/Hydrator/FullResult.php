<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Hydrator;

use Alaress_Date_Format;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\User;

/**
 * Data object for a single fully-hydrated search result.
 *
 * This is created from a `\Schoolbox\Search\Api\Hydrator\IHydrator::hydrate`
 * implementation.
 */
class FullResult
{
    /**
     * Constructor
     *
     * @param RawResult                $rawResult   The raw result from which this full result was derived.
     *                                              Required so that a cursor may be built to this raw result.
     * @param int                      $id          The ID of the search result subject.
     * @param string                   $title       The title of the search result.
     * @param string                   $url         A link to the search result.
     * @param string                   $type        The type of search result.
     * @param float                    $relevance   The relevance score of the search result.
     * @param Alaress_Date_Format|null $date        The date (if any) of the search result.
     * @param FolderModel|null         $folder      The folder (if any) the search result belongs in.
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
        public RawResult $rawResult,
        public int $id,
        public string $title,
        public string $url,
        public string $type,
        public float $relevance,
        public ?Alaress_Date_Format $date = null,
        public ?FolderModel $folder = null,
        public ?string $meta = null,
        public ?string $image = null,
        public ?string $icon = null,
        public ?string $description = null,
        public ?User $creator = null,
        public array $context = [],
        public array $links = [],
    ) {
        // We don't want our search results to be polluted by ugly trailing newlines for example, so we trim.
        foreach (get_object_vars($this) as $key => $value) {
            if (in_array($key, ["title", "url", "type", "meta", "description"]) && $value !== null) {
                $this->$key = trim($this->$key);
            }
        }
    }
}
