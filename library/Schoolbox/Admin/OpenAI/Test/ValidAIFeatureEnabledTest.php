<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\OpenAI\Config;
use Schoolbox\Admin\OpenAI\ValidAIFeatureEnabled;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Editor\OpenAI\Helper;

class ValidAIFeatureEnabledTest extends TestCase
{
    /**
     * @return void
     */
    public function testFeatureRequiresEnabledPlanAndProvider(): void
    {
        $featureConfig = json_encode([
            Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OPENAI],
        ], JSON_THROW_ON_ERROR);

        $enabled = new ValidAIFeatureEnabled(new ArrayLoader([
            ConfigMapper::NAME_AI_TEXT_CHAT_ENABLED => 'true',
            ConfigMapper::NAME_AI_TEXT_CHAT => $featureConfig,
        ]));
        $this->assertTrue($enabled->isSatisfiedBy(ConfigMapper::NAME_AI_TEXT_CHAT));

        $planDisabled = new ValidAIFeatureEnabled(new ArrayLoader([
            ConfigMapper::NAME_AI_TEXT_CHAT_ENABLED => 'false',
            ConfigMapper::NAME_AI_TEXT_CHAT => $featureConfig,
        ]));
        $this->assertFalse($planDisabled->isSatisfiedBy(ConfigMapper::NAME_AI_TEXT_CHAT));

        $providerDisabled = new ValidAIFeatureEnabled(new ArrayLoader([
            ConfigMapper::NAME_AI_TEXT_CHAT_ENABLED => 'true',
            ConfigMapper::NAME_AI_TEXT_CHAT => json_encode([
                Config::FIELD_PROVIDER => ['value' => Helper::ENDPOINT_TYPE_OFF],
            ], JSON_THROW_ON_ERROR),
        ]));
        $this->assertFalse($providerDisabled->isSatisfiedBy(ConfigMapper::NAME_AI_TEXT_CHAT));
    }

    /**
     * @return void
     */
    public function testUnknownFeatureIsNotEnabled(): void
    {
        $enabled = new ValidAIFeatureEnabled(new ArrayLoader());

        $this->assertFalse($enabled->isSatisfiedBy('not-an-ai-feature'));
    }

    /**
     * @return void
     */
    public function testPlanFlagsCoverEveryConfiguredAiFeatureAndRubricGeneration(): void
    {
        $this->assertSame(ConfigMapper::NAME_AI_TEXT_CHAT_ENABLED, Config::PLAN_FLAGS[ConfigMapper::NAME_AI_TEXT_CHAT]);
        $this->assertSame(ConfigMapper::NAME_AI_LESSONPLAN_ENABLED, Config::PLAN_FLAGS[ConfigMapper::NAME_AI_LESSONPLAN]);
        $this->assertSame(ConfigMapper::NAME_AI_DIFFERENTIATION_ENABLED, Config::PLAN_FLAGS[ConfigMapper::NAME_AI_DIFFERENTIATION]);
        $this->assertSame(ConfigMapper::NAME_AI_DIALOGAGENT_ENABLED, Config::PLAN_FLAGS[ConfigMapper::NAME_AI_DIALOGAGENT]);
        $this->assertSame(ConfigMapper::NAME_AI_IMAGE_GENERATION_ENABLED, Config::PLAN_FLAGS[ConfigMapper::NAME_AI_IMAGE_GENERATION]);
        $this->assertSame(ConfigMapper::NAME_AI_LEARNING_ACTIVITY_CUSTOMISATION_ENABLED, Config::PLAN_FLAGS[ConfigMapper::NAME_AI_LEARNING_ACTIVITY_CUSTOMISATION]);
        $this->assertSame(ConfigMapper::NAME_AI_MODERATION_AGENT_ENABLED, Config::PLAN_FLAGS[ConfigMapper::NAME_AI_MODERATION_AGENT]);
        $this->assertSame(ConfigMapper::NAME_AI_RUBRIC_GENERATION_ENABLED, Config::PLAN_FLAGS[ConfigMapper::NAME_AI_RUBRIC_GENERATION]);
    }
}
