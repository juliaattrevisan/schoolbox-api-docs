<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\ModerationQueue;

use Ramsey\Uuid\Uuid;
use Schoolbox\Admin\OpenAI\Config as AIConfig;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\OpenAi\Agent\InteractionModeration;
use Schoolbox\OpenAi\ClientFactory;
use Schoolbox\OpenAi\Feature;
use Schoolbox\OpenAi\IClient;
use Schoolbox\OpenAi\Moderation\Event\Mapper as ModerationEventMapper;
use Schoolbox\OpenAi\Moderation\Event\Model as ModerationEventModel;
use Schoolbox\OpenAi\Moderation\Event\Status as ModerationEventStatus;
use Schoolbox\OpenAi\Session\Log\Mapper as SessionLogMapper;
use Schoolbox\OpenAi\Session\Log\Model as SessionLogModel;
use Schoolbox\Config\ConfigMapper;

class Moderator
{
    /**
     * @param SessionLogMapper $logMapper Mapper for target session logs.
     * @param ModerationEventMapper $moderationEventMapper Mapper for persisted moderation outcomes.
     * @param IConfigLoader $config Config loader used to read the moderation-agent configuration.
     * @param ClientFactory $clientFactory AI client factory.
     * @param callable|null $interactionModerationFactory Test hook for building the moderation agent.
     * @param callable|null $uuidFactory Test hook for deterministic moderation session IDs.
     */
    public function __construct(
        private readonly SessionLogMapper $logMapper,
        private readonly ModerationEventMapper $moderationEventMapper,
        private readonly IConfigLoader $config,
        private readonly ClientFactory $clientFactory,
        private readonly mixed $interactionModerationFactory = null,
        private readonly mixed $uuidFactory = null,
    ) {
    }

    /**
     * Moderates one target AI session, unless it has no logs or is already moderated for its latest log.
     *
     * @param string $targetSessionId The AI session to moderate.
     * @return ModerationResult
     */
    public function moderate(string $targetSessionId): ModerationResult
    {
        /** @var GenericCollection<SessionLogModel> $logs */
        $logs = $this->logMapper->findByColumn('ai_session_id', $targetSessionId, [
            'order' => ['created_at' => 'ASC'],
        ]);

        if ($logs->count() === 0) {
            return ModerationResult::noOp('no_logs');
        }

        /** @var SessionLogModel $latestLog */
        $latestLog = $logs->last();
        if ($this->isModeratedForLatestLog($targetSessionId, $latestLog)) {
            return ModerationResult::noOp('already_moderated');
        }

        $moderationSessionId = $this->createModerationSessionId();
        $moderationAgent = $this->createInteractionModeration();
        $moderationDetails = $moderationAgent->request(null, $moderationSessionId, $logs);
        $status = $moderationDetails->getCategories()->count() > 0
            ? ModerationEventStatus::REQUIRES_ATTENTION
            : ModerationEventStatus::NO_BEHAVIOUR_FLAGGED;

        $event = $this->moderationEventMapper->createModel()
            ->setAiSessionId($moderationSessionId)
            ->setTargetAiSessionId($targetSessionId)
            ->setStatus($status)
            ->setModerationDetails($moderationDetails);

        $this->moderationEventMapper->save($event);

        return ModerationResult::moderated($event);
    }

    /**
     * @param string $targetSessionId
     * @param SessionLogModel $latestLog
     * @return bool
     */
    private function isModeratedForLatestLog(string $targetSessionId, SessionLogModel $latestLog): bool
    {
        /** @var GenericCollection<ModerationEventModel> $events */
        $events = $this->moderationEventMapper->findByColumn('target_ai_session_id', $targetSessionId, [
            'order' => ['created_at' => 'DESC'],
            'limit' => 1,
        ]);

        /** @var ModerationEventModel|null $latestEvent */
        $latestEvent = $events->first();
        if (null === $latestEvent?->getCreatedAt() || null === $latestLog->getCreatedAt()) {
            return false;
        }

        return $latestEvent->getCreatedAt()->gte($latestLog->getCreatedAt());
    }

    /**
     * @return InteractionModeration
     */
    private function createInteractionModeration(): InteractionModeration
    {
        $agentConfig = (new AIConfig())->decode(
            (string)$this->config->get(ConfigMapper::NAME_AI_MODERATION_AGENT),
        );
        $client = $this->clientFactory->buildForFeature(Feature::AI_MODERATION_AGENT);

        if (\is_callable($this->interactionModerationFactory)) {
            return ($this->interactionModerationFactory)($client, $agentConfig);
        }

        return $this->buildInteractionModeration($client, $agentConfig);
    }

    /**
     * @param IClient $client
     * @param array $agentConfig
     * @return InteractionModeration
     */
    private function buildInteractionModeration(IClient $client, array $agentConfig): InteractionModeration
    {
        return new InteractionModeration($client, $agentConfig);
    }

    /**
     * @return string
     */
    private function createModerationSessionId(): string
    {
        if (\is_callable($this->uuidFactory)) {
            return (string)($this->uuidFactory)();
        }

        return (string)Uuid::uuid4();
    }
}
