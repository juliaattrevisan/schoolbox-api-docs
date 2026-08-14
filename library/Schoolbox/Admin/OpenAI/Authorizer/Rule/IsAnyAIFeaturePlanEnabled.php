<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\IConfigLoader;
use Schoolbox\User;

/**
 * Prevent access to the AI Services page when the plan does not enable a configurable AI feature.
 */
class IsAnyAIFeaturePlanEnabled implements IAuthorizationRule
{
    /**
     * @param IConfigLoader $configLoader
     */
    public function __construct(private IConfigLoader $configLoader)
    {
    }

    /**
     * Reject direct requests when the AI Services subnavigation is hidden by plan controls.
     *
     * @param array<string, mixed> $params
     * @param User|null $user
     * @return void
     */
    public function authorize(array $params = [], ?User $user = null): void
    {
        if (!(new Config())->hasEnabledPlanFeature($this->configLoader)) {
            throw new Unauthorized('No AI features are enabled for this plan.');
        }
    }
}
