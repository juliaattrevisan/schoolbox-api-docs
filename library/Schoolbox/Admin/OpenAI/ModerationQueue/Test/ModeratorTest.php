<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\ModerationQueue\Test;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\OpenAI\ModerationQueue\Moderator;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\OpenAi\Agent\InteractionModeration;
use Schoolbox\OpenAi\ClientFactory;
use Schoolbox\OpenAi\Feature;
use Schoolbox\OpenAi\IClient;
use Schoolbox\OpenAi\Moderation\Event\Mapper as ModerationEventMapper;
use Schoolbox\OpenAi\Moderation\Event\Model as ModerationEventModel;
use Schoolbox\OpenAi\Moderation\Event\Status as ModerationEventStatus;
use Schoolbox\OpenAi\Moderation\Result\Result as ModerationAgentResult;
use Schoolbox\OpenAi\Session\Log\Mapper as SessionLogMapper;
use Schoolbox\OpenAi\Session\Log\Model as SessionLogModel;

class ModeratorTest extends TestCase
{
    /**
     * @return void
     */
    public function testCreatesOneEventForSessionWithLogs(): void
    {
        $logs = $this->logCollection([
            $this->createLog('2026-07-01 09:00:00'),
            $this->createLog('2026-07-01 09:05:00'),
        ]);
        $agentResult = $this->createModerationResult(false);

        $savedEvent = null;
        $moderator = $this->createModerator(
            logs: $logs,
            events: $this->eventCollection([]),
            agentResult: $agentResult,
            savedEvent: $savedEvent,
        );

        $result = $moderator->moderate('target-session-id');

        $this->assertTrue($result->isModerated());
        $this->assertFalse($result->isNoOp());
        $this->assertInstanceOf(ModerationEventModel::class, $savedEvent);
        $this->assertSame($savedEvent, $result->getEvent());
        $this->assertSame('moderation-session-id', $savedEvent->getAiSessionId());
        $this->assertSame('target-session-id', $savedEvent->getTargetAiSessionId());
        $this->assertSame($agentResult, $savedEvent->getModerationDetails());
    }

    /**
     * @return void
     */
    public function testMapsFlaggedResultToRequiresAttention(): void
    {
        $savedEvent = null;
        $moderator = $this->createModerator(
            logs: $this->logCollection([$this->createLog('2026-07-01 09:00:00')]),
            events: $this->eventCollection([]),
            agentResult: $this->createModerationResult(true),
            savedEvent: $savedEvent,
        );

        $moderator->moderate('target-session-id');

        $this->assertSame(ModerationEventStatus::REQUIRES_ATTENTION->value, $savedEvent?->getStatus());
    }

    /**
     * @return void
     */
    public function testMapsEmptyCategoryResultToNoBehaviourFlagged(): void
    {
        $savedEvent = null;
        $moderator = $this->createModerator(
            logs: $this->logCollection([$this->createLog('2026-07-01 09:00:00')]),
            events: $this->eventCollection([]),
            agentResult: $this->createModerationResult(false),
            savedEvent: $savedEvent,
        );

        $moderator->moderate('target-session-id');

        $this->assertSame(ModerationEventStatus::NO_BEHAVIOUR_FLAGGED->value, $savedEvent?->getStatus());
    }

    /**
     * @return void
     */
    public function testDoesNotCreateSecondEventWhenRerunWithoutNewerLogs(): void
    {
        $savedEvent = null;
        $moderator = $this->createModerator(
            logs: $this->logCollection([$this->createLog('2026-07-01 09:00:00')]),
            events: $this->eventCollection([
                $this->createEvent('2026-07-01 09:05:00'),
            ]),
            agentResult: $this->createModerationResult(false),
            savedEvent: $savedEvent,
            expectAgentRequest: false,
            expectSave: false,
        );

        $result = $moderator->moderate('target-session-id');

        $this->assertTrue($result->isNoOp());
        $this->assertSame('already_moderated', $result->getReason());
        $this->assertNull($savedEvent);
    }

    /**
     * @return void
     */
    public function testCreatesNewEventWhenNewerLogExistsAfterLastModerationEvent(): void
    {
        $savedEvent = null;
        $moderator = $this->createModerator(
            logs: $this->logCollection([
                $this->createLog('2026-07-01 09:00:00'),
                $this->createLog('2026-07-01 09:10:00'),
            ]),
            events: $this->eventCollection([
                $this->createEvent('2026-07-01 09:05:00'),
            ]),
            agentResult: $this->createModerationResult(false),
            savedEvent: $savedEvent,
        );

        $result = $moderator->moderate('target-session-id');

        $this->assertTrue($result->isModerated());
        $this->assertSame('target-session-id', $savedEvent?->getTargetAiSessionId());
    }

    /**
     * @return void
     */
    public function testReturnsNoOpResultWhenThereAreNoLogs(): void
    {
        $savedEvent = null;
        $moderator = $this->createModerator(
            logs: $this->logCollection([]),
            events: $this->eventCollection([]),
            agentResult: $this->createModerationResult(false),
            savedEvent: $savedEvent,
            expectAgentRequest: false,
            expectSave: false,
            expectEventLookup: false,
        );

        $result = $moderator->moderate('target-session-id');

        $this->assertTrue($result->isNoOp());
        $this->assertSame('no_logs', $result->getReason());
        $this->assertNull($savedEvent);
    }

