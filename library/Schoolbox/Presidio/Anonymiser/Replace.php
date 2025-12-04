<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Presidio\Anonymiser;

/**
 * Replacement anonymiser: replaces anonymised content with a new value.
 */
class Replace implements IAnonymiser
{
    /**
     * The type to put in the payload.
     */
    private const TYPE = 'replace';

    /**
     * The default new value to use: if this is empty, this will default to
     * `<ENTITY_TYPE>`, where `ENTITY_TYPE` is the type of thing which Presidio
     * replaced (e.g. PHONE_NUMBER).
     */
    public const DEFAULT_NEW_VALUE = '';

    /**
     * Constructor
     *
     * @param string $newValue The value to replace anonymised content with.
     */
    public function __construct(private string $newValue = self::DEFAULT_NEW_VALUE)
    {
    }

    /**
     * @inheritdoc
     */
    public function buildPayload(): array
    {
        return [
            'type' => self::TYPE,
            'new_value' => $this->newValue,
        ];
    }
}
