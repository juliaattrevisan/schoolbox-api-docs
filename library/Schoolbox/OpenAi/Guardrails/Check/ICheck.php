<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Guardrails\Check;

use Schoolbox\Application\Exception\Exception as SchoolboxException;

interface ICheck
{
    /**
     * The unique identifier for the guardrail check
     * @return string
     */
    public static function getName(): string;

    /**
     * Human-readable explanation of the guardrail's purpose
     * @return string
     */
    public static function getDescription(): string;

    /**
     * Perform the check
     *
     * @param string $inputText The text to analyse
     * @return Outcome The outcome of the check
     * @throws SchoolboxException if the check could not be performed
     */
    public function check(string $inputText): Outcome;
}
