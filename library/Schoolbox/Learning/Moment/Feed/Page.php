<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed;

use Schoolbox\Collection;

/**
 * Immutable value object containing one visible page of feed results and cursor metadata.
 */
class Page
{
    /**
     * Constructor.
     *
     * @param Collection  $data
     * @param int         $count
     * @param string|null $currentCursor
     * @param string|null $nextCursor
     */
    public function __construct(
        private Collection $data,
        private int $count,
        private ?string $currentCursor,
        private ?string $nextCursor,
    ) {
    }

    /**
     * Returns the visible feed items for this page.
     *
     * @return Collection
     */
    public function getData(): Collection
    {
        return $this->data;
    }

    /**
     * Returns the number of visible feed items in this page.
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Returns the cursor used to retrieve this page.
     *
     * @return string|null
     */
    public function getCurrentCursor(): ?string
    {
        return $this->currentCursor;
    }

    /**
     * Returns the cursor for the next visible page.
     *
     * @return string|null
     */
    public function getNextCursor(): ?string
    {
        return $this->nextCursor;
    }
}
