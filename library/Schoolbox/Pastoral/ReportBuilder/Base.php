<?php

namespace Schoolbox\Pastoral\ReportBuilder;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
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

    public function __construct(
        protected Helper $helper,
        protected ModelToArray $userTranslator,
        protected UserMapper $userMapper,
        protected PastoralMapper $pastoralMapper,
        protected string $resultType,
        protected ?string $sortBy,
        protected ?string $order = self::ORDER_DIRECTION_DEFAULT,
        protected int $limit = self::LIMIT_DEFAULT,
        protected ?ICodec $codec = null,
    ) {
        $this->codec ??= new Base64();

        /* Set a maximum number of results should be returned otherwise reject
         */
        if ($this->limit > self::LIMIT_MAX) {
            throw new ValueError(
                sprintf('Limit must be at most %d: %d provided', self::LIMIT_MAX, $this->limit),
                self::BAD_REQUEST_ERROR_CODE
            );
        }

        /* Set a minimum number of results should be returned otherwise reject
         */
        if ($this->limit < self::LIMIT_MIN) {
            throw new ValueError(
                sprintf('Limit must be at least %d: %d provided', self::LIMIT_MIN, $this->limit),
                self::BAD_REQUEST_ERROR_CODE
            );
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
}
