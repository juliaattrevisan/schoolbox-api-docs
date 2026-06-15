<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Database\Test\OpenAi\Session\Log;

use Alaress\Database\Test\TestConnectorTrait;
use PHPUnit\Framework\TestCase;
use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\OpenAi\Feature;
use Schoolbox\OpenAi\Session\Log\Mapper;
use Schoolbox\OpenAi\Session\Log\Model;
use Schoolbox\OpenAi\Session\Mapper as SessionMapper;
use Schoolbox\OpenAi\Session\Model as SessionModel;

/**
 * Database round-trip tests for the AI session log {@see Mapper}.
 */
class MapperTest extends TestCase
{
    use TestConnectorTrait;

    /**
     * The id of the parent ai_session row created in setUp() for the FK.
     *
     * @var string
     */
    private string $sessionId;

    /**
     * Open a transaction, point the mappers at it, and create a parent session for the FK.
     */
    public function setUp(): void
    {
        $this->connectSchoolbox();
        $this->schoolbox->beginTransaction();

        // Inject the transactional connection so mapper writes are covered by the
        // transaction and rolled back in tearDown().
        SessionMapper::getInstance()->setDatabase($this->schoolbox);
        Mapper::getInstance()->setDatabase($this->schoolbox);

        // ai_session_log.ai_session_id is a FK to ai_session.id, so create a parent first.
        $session = (new SessionModel())->setFeature(Feature::CHAT);
        SessionMapper::getInstance()->save($session);
        $this->sessionId = $session->getId();
    }

    /**
     * Roll back everything written during the test.
     */
    public function tearDown(): void
    {
        $this->schoolbox->rollback();
    }

    /**
     * A fully populated log row persists and round-trips on reload.
     */
    public function testSaveAndRoundTrip(): void
    {
        $model = (new Model())
            ->setAiSessionId($this->sessionId)
            ->setPrompt('Hello')
            ->setInputTokens(11)
            ->setOutputTokens(22)
            ->setInputText('in')
            ->setOutputText('out')
            ->setModel('gpt-4o')
            ->setEndpoint('/v1/responses')
            ->setLogFormat(LogFormat::AGENT_CHAT)
            ->setResponse('the response')
            ->setReturnCode(200);

        Mapper::getInstance()->save($model);

        $id = $model->getId();
        $this->assertNotNull($id);

        $reloaded = Mapper::getInstance()->findById($id);

        $this->assertInstanceOf(Model::class, $reloaded);
        $this->assertSame($this->sessionId, $reloaded->getAiSessionId());
        $this->assertSame('Hello', $reloaded->getPrompt());
        $this->assertSame(11, $reloaded->getInputTokens());
        $this->assertSame(22, $reloaded->getOutputTokens());
        $this->assertSame('in', $reloaded->getInputText());
        $this->assertSame('out', $reloaded->getOutputText());
        $this->assertSame('gpt-4o', $reloaded->getModel());
        $this->assertSame('/v1/responses', $reloaded->getEndpoint());
        $this->assertSame('agent_chat', $reloaded->getLogFormat());
        $this->assertSame('the response', $reloaded->getResponse());
        $this->assertSame(200, $reloaded->getReturnCode());
        $this->assertNull($reloaded->getUser());
    }

    /**
     * A null log_format is persisted and rehydrated as null.
     */
    public function testNullLogFormatRoundTrips(): void
    {
        $model = (new Model())
            ->setAiSessionId($this->sessionId)
            ->setPrompt('No format')
            ->setLogFormat(null);

        Mapper::getInstance()->save($model);

        $reloaded = Mapper::getInstance()->findById($model->getId());

        $this->assertNull($reloaded->getLogFormat());
    }
}
