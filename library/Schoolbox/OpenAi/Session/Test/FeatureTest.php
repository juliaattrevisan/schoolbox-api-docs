<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\OpenAi\Feature;

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
     * Only features with AI moderation support expose that capability.
     */
    public function testSupportsAiModeration(): void
    {
        $this->assertTrue(Feature::DIALOG_AGENT->supportsAiModeration());

        foreach (Feature::cases() as $feature) {
            if (Feature::DIALOG_AGENT === $feature) {
                continue;
            }

            $this->assertFalse($feature->supportsAiModeration());
        }
    }
}
