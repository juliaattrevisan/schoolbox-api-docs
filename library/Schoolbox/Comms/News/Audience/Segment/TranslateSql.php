<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Segment;

/**
 * Strictly-typed combination of a translate SQL fragment plus bindings used in the query
 */
class TranslateSql
{
    /** @var string */
    private $sql;

    /** @var array */
    private $params;


    /**
     * @param string $sql
     * @param array  $params
     */
    public function __construct(string $sql, array $params)
    {
        $this->sql    = $sql;
        $this->params = $params;
    }


    /** @return string */
    public function getSql(): string
    {
        return $this->sql;
    }


    /** @return array */
    public function getParams(): array
    {
        return $this->params;
    }
}
