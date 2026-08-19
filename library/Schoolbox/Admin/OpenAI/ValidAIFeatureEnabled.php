<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI;

use Schoolbox\IConfigLoader;

/**
 * Decide if a particular AI feature is enabled
 */
class ValidAIFeatureEnabled
{
    /**
     * @param IConfigLoader $configLoader
     */
    public function __construct(private IConfigLoader $configLoader)
    {
    }

    /**
     * @param string $featureConfigName (ConfigMapper::NAME_AI_*)
     * @return bool
     */
    public function isSatisfiedBy(string $featureConfigName): bool
    {
        $aiConfig = new \Schoolbox\Admin\OpenAI\Config();
        if (!in_array($featureConfigName, Config::ALL_MODULES, true)) {
            return false;
        }

        $featureConfig = $aiConfig->decode((string)$this->configLoader->get($featureConfigName));

        return $aiConfig->isFeatureEnabled($featureConfig, $featureConfigName, $this->configLoader);
    }
}
