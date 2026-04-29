<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed;

use InvalidArgumentException;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;

/**
 * Builds feed pages from raw cursor rows after applying hydrator visibility checks.
 */
class Paginator
{
    /**
     * Constructor.
     *
     * @param ICursor  $cursor
     * @param Hydrator $hydrator
     * @param int      $limit
     */
    public function __construct(
        private ICursor $cursor,
        private Hydrator $hydrator,
        private int $limit,
    ) {
        if ($this->limit < 1) {
            throw new InvalidArgumentException('Limit must be greater than zero.');
        }
    }

    /**
     * Retrieves one feed page containing only rows visible after hydration.
     *
     * @param string|null $encodedCursor
     * @return Page
     */
    public function retrievePage(?string $encodedCursor): Page
    {
        [$data, $nextCursor] = $this->retrieveVisibleResultsAndNextCursor($encodedCursor);

        return new Page(
            $data,
            $data->count(),
            $encodedCursor,
            $nextCursor
        );
    }

    /**
     * Retrieves visible feed items and the cursor for the next visible page.
     *
     * @param string|null $encodedCursor
     * @return array{Collection, string|null}
     */
    private function retrieveVisibleResultsAndNextCursor(?string $encodedCursor): array
    {
        $visibleResults = new Collection();
        $visibleRawResults = new Collection();
        $batchCursor = $encodedCursor;

        while (true) {
            $rawResults = $this->cursor->retrieveResults($batchCursor);
            if ($rawResults->count() === 0) {
                return [$visibleResults, null];
            }

            $hydratedResults = $this->hydrator->hydrateResults($rawResults);

            foreach ($hydratedResults as $i => $hydratedResult) {
                if ($visibleResults->count() >= $this->limit) {
                    return [$visibleResults, $this->cursor->next($visibleRawResults)];
                }

                $visibleResults->add($hydratedResult);
                $visibleRawResults->add($rawResults[$i]);
            }

            $nextBatchCursor = $this->cursor->next($rawResults);
            if ($nextBatchCursor === null || $nextBatchCursor === $batchCursor) {
                return [$visibleResults, null];
            }

            $batchCursor = $nextBatchCursor;
        }
    }
}
