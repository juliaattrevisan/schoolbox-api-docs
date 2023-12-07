<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Cursor;

use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Search\Api\SearchFilter\DataObject as FilterDataObject;
use Schoolbox\Search\Api\Source\ISource;
use Schoolbox\User;

/**
 * Cursor for the search API.
 *
 * Retrieves only raw results: these raw results will be hydrated in a later
 * step, and then filtered to results which are applicable to the current user.
 */
class Cursor implements ICursor
{
    /**
     * A codec for the cursor as passed in the query string.
     */
    private Codec $codec;

    /**
     * Constructor
     *
     * @param User             $loggedInUser The logged-in user.
     * @param ISource          $source       A source for search results.
     * @param string           $keyword      A keyword to search for.
     * @param FilterDataObject $filter       A set of filters to apply to the search.
     * @param int              $limit        The max number of results to fetch.
     */
    public function __construct(
        private User $loggedInUser,
        private ISource $source,
        private string $keyword,
        private FilterDataObject $filter,
        private int $limit,
    ) {
        $this->codec = new Codec();
    }

    /**
     * @inheritdoc
     */
    public function next(Collection $collection): ?string
    {
        // As usual: if the collection is empty, there is no next
        if ($collection->count() === 0) {
            return null;
        }

        // Find the last item in the collection: get a pointer to that
        // Expect `$last` to be a Result data object
        return $this->codec->encode($collection->last());
    }

    /**
     * This returns null for now, until there's a need for the current cursor.
     *
     * @return null
     */
    public function current(Collection $collection): ?string
    {
        return null;
    }

    /**
     * This cursor cannot return an accurate result count, because its results
     * are later filtered: thus, it always returns null.
     *
     * @return null
     */
    public function retrieveResultCount(): ?int
    {
        // null for now
        return null;
    }

    /**
     * @param string|null $encodedCursor
     * @return GenericCollection<RawResult>
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        /** @var DataObject|null $cursor */
        $cursor = $this->codec->decode($encodedCursor);
        return new GenericCollection(
            RawResult::class,
            $this->source->search(
                $this->loggedInUser,
                $this->keyword,
                $this->filter,
                $cursor,
                $this->limit,
            )
        );
    }
}
