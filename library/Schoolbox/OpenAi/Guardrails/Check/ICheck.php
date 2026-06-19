<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Guardrails\Check;

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\OpenAi\Session\Log;

interface ICheck
{
    /**
     * The unique identifier for the guardrail check
     * @return string
     */
    public static function getName(): string;

    /**
     * Perform the check
     *
     * @param string $inputText The text to analyse
     * @param Log\Model $log The AI log associated with this check, if applicable - this is solely used for the AI-based checks to add the payload...
     * @return Outcome The outcome of the check
     * @throws SchoolboxException if the check could not be performed
     */
    public function check(string $inputText, Log\Model $log): Outcome;
}
