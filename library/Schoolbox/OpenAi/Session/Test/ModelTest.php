<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\OpenAi\Feature;
use Schoolbox\OpenAi\Session\Model;
use ValueError;

/**
 * Unit tests for the AI session {@see Model}.
 */
class ModelTest extends TestCase
{
    /**
     * A Feature enum passed to setFeature is stored and returned as its string value.
     */
    public function testSetFeatureAcceptsEnumAndReturnsStringValue(): void
    {
        $model = new Model();
        $model->setFeature(Feature::DIALOG_AGENT);

        $this->assertSame('dialogagent', $model->getFeature());
    }

    /**
     * A plain string passed to setFeature is stored and returned unchanged.
     */
    public function testSetFeatureAcceptsStringAndReturnsStringValue(): void
    {
        $model = new Model();
        $model->setFeature('chat');

        $this->assertSame('chat', $model->getFeature());
    }

    /**
     * A plain string passed to setFeature must be a known Feature value.
     */
    public function testSetFeatureRejectsUnknownStringValue(): void
    {
        $model = new Model();

        $this->expectException(ValueError::class);
        $model->setFeature('unknown');
    }
}
