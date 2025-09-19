<?php

namespace Schoolbox\Pastoral\ReportBuilder;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Schoolbox\Collection;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\User\Translator\Author\ModelToArray;
use Schoolbox\UserMapper;
use ValueError;

class Base
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
    public const SORT_BY_STUDENT_DEFAULT = 'user';
    public const SORT_BY_TYPE_DEFAULT = 'type_subtype';
    public const ORDER_DIRECTION_DEFAULT = self::ORDER_DIRECTION_ASC;
    public const ORDER_DIRECTION_ASC = 'ASC';
    public const ORDER_DIRECTION_DESC = 'DESC';
    /**
     * A sane value used for comparison of NULL value in the points when querying db
     */
    public const MIN_SORT_BY_VALUE = -99999999;
    public const BAD_REQUEST_ERROR_CODE = 400;
    public const MIN_DATE_VALUE = '1970-01-01 00:00:00';

    public function __construct(
        protected Helper $helper,
        protected ModelToArray $userTranslator,
        protected UserMapper $userMapper,
        protected PastoralMapper $pastoralMapper,
        protected string $resultType,
        protected ?string $sortBy,
        protected ?string $order = self::ORDER_DIRECTION_DEFAULT,
        protected ?int $limit = self::LIMIT_DEFAULT,
        protected ?ICodec $codec = null,
    ) {
        $this->codec ??= new Base64();

        if (null !== $this->limit) {
            /* Set a maximum number of results should be returned otherwise reject
             */
            if ($this->limit > static::LIMIT_MAX) {
                throw new ValueError(
                    sprintf('Limit must be at most %d: %d provided', static::LIMIT_MAX, $this->limit),
                    self::BAD_REQUEST_ERROR_CODE
                );
            }

            /* Set a minimum number of results should be returned otherwise reject
             */
            if ($this->limit < static::LIMIT_MIN) {
                throw new ValueError(
                    sprintf('Limit must be at least %d: %d provided', static::LIMIT_MIN, $this->limit),
                    self::BAD_REQUEST_ERROR_CODE
                );
            }
        }

        /* If not set or it is assigned to other values should set back to default direction
         */
        if (
            null === $this->order ||
            (
                strcasecmp($this->order, static::ORDER_DIRECTION_ASC) &&
                strcasecmp($this->order, static::ORDER_DIRECTION_DESC)
            )
        ) {
            $this->order = static::ORDER_DIRECTION_DEFAULT;
        }
    }

    protected function getTotalCount(): Collection
    {
        return new Collection();
    }

    /**
     * Get the total count row for the tab type
     *
     * @return array
     */
    public function getTotalCountRow(): array
    {
        /* Return null if all the values are null otherwise return the total count
         */
        return array_merge(
            [static::DEFAULT_SORTING_KEY => ucwords("total {$this->resultType}")],
            $this->getTotalCount()
                 ->map(fn($count) => is_numeric($count) ? $count / 1 : null)->getAll()
        );
    }
}
