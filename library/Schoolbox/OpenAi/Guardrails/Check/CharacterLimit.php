<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Guardrails\Check;

use Schoolbox\Editor\OpenAI\Log;

/**
 * Guardrail check to flag requests that exceed a specified character limit.
 */
class CharacterLimit implements ICheck
{
    /**
     * The default limit to use if an agent does not specify a separate configuration
     */
    private const int DEFAULT_CHAR_LIMIT = 5000;

    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'Character Limit';
    }

    /**
     * Constructor
     *
     * @param int $charLimit  The character limit to enforce.
     */
    public function __construct(private int $charLimit = self::DEFAULT_CHAR_LIMIT)
    {
    }

    /**
     * @inheritDoc
     */
    public function check(string $inputText, Log\Model $log): Outcome
    {
        $length = mb_strlen($inputText);

        return new Outcome(
            $this,
            $length > $this->charLimit,
            ['length' => $length, 'limit' => $this->charLimit]
        );
    }
}
