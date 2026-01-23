<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Exception;

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\OpenAi\Guardrails\Check\ICheck;

/**
 * Exception raised when a guardrail flags the input as disallowed.
 */
class GuardrailFlagged extends SchoolboxException
{
    /**
     * Status code for all guardrail flagged exceptions
     */
    protected $httpStatusCode = 400;

    /**
     * Constructor
     *
     * @param ICheck $check       The guardrail check that was flagged
     * @param string $information Extra information to be included in the error
     */
    public function __construct(ICheck $check, string $information = '')
    {
        $message = \sprintf(
            "Your request didn't meet the expected guidelines (%s).",
            $check::getName(),
        );

        parent::__construct($message, $this->httpStatusCode);

        if ('' !== $information) {
            $this->information = $information;
        }
    }
}
