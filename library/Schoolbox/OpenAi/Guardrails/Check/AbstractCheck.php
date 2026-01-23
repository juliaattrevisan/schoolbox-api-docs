<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Guardrails\Check;

use Schoolbox\OpenAi\IClient;
use Schoolbox\User;

/**
 * Abstract base class for all guardrail checks
 */
abstract class AbstractCheck implements ICheck
{
    /**
     * @param IClient $client    An AI provider client (dependent on the check)
     * @param array   $config    Guardrail check config (dependent on the check)
     * @param ?User   $user      The user making the request, if any.
     * @param ?string $feature   An identifier for the feature being requested.
     * @param ?string $sessionId The session ID against which this request should be logged.
     */
    public function __construct(
        protected IClient $client,
        protected array $config,
        protected ?User $user = null,
        protected ?string $feature = null,
        protected ?string $sessionId = null,
    ) {
    }
}
