<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment;

use Alaress\Database\Predicate;

/**
 * Strongly-typed DTO to encapsulate a SQL join + predicate.
 */
class UserResolutionSql
{
    /** @var string */
    private $join;

    /** @var Predicate\Builder */
    private $pb;


    /**
     * @param string            $join
     * @param Predicate\Builder $pb
     */
    public function __construct(string $join, Predicate\Builder $pb)
    {
        $this->join = $join;
        $this->pb   = $pb;
    }


    /** @return string */
    public function getJoin(): string
    {
        return $this->join;
    }


    /** @return Predicate\Builder */
    public function getPb(): Predicate\Builder
    {
        return $this->pb;
    }
}
