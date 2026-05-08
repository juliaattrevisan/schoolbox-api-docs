<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Learning\Moment\Feed\Hydrator;
use Schoolbox\Learning\Moment\Feed\Paginator;

/**
 * Tests the feed paginator's handling of rows hidden during hydration.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(Paginator::class)]
class PaginatorTest extends TestCase
{
    /**
     * Hidden raw rows should not consume slots in the returned visible page.
     *
     * @return void
     */
    public function testHiddenRowsDoNotConsumeVisiblePageLimit(): void
    {
        $cursor = self::buildCursor([
            null => [
                self::rawResult(1),
                self::rawResult(2),
            ],
            'cursor-2' => [
                self::rawResult(3),
                self::rawResult(4),
            ],
            'cursor-4' => [
                self::rawResult(5),
            ],
            'cursor-5' => [],
        ]);
        $hydrator = self::buildHydrator([3, 4, 5]);

        $page = (new Paginator($cursor, $hydrator, 2))->retrievePage(null);

        self::assertSame([3, 4], self::resultIds($page->getData()));
        self::assertSame(2, $page->getCount());
        self::assertNull($page->getCurrentCursor());
        self::assertSame('cursor-4', $page->getNextCursor());
    }

    /**
     * Retrieving a page should not perform a second full scan just to calculate an exact visible total.
     *
     * @return void
     */
    public function testRetrievingPageDoesNotScanAllResultsToCountVisibleResults(): void
    {
        $retrievedCursors = [];
        $cursor = self::buildCursor(
            [
                null => [
                    self::rawResult(1),
                    self::rawResult(2),
                ],
                'cursor-2' => [
                    self::rawResult(3),
                    self::rawResult(4),
                ],
                'cursor-4' => [
                    self::rawResult(5),
                ],
                'cursor-5' => [],
            ],
            /**
             * Records each cursor retrieved by the test cursor.
             */
            static function (?string $cursor) use (&$retrievedCursors): void {
                $retrievedCursors[] = $cursor;
            }
        );
        $hydrator = self::buildHydrator([1, 2, 3, 4, 5]);

        $page = (new Paginator($cursor, $hydrator, 2))->retrievePage(null);

        self::assertSame([1, 2], self::resultIds($page->getData()));
        self::assertSame(2, $page->getCount());
        self::assertSame([null, 'cursor-2'], $retrievedCursors);
    }

    /**
     * If no later visible rows exist, the page should not expose a next cursor.
     *
     * @return void
     */
    public function testNextCursorIsNullWhenOnlyHiddenRowsRemain(): void
    {
        $cursor = self::buildCursor([
            null => [
                self::rawResult(1),
                self::rawResult(2),
            ],
            'cursor-2' => [
                self::rawResult(3),
                self::rawResult(4),
            ],
            'cursor-4' => [],
        ]);
        $hydrator = self::buildHydrator([1, 2]);

        $page = (new Paginator($cursor, $hydrator, 2))->retrievePage(null);

        self::assertSame([1, 2], self::resultIds($page->getData()));
        self::assertSame(2, $page->getCount());
        self::assertNull($page->getNextCursor());
    }

    /**
     * Builds a raw cursor row for the test cursor.
     *
     * @param int $id
     * @return array{id: int, cursor: string}
     */
    private static function rawResult(int $id): array
    {
        return [
            'id' => $id,
            'cursor' => 'cursor-' . $id,
        ];
    }

    /**
     * Extracts object IDs from hydrated test results.
     *
     * @param Collection $results
     * @return int[]
     */
    private static function resultIds(Collection $results): array
    {
        return array_map(
            static fn (array $result): int => $result['object']['id'],
            $results->getAll()
        );
    }

    /**
     * Builds a cursor test double with fixed pages keyed by cursor value.
     *
     * @param array<string|null, array<int, array{id: int, cursor: string}>> $pages
     * @param \Closure|null $onRetrieve
     * @return ICursor
     */
    private static function buildCursor(array $pages, ?\Closure $onRetrieve = null): ICursor
    {
        /**
         * Cursor test double that serves fixed result pages keyed by cursor value.
         */
        return new class ($pages, $onRetrieve) implements ICursor {
            /**
             * Constructor.
             *
             * @param array<string|null, array<int, array{id: int, cursor: string}>> $pages
             * @param \Closure|null $onRetrieve
             */
            public function __construct(
                private array $pages,
                private ?\Closure $onRetrieve
            ) {
            }

            /**
             * Returns a cursor built from the last raw row in the collection.
             *
             * @param Collection $collection
             * @return string|null
             */
            public function next(Collection $collection): ?string
            {
                if ($collection->count() === 0) {
                    return null;
                }

                return $collection->last()['cursor'];
            }

            /**
             * Current cursors are not needed by these tests.
             *
             * @param Collection $collection
             * @return string|null
             */
            public function current(Collection $collection): ?string
            {
                return null;
            }

            /**
             * Raw cursor counts are intentionally unused by the paginator.
             *
             * @return int|null
             */
            public function retrieveResultCount(): ?int
            {
                return null;
            }

            /**
             * Returns the raw rows configured for the supplied cursor.
             *
             * @param string|null $encodedCursor
             * @return Collection
             */
            public function retrieveResults(?string $encodedCursor): Collection
            {
                if ($this->onRetrieve !== null) {
                    ($this->onRetrieve)($encodedCursor);
                }

                return new Collection($this->pages[$encodedCursor] ?? []);
            }
        };
    }

    /**
     * Builds a hydrator test double that filters rows according to visible IDs.
     *
     * @param int[] $visibleIds
     * @return Hydrator
     */
    private static function buildHydrator(array $visibleIds): Hydrator
    {
        /**
         * Hydrator test double that filters out rows whose IDs are not visible.
         */
        return new class ($visibleIds) extends Hydrator {
            /**
             * Constructor.
             *
             * @param int[] $visibleIds
             */
            public function __construct(private array $visibleIds)
            {
            }

            /**
             * Hydrates only rows whose IDs are configured as visible.
             *
             * @param Collection $results
             * @return Collection
             */
            public function hydrateResults(Collection $results): Collection
            {
                $hydrated = new Collection();

                foreach ($results as $i => $result) {
                    if (!in_array($result['id'], $this->visibleIds, true)) {
                        continue;
                    }

                    $hydrated[$i] = [
                        'discriminator' => 'test',
                        'object' => [
                            'id' => $result['id'],
                        ],
                    ];
                }

                return $hydrated;
            }
        };
    }
}
