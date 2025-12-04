<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Presidio\Anonymiser;

/**
 * Hash anonymiser: replaces anonymised content with hashed content.
 */
class Hash implements IAnonymiser
{
    /**
     * The type to put in the payload.
     */
    private const TYPE = 'hash';

    /**
     * Constructor
     *
     * @param HashType $hashType The hash type to use.
     */
    public function __construct(private HashType $hashType = HashType::Sha256)
    {
    }

    /**
     * @inheritdoc
     */
    public function buildPayload(): array
    {
        return [
            'type' => self::TYPE,
            'hash_type' => $this->hashType->value,
        ];
    }
}
