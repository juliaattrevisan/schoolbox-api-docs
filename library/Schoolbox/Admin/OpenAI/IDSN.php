<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI;

use SensitiveParameter;

interface IDSN
{
    /**
     * Take a DSN string and break it down into its constituent parts
     *
     * @param string $dsn
     * @return array
     */
    public function decode(#[SensitiveParameter] string $dsn): array;


    /**
     * Take an array of DSN parts and assemble it into a serialized DSN string equivalent
     *
     * @param array $parts
     * @return string
     */
    public function encode(#[SensitiveParameter] array $parts): string;
}
