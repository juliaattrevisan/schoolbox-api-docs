<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application\WebRequest;

/**
 * Strongly-typed encapsulation of a shutdown function
 */
class ShutdownFunction
{
    /**
     * The function to call.
     *
     * @var callable
     */
    private $function;

    /**
     * The args to call `$this->function` with.
     */
    private array $args;

    /**
     * Constructor
     *
     * @param callable $function
     * @param mixed    ...$args
     */
    public function __construct(callable $function, ...$args)
    {
        $this->function = $function;
        $this->args     = $args;
    }

    /**
     * Call `$this->function(...$this->args)`.
     */
    public function __invoke()
    {
        call_user_func($this->function, $this->args);
    }
}