    /**
     * @param GenericCollection<SessionLogModel> $logs
     * @param GenericCollection<ModerationEventModel> $events
     * @param ModerationAgentResult $agentResult
     * @param ModerationEventModel|null $savedEvent
     * @param bool $expectAgentRequest
     * @param bool $expectSave
     * @param bool $expectEventLookup
     * @return Moderator
     */
    private function createModerator(
        GenericCollection $logs,
        GenericCollection $events,
        ModerationAgentResult $agentResult,
        ?ModerationEventModel &$savedEvent,
        bool $expectAgentRequest = true,
        bool $expectSave = true,
        bool $expectEventLookup = true,
    ): Moderator {
        $logMapper = $this->createMock(SessionLogMapper::class);
        $logMapper
            ->expects($this->once())
            ->method('findByColumn')
            ->with('ai_session_id', 'target-session-id', ['order' => ['created_at' => 'ASC']])
            ->willReturn($logs);

        $eventMapper = $this->createMock(ModerationEventMapper::class);
        $eventMapper
            ->expects($expectEventLookup ? $this->once() : $this->never())
            ->method('findByColumn')
            ->with('target_ai_session_id', 'target-session-id', [
                'order' => ['created_at' => 'DESC'],
                'limit' => 1,
            ])
            ->willReturn($events);
        $eventMapper
            ->expects($expectSave ? $this->once() : $this->never())
            ->method('createModel')
            ->willReturn(new ModerationEventModel());
        $eventMapper
            ->expects($expectSave ? $this->once() : $this->never())
            ->method('save')
            ->willReturnCallback(
                function (ModerationEventModel $event) use (&$savedEvent): void {
                    $savedEvent = $event;
                }
            );

        $config = $this->createMock(IConfigLoader::class);
        $config
            ->expects($expectAgentRequest ? $this->once() : $this->never())
            ->method('get')
            ->with(ConfigMapper::NAME_AI_MODERATION_AGENT)
            ->willReturn('{"provider":{"value":"openai"}}');

        $client = $this->createStub(IClient::class);
        $clientFactory = $this->createMock(ClientFactory::class);
        $clientFactory
            ->expects($expectAgentRequest ? $this->once() : $this->never())
            ->method('buildForFeature')
            ->with(Feature::AI_MODERATION_AGENT)
            ->willReturn($client);

        $interactionModeration = $this->createInteractionModeration($agentResult, $expectAgentRequest);
        $interactionModerationFactory = function (
            IClient $actualClient,
            array $agentConfig
        ) use (
            $client,
            $interactionModeration
        ): InteractionModeration {
            $this->assertSame($client, $actualClient);
            $this->assertSame('openai', $agentConfig['provider']['value']);
            return $interactionModeration;
        };

        return new Moderator(
            $logMapper,
            $eventMapper,
            $config,
            $clientFactory,
            $interactionModerationFactory,
            static fn(): string => 'moderation-session-id',
        );
    }

    /**
     * @param ModerationAgentResult $agentResult
     * @param bool $expectAgentRequest
     * @return InteractionModeration&MockObject
     */
    private function createInteractionModeration(
        ModerationAgentResult $agentResult,
        bool $expectAgentRequest,
    ): InteractionModeration&MockObject {
        $interactionModeration = $this
            ->getMockBuilder(InteractionModeration::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $interactionModeration
            ->expects($expectAgentRequest ? $this->once() : $this->never())
            ->method('request')
            ->with(
                null,
                'moderation-session-id',
                $this->isInstanceOf(GenericCollection::class),
            )
            ->willReturn($agentResult);

        return $interactionModeration;
    }

    /**
     * @param list<SessionLogModel> $logs
     * @return GenericCollection<SessionLogModel>
     */
    private function logCollection(array $logs): GenericCollection
    {
        return new GenericCollection(SessionLogModel::class, $logs);
    }

    /**
     * @param list<ModerationEventModel> $events
     * @return GenericCollection<ModerationEventModel>
     */
    private function eventCollection(array $events): GenericCollection
    {
        return new GenericCollection(ModerationEventModel::class, $events);
    }

    /**
     * @param string $createdAt
     * @return SessionLogModel
     */
    private function createLog(string $createdAt): SessionLogModel
    {
        return (new SessionLogModel())
            ->setAiSessionId('target-session-id')
            ->setPrompt('prompt')
            ->setCreatedAt(new \Alaress_Date_Format($createdAt, \Alaress_Date_Format::DATABASE_FORMAT));
    }

    /**
     * @param string $createdAt
     * @return ModerationEventModel
     */
    private function createEvent(string $createdAt): ModerationEventModel
    {
        return (new ModerationEventModel())
            ->setAiSessionId('previous-moderation-session-id')
            ->setTargetAiSessionId('target-session-id')
            ->setStatus(ModerationEventStatus::NO_BEHAVIOUR_FLAGGED)
            ->setModerationDetails($this->createModerationResult(false))
            ->setCreatedAt(new \Alaress_Date_Format($createdAt, \Alaress_Date_Format::DATABASE_FORMAT));
    }

    /**
     * @param bool $flagged
     * @return ModerationAgentResult
     */
    private function createModerationResult(bool $flagged): ModerationAgentResult
    {
        return ModerationAgentResult::fromArray([
            'overall_risk_level' => $flagged ? 'high' : 'none',
            'summary' => $flagged ? 'Flagged interaction.' : 'No concerns found.',
            'categories' => $flagged ? [
                [
                    'category' => 'ai_misuse',
                    'severity' => 'high',
                    'confidence' => 'high',
                    'reason' => 'The interaction was flagged.',
                    'evidence' => [],
                ],
            ] : [],
            'pattern_assessment' => [
                'pattern_detected' => $flagged,
                'description' => $flagged ? 'Repeated concerning pattern.' : 'No pattern detected.',
            ],
            'recommended_school_action' => [
                'action_level' => $flagged ? 'review' : 'none',
                'why' => $flagged ? 'A staff member should review.' : 'No action needed.',
            ],
            'safeguarding_priority' => false,
            'discipline_priority' => $flagged,
        ]);
    }
}
