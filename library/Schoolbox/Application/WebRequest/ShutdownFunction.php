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
    private       $function;
    private array $args;

    public function __construct(callable $function, ...$args)
    {
        $this->function = $function;
        $this->args     = $args;
    }

    public function __invoke()
    {
        call_user_func($this->function, $this->args);
    }
}
