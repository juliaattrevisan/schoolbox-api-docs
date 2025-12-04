<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Presidio\Anonymiser;

use UnexpectedValueException;

/**
 * Encrypt anonymiser: replaces anonymised content with an encrypted form.
 */
class Encrypt implements IAnonymiser
{
    /**
     * The type to put in the payload.
     */
    private const TYPE = 'encrypt';

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
            throw new UnexpectedValueException(
                sprintf(
                    'Key length of %d bits is invalid: must be 128, 192 or 256 bits',
                    $strlen * 8,
                ),
            );
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
