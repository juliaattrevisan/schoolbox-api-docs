<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\OpenAi\Session\Feature;

/**
 * Unit tests for the {@see Feature} enum.
 */
class FeatureTest extends TestCase
{
    /**
     * Guards that the enum values stay in sync with the ai_session.feature DB enum.
     */
    public function testValuesMatchDatabaseEnum(): void
    {
        $values = array_column(Feature::cases(), 'value');

        $this->assertEqualsCanonicalizing([
            'chat',
            'lessonplan',
            'image',
            'differentiation',
            'dialogagent',
            'learningactivitycustomisation',
            'aimoderationagent',
        ], $values);
    }

    /**
     * Confirms cases can be resolved from their backing string value.
     */
    public function testCanBuildFromValue(): void
    {
        $this->assertSame(Feature::CHAT, Feature::from('chat'));
        $this->assertSame(Feature::AI_MODERATION_AGENT, Feature::from('aimoderationagent'));
    }
}
