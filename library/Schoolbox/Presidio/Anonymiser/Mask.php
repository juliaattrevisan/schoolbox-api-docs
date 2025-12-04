<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Presidio\Anonymiser;

/**
 * Mask anonymiser: blots out characters in anonymised content with a masking
 * character
 */
class Mask implements IAnonymiser
{
    /**
     * The type to put in the payload.
     */
    private const TYPE = 'mask';

    /**
     * The default masking char to use.
     */
    public const DEFAULT_MASKING_CHAR = '*';

    /**
     * The default number of chars to mask.
     * (Will max out at the length of the anonymised content.)
     */
    public const DEFAULT_CHARS_TO_MASK = 1000;

    /**
     * Constructor
     *
     * @param string $maskingChar The char to replace characters in anonymised content with.
     * @param int    $charsToMask How many characters to replace.
     * @param bool   $fromEnd     If true, replace from the start of the content; otherwise from the end.
     */
    public function __construct(
        private string $maskingChar = self::DEFAULT_MASKING_CHAR,
        private int $charsToMask = self::DEFAULT_CHARS_TO_MASK,
        private bool $fromEnd = false,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function buildPayload(): array
    {
        return [
            'type'          => self::TYPE,
            'masking_char'  => $this->maskingChar,
            'chars_to_mask' => $this->charsToMask,
            'from_end'      => $this->fromEnd,
        ];
    }
}
