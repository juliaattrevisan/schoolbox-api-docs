<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\DialogAgent;

use Schoolbox\Admin\OpenAI\ValidAIFeatureEnabled;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

/**
 * Decide if dialog agent is enabled
 *
 * Currently this just a shortcut to ValidAIFeatureEnabled for dialog agent,
 * but could be extended in future if for example a dedicated dialog agent config was added
 */
class ValidDialogAgentEnabled
{
    /**
     * @param IConfigLoader $configLoader
     */
    public function __construct(private IConfigLoader $configLoader)
    {
    }

    /**
     * @return bool
     */
    public function isSatisfied(): bool
    {

        return (new ValidAIFeatureEnabled($this->configLoader))->isSatisfiedBy(ConfigMapper::NAME_AI_DIALOGAGENT);
    }
}
