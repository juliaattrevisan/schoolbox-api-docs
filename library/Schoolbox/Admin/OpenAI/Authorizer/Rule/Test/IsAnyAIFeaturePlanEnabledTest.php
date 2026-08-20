<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\Authorizer\Rule\Test;

use Alaress\Authorizer\Failure\Unauthorized;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\OpenAI\Authorizer\Rule\IsAnyAIFeaturePlanEnabled;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Config\ConfigMapper;

class IsAnyAIFeaturePlanEnabledTest extends TestCase
{
    /**
     * @return void
     */
    public function testRejectsAccessWhenNoAiPlanFeatureIsEnabled(): void
    {
        $rule = new IsAnyAIFeaturePlanEnabled(new ArrayLoader([
            ConfigMapper::NAME_AI_TEXT_CHAT_ENABLED => 'false',
            ConfigMapper::NAME_AI_LESSONPLAN_ENABLED => 'false',
            ConfigMapper::NAME_AI_DIFFERENTIATION_ENABLED => 'false',
            ConfigMapper::NAME_AI_DIALOGAGENT_ENABLED => 'false',
            ConfigMapper::NAME_AI_IMAGE_GENERATION_ENABLED => 'false',
            ConfigMapper::NAME_AI_LEARNING_ACTIVITY_CUSTOMISATION_ENABLED => 'false',
            ConfigMapper::NAME_AI_MODERATION_AGENT_ENABLED => 'false',
            ConfigMapper::NAME_AI_RUBRIC_GENERATION_ENABLED => 'false',
        ]));

        $this->expectException(Unauthorized::class);
        $rule->authorize();
    }

    /**
     * @return void
     */
    public function testAllowsAccessWhenAnAiPlanFeatureIsEnabled(): void
    {
        $rule = new IsAnyAIFeaturePlanEnabled(new ArrayLoader([
            ConfigMapper::NAME_AI_TEXT_CHAT_ENABLED => 'true',
        ]));

        $rule->authorize();
        $this->addToAssertionCount(1);
    }
}
