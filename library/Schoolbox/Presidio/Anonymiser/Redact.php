<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Presidio\Anonymiser;

/**
 * Redaction anonymiser: removes anonymised content completely.
 */
class Redact implements IAnonymiser
{
    /**
     * The type to put in the payload.
     */
    private const TYPE = 'redact';

    /**
     * @inheritdoc
     */
    public function buildPayload(): array
    {
        return [
            'type' => self::TYPE,
        ];
    }
}
