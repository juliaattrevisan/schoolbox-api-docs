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
use Schoolbox\Editor\OpenAI\Helper;
use Schoolbox\Editor\OpenAI\Log\Mapper as AILogMapper;
use Schoolbox\OpenAi\Agent\InteractionModeration;
use Schoolbox\OpenAi\ClientFactory;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Temporary tool to run the InteractionModeration agent against a particular session_id within openai_log
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

Runs the AI interaction moderation agent against existing openai_log rows for a session.

Usage: {$this->getToolFilename()} --session-id SESSION_ID
    --session-id    The AI session ID to run moderation against

HELP;
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $sessionId = trim((string)($this->getOpt(self::OPT_LONG_SESSION_ID) ?? ''));

        if ($sessionId === '') {
            $this->getLogger()->error('--session-id is required.');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $this->aiLogMapper = AILogMapper::getInstance();
        $this->clientFactory = $this->getDi()->get('ai.clientFactory');

        $logs = $this->aiLogMapper->findByColumn('session_id', $sessionId, [
            'order' => [
                'created_at' => 'ASC',
            ],
        ]);

        if ($logs->count() === 0) {
            $this->getLogger()->error(sprintf('No logs found for session ID "%s"', $sessionId));
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $this->getLogger()->info(sprintf('Found %d log(s) for session ID "%s"', $logs->count(), $sessionId));

        $agentConfig = (new AIConfig())->decode(
            (string)$this->getConfig()->get(ConfigMapper::NAME_AI_MODERATION_AGENT)
        );

        $interactionModeration = new InteractionModeration(
            $this->clientFactory->buildForFeature(Helper::FEATURE_AI_MODERATION_AGENT),
            $agentConfig,
        );

        $this->getLogger()->info('Running moderation agent...');

        $moderationSessionId = (string)Uuid::uuid4();
        $moderationResult = $interactionModeration->request(null, $moderationSessionId, $logs);

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
