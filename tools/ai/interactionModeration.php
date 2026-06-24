#!/usr/bin/env php
<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Ramsey\Uuid\Uuid;
use Schoolbox\Admin\OpenAI\Config as AIConfig;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\OpenAi\Feature;
use Schoolbox\OpenAi\Session\Log\Mapper as AILogMapper;
use Schoolbox\OpenAi\Agent\InteractionModeration;
use Schoolbox\OpenAi\ClientFactory;
use Schoolbox\OpenAi\Moderation\Event\Mapper as ModerationEventMapper;
use Schoolbox\OpenAi\Moderation\Event\Status as ModerationEventStatus;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Temporary tool to run the InteractionModeration agent against a particular AI session within ai_session_log.
 */
$app = new class extends CliTool\App {
    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';

    /**
     * --session-id
     *
     * The AI session ID to run moderation against
     */
    private const string OPT_LONG_SESSION_ID = 'session-id:';

    /**
     * @var ModerationEventMapper
     */
    private ModerationEventMapper $moderationEventMapper;

    /**
     * @var AILogMapper
     */
    private AILogMapper $aiLogMapper;

    /**
     * @var ClientFactory
     */
    private ClientFactory $clientFactory;

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_SESSION_ID,
        ];
    }

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
                    echo <<<HELP
Interaction Moderation Tool

Runs the AI interaction moderation agent against existing ai_session_log rows for an AI session.

Usage: {$this->getToolFilename()} --session-id SESSION_ID
    --session-id    The AI session ID to run moderation against

HELP;
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $targetSessionId = trim((string)($this->getOpt(self::OPT_LONG_SESSION_ID) ?? ''));

        if ($targetSessionId === '') {
            $this->getLogger()->error('--session-id is required.');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $this->moderationEventMapper = ModerationEventMapper::getInstance();
        $this->aiLogMapper = AILogMapper::getInstance();
        $this->clientFactory = $this->getDi()->get('ai.clientFactory');

        /* find related logs */

        $logs = $this->aiLogMapper->findByColumn('ai_session_id', $targetSessionId, [
            'order' => [
                'created_at' => 'ASC',
            ],
        ]);

        if ($logs->count() === 0) {
            $this->getLogger()->error(sprintf('No logs found for session ID "%s"', $targetSessionId));
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $this->getLogger()->info(sprintf('Found %d log(s) for session ID "%s"', $logs->count(), $targetSessionId));

        /* run the agent */

        $agentConfig = (new AIConfig())->decode(
            (string)$this->getConfig()->get(ConfigMapper::NAME_AI_MODERATION_AGENT)
        );

        $interactionModeration = new InteractionModeration(
            $this->clientFactory->buildForFeature(Feature::AI_MODERATION_AGENT),
            $agentConfig,
        );

        $this->getLogger()->info('Running moderation agent...');

        $moderationSessionId = (string)Uuid::uuid4();
        $moderationResult = $interactionModeration->request(null, $moderationSessionId, $logs);

        /* persist the result (for both flagged and non-flagged sessions so we can remember if we ran the agent against them already) */
        $status = $moderationResult->getNeedsHumanReview()
            ? ModerationEventStatus::REQUIRES_ATTENTION
            : ModerationEventStatus::NO_BEHAVIOUR_FLAGGED;

        $event = $this->moderationEventMapper->createModel()
            ->setAiSessionId($moderationSessionId)
            ->setTargetAiSessionId($targetSessionId)
            ->setStatus($status)
            ->setModerationDetails($moderationResult);

        $this->moderationEventMapper->save($event);

        /* dump the results out since this is a temp testing tool */
        fwrite(
            $this->getOutStream(),
            json_encode($moderationResult, JSON_PRETTY_PRINT) . PHP_EOL
        );
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
