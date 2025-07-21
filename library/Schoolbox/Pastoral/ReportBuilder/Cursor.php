<?php

namespace Schoolbox\Pastoral\ReportBuilder;

use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\User\Translator\Author\ModelToArray;
use ValueError;

class Cursor implements ICursor
{
    /**
     * Maximum number of results returnable from the cursor at once:
     * IMO 20 results is a sensible maximum.
     */
    public const LIMIT_MAX = 20;

    /**
     * A sensible default number of results to return from the cursor at once:
     * IMO the maximum is a sensible default.
     */
    public const LIMIT_DEFAULT = self::LIMIT_MAX;

    public function __construct(
        protected PastoralReport $report,
        protected ModelToArray $userTranslator,
        protected ?int $limit = self::LIMIT_DEFAULT,
    ) {
        if ($this->limit > self::LIMIT_MAX) {
            throw new ValueError(sprintf('Limit must be at most %d: %d provided', self::LIMIT_MAX, $this->limit));
        }
    }

    public function next(Collection $collection): ?string
    {
        // TODO: Implement next() method.
        return null;
    }

    public function current(Collection $collection): ?string
    {
        // TODO: Implement current() method.
        return null;
    }

    public function retrieveResultCount(): ?int
    {
        // TODO: Implement retrieveResultCount() method.
        return null;
    }

    public function retrieveResults(?string $encodedCursor): Collection
    {
        // TODO: Implement retrieveResults() method.
        return new Collection();
    }
}
