<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Admin\OpenAI\ValidAIFeatureEnabled;
use Schoolbox\User;
use Schoolbox\IConfigLoader;

/**
 * Checks that a given AI feature is enabled
 */
class IsAIFeatureEnabled implements IAuthorizationRule
{
    /**
     * @param string $featureConfigName
     * @param IConfigLoader $configLoader
     */
    public function __construct(private string $featureConfigName, private IConfigLoader $configLoader)
    {
    }


    /**
     * @inheritdoc
     */
    public function authorize(array $params = [], ?User $user = null): void
    {
        if (!(new ValidAIFeatureEnabled($this->configLoader)->isSatisfiedBy($this->featureConfigName))) {
            throw new Unauthorized(\sprintf(
                'AI feature %s is not enabled',
                $this->featureConfigName
            ));
        }
    }
}
