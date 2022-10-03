<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Home;

/**
 * Strongly-typed SamlWidget encapsulation
 */
class SamlWidget
{
    private string $light;

    private string $message;

    public function __construct(string $light, string $message)
    {
        $this->light   = $light;
        $this->message = $message;
    }


    public function getLight(): string
    {
        return $this->light;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
