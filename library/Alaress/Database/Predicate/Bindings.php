<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Predicate;

/**
 * Strongly-typed placeholders and parameter bindings
 *
 * Most likely used to generate placeholders and parameters for sql IN() queries
 */
class Bindings
{

    /** @var string */
    private $placeholder;

    /** @var mixed[] */
    private $bindings;


    /**
     * @param string  $placeholder
     * @param mixed[] $bindings
     */
    public function __construct(string $placeholder, array $bindings)
    {
        $this->placeholder = $placeholder;
        $this->bindings    = $bindings;
    }


    /**
     * @return string
     */
    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }


    /**
     * Each array element index will be found as a token in the placeholder
     *
     * @return mixed[]
     */
    public function getParams(): array
    {
        return $this->bindings;
    }
}
