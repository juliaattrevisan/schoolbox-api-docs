<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Presidio\Anonymiser;

use UnexpectedValueException;

/**
 * Decrypt de-anonymiser: replaces encrypted anonymised content with its
 * original form.
 */
class Decrypt implements IAnonymiser
{
    /**
     * The type to put in the payload.
     */
    private const TYPE = 'decrypt';

    /**
     * Constructor
     *
     * @param string $key The cryptographic key: must be 128, 192 or 256 bits.
     * @throws UnexpectedValueException if it isn't
     */
    public function __construct(private string $key)
    {
        $strlen = strlen($this->key);
        if (!in_array($strlen, [16, 24, 32])) {
            throw new UnexpectedValueException('Key length must be 128, 192 or 256 bits');
        }
    }

    /**
     * @inheritdoc
     */
    public function buildPayload(): array
    {
        return [
            'type' => self::TYPE,
            'key'  => $this->key,
        ];
    }
}
