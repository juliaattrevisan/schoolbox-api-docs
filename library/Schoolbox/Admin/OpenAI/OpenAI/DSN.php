<?php
/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\OpenAI;

use Schoolbox\Admin\OpenAI\IDSN;
use SensitiveParameter;

class DSN implements IDSN
{

    /**
     * @inheritDoc
     */
    public function decode(#[SensitiveParameter] string $dsn): array
    {
        // TODO: Implement decode() method.
    }

    /**
     * @inheritDoc
     */
    public function encode(#[SensitiveParameter] array $parts): string
    {
        // TODO: Implement encode() method.
    }
}