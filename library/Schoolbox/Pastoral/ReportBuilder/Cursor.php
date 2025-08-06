<?php

namespace Schoolbox\Pastoral\ReportBuilder;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Collection;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\User\Translator\Author\ModelToArray;
use Schoolbox\UserMapper;
use ValueError;

class Cursor implements ICursor
{
    /**
     * Maximum number of results returnable from the cursor at once:
     * IMO 20 results is a sensible maximum.
     */
    public const LIMIT_MAX = 20;

    /**
     * Minimum number of results returnable and should really be more than 0
     */
    public const LIMIT_MIN = 1;

    /**
     * A sensible default number of results to return from the cursor at once:
     * IMO the maximum is a sensible default.
     */
    public const LIMIT_DEFAULT = self::LIMIT_MAX;

    public const RESULT_TYPE_POINTS = 'points';
    public const RESULT_TYPE_RECORDS = 'records';
    public const SORT_BY_DEFAULT = 'user';
    public const ORDER_DIRECTION_DEFAULT = self::ORDER_DIRECTION_ASC;
    public const ORDER_DIRECTION_ASC = 'ASC';
    public const ORDER_DIRECTION_DESC = 'DESC';
    /**
     * A sane value used for comparison of NULL value in the points when querying db
     */
    public const MIN_SORT_BY_VALUE = -99999999;

    public function __construct(
        protected Helper $helper,
        protected ModelToArray $userTranslator,
        protected UserMapper $userMapper,
        protected PastoralMapper $pastoralMapper,
        protected string $resultType,
        protected ?string $sortBy = self::SORT_BY_DEFAULT,
        protected ?string $order = self::ORDER_DIRECTION_DEFAULT,
        protected int $limit = self::LIMIT_DEFAULT,
        protected ?ICodec $codec = null,
    ) {
        $this->codec ??= new Base64();

        /* Set a maximum number of results should be returned otherwise reject
         */
        if ($this->limit > self::LIMIT_MAX) {
            throw new ValueError(sprintf('Limit must be at most %d: %d provided', self::LIMIT_MAX, $this->limit));
        }

        /* Set a minimum number of results should be returned otherwise reject
         */
        if ($this->limit < self::LIMIT_MIN) {
            throw new ValueError(sprintf('Limit must be at least %d: %d provided', self::LIMIT_MIN, $this->limit));
        }

        /* Set default if sorting is set as null
         */
        if (null === $this->sortBy) {
            $this->sortBy = self::SORT_BY_DEFAULT;
        }

        /* If not set or it is assigned to other values should set back to default direction
         */
        if (
            null === $this->order ||
            (
                strcasecmp($this->order, self::ORDER_DIRECTION_ASC) &&
                strcasecmp($this->order, self::ORDER_DIRECTION_DESC)
            )
        ) {
            $this->order = self::ORDER_DIRECTION_DEFAULT;
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
