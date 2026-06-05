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
     * The ai_session_id set via setAiSessionId is returned by getAiSessionId.
     */
    public function testAiSessionIdAccessor(): void
    {
        $model = new Model();
        $model->setAiSessionId('1f2e3d4c-5b6a-7890-abcd-ef0123456789');

        $this->assertSame('1f2e3d4c-5b6a-7890-abcd-ef0123456789', $model->getAiSessionId());
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
     * An over-long input text is trimmed to the TEXT column maximum length.
     */
    public function testInputTextIsTrimmedToTextMaxLength(): void
    {
        $model = new Model();
        $model->setInputText(str_repeat('a', 70000));

        $this->assertSame(65535, mb_strlen($model->getInputText()));
    }

    /**
     * An over-long output text is trimmed to the TEXT column maximum length.
     */
    public function testOutputTextIsTrimmedToTextMaxLength(): void
    {
        $model = new Model();
        $model->setOutputText(str_repeat('a', 70000));

        $this->assertSame(65535, mb_strlen($model->getOutputText()));
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
}
