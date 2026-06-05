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

class ModelTest extends TestCase
{
    public function testSetLogFormatAcceptsEnumAndReturnsStringValue(): void
    {
        $model = new Model();
        $model->setLogFormat(LogFormat::AGENT_CHAT);

        $this->assertSame('agent_chat', $model->getLogFormat());
    }

    public function testSetLogFormatAcceptsString(): void
    {
        $model = new Model();
        $model->setLogFormat('guardrail');

        $this->assertSame('guardrail', $model->getLogFormat());
    }

    public function testAiSessionIdAccessor(): void
    {
        $model = new Model();
        $model->setAiSessionId('1f2e3d4c-5b6a-7890-abcd-ef0123456789');

        $this->assertSame('1f2e3d4c-5b6a-7890-abcd-ef0123456789', $model->getAiSessionId());
    }

    public function testPromptIsTrimmedToTextMaxLength(): void
    {
        $model = new Model();
        $model->setPrompt(str_repeat('a', 70000));

        $this->assertSame(65535, mb_strlen($model->getPrompt()));
    }

    public function testInputTextIsTrimmedToTextMaxLength(): void
    {
        $model = new Model();
        $model->setInputText(str_repeat('a', 70000));

        $this->assertSame(65535, mb_strlen($model->getInputText()));
    }

    public function testOutputTextIsTrimmedToTextMaxLength(): void
    {
        $model = new Model();
        $model->setOutputText(str_repeat('a', 70000));

        $this->assertSame(65535, mb_strlen($model->getOutputText()));
    }

    public function testResponseIsTrimmedToMediumTextMaxLength(): void
    {
        $model = new Model();
        $model->setResponse(str_repeat('a', 16777215 + 10));

        $this->assertSame(16777215, mb_strlen($model->getResponse()));
    }
}
