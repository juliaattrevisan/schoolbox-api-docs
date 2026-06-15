<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Database\Test\OpenAi\Session;

use Alaress\Database\Test\TestConnectorTrait;
use Schoolbox\Editor\OpenAI\Log\LogFormat;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Schoolbox\Mapper\Exception;
use Schoolbox\OpenAi\Feature;
use Schoolbox\OpenAi\Session\Log\Mapper as LogMapper;
use Schoolbox\OpenAi\Session\Log\Model as LogModel;
use Schoolbox\OpenAi\Session\Mapper;
use Schoolbox\OpenAi\Session\Model;
use Schoolbox\UserMapper;

/**
 * Database tests for the admin session-list query {@see Mapper::getFilteredSessions()}.
 */
class FilteredSessionsTest extends TestCase
{
    use TestConnectorTrait;

    /**
     * Open a transaction and point the mappers at it, so writes roll back afterwards.
     */
    public function setUp(): void
    {
        $this->connectSchoolbox();
        $this->schoolbox->beginTransaction();

        Mapper::getInstance()->setDatabase($this->schoolbox);
        LogMapper::getInstance()->setDatabase($this->schoolbox);
        UserMapper::getInstance()->setDatabase($this->schoolbox);
    }

    /**
     * Roll back everything written during the test.
     */
    public function tearDown(): void
    {
        $this->schoolbox->rollback();
    }

    /**
     * A session is returned with logFormat/returnCode derived from its first log,
     * and the feature filter matches it.
     * @throws Exception
     */
    public function testReturnsSessionWithDerivedFirstLogValues(): void
    {
        $id = Uuid::uuid4()->toString();
        Mapper::getInstance()->findOrCreate($id, Feature::CHAT, null);

        // First log (earliest) is the one whose log_format/return_code should surface.
        LogMapper::getInstance()->save(
            new LogModel()->setAiSessionId($id)->setPrompt('first')
                ->setLogFormat(LogFormat::AGENT_CHAT)->setReturnCode(200)
        );
        LogMapper::getInstance()->save(
            new LogModel()->setAiSessionId($id)->setPrompt('second')
                ->setLogFormat(LogFormat::GUARDRAIL)->setReturnCode(500)
        );

        $sessions = Mapper::getInstance()->getFilteredSessions(
            null,
            Feature::CHAT->value,
            null,
            null,
            null,
        );

        $match = null;
        foreach ($sessions as $session) {
            if ($session->getId() === $id) {
                $match = $session;
            }
        }

        $this->assertInstanceOf(Model::class, $match);
        $this->assertSame('chat', $match->getFeature());
        $this->assertSame('agent_chat', $match->getDerivedLogFormat());
        $this->assertSame(200, $match->getDerivedReturnCode());
    }

    /**
     * The keyword filter matches sessions by their logs' prompt/response text.
     * @throws Exception
     */
    public function testKeywordFilterMatchesLogText(): void
    {
        $id = Uuid::uuid4()->toString();
        Mapper::getInstance()->findOrCreate($id, Feature::CHAT, null);
        LogMapper::getInstance()->save(
            new LogModel()->setAiSessionId($id)->setPrompt('a unique needle phrase')
        );

        $count = Mapper::getInstance()->countFilteredSessions(
            'unique needle',
            null,
            null,
            null,
            null
        );

        $this->assertGreaterThanOrEqual(1, $count);
    }
}
