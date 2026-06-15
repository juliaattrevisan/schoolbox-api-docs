<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Log\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\OpenAi\Session\Log\Model;

/**
 * Unit tests for the AI session log {@see Model}.
 */
class ModelTest extends TestCase
{
    /**
     * A LogFormat enum passed to setLogFormat is stored and returned as its string value.
     */
    public function testSetLogFormatAcceptsEnumAndReturnsStringValue(): void
    {
        $model = new Model();
        $model->setLogFormat(LogFormat::AGENT_CHAT);

        $this->assertSame('agent_chat', $model->getLogFormat());
    }

    /**
     * A plain string passed to setLogFormat is stored and returned unchanged.
     */
    public function testSetLogFormatAcceptsString(): void
    {
        $model = new Model();
        $model->setLogFormat('guardrail');

        $this->assertSame('guardrail', $model->getLogFormat());
    }

    /**
     * An over-long prompt is trimmed to the TEXT column maximum length.
     */
    public function testPromptIsTrimmedToTextMaxLength(): void
    {
        $model = new Model();
        $model->setPrompt(str_repeat('a', 70000));

        $this->assertSame(65535, mb_strlen($model->getPrompt()));
    }

    /**
     * A multibyte prompt is trimmed to the TEXT column maximum byte length without breaking UTF-8.
     */
    public function testPromptIsTrimmedToTextMaxByteLength(): void
    {
        $model = new Model();
        $model->setPrompt(str_repeat('€', 22000));

        $this->assertLessThanOrEqual(65535, strlen($model->getPrompt()));
        $this->assertTrue(mb_check_encoding($model->getPrompt(), 'UTF-8'));
    }

    /**
     * An over-long response is trimmed to the MEDIUMTEXT column maximum length.
     */
    public function testResponseIsTrimmedToMediumTextMaxLength(): void
    {
        $model = new Model();
        $model->setResponse(str_repeat('a', 16777215 + 10));

        $this->assertSame(16777215, mb_strlen($model->getResponse()));
    }

    /**
     * A multibyte response is trimmed to the MEDIUMTEXT column maximum byte length without breaking UTF-8.
     */
    public function testResponseIsTrimmedToMediumTextMaxByteLength(): void
    {
        $model = new Model();
        $model->setResponse(str_repeat('€', 5592415));

        $this->assertLessThanOrEqual(16777215, strlen($model->getResponse()));
        $this->assertTrue(mb_check_encoding($model->getResponse(), 'UTF-8'));
    }
}
