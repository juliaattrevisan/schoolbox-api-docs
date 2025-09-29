<?php
/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */
declare(strict_types=1);

namespace Admin\OpenAI\Azure;

use Schoolbox\Admin\OpenAI\IDSN;
use SensitiveParameter;

class DNS implements IDSN
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