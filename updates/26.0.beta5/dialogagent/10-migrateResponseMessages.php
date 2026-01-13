<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Alaress\Database\Statement\Query\Base as Query;
use Schoolbox\Application\CliTool;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response as Response;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * CLI Tool
 *
 * Copy this to /tools folder
 *
 * To redirect log output to a file(s):
 *     STDOUT is file descriptor 1
 *     STDERR is file descriptor 2; our logger output ends up going here
 *
 *     Example: php example.php 2> log.txt
 */
$app = new class extends CliTool\App
{
    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * @var Query
     */
    private Query $insertStatement;


    /**
     * Parse and insert a row for the user's input message
     *
     * @param int $dialogAgentResponseId
     * @param array $logData
     */
    private function insertUserMessage(int $dialogAgentResponseId, array $logData): void
    {
        $this->insertStatement->execute([
            'dialogAgentResponseId' => $dialogAgentResponseId,
            'aiResponseId' => null,
            'role' => Response\Message\Role::User->value,
            'status' => Response\Message\Status::Ok->value,
            'content' => $logData['input_text'],
            'createdAt' => $logData['created_at'],
        ]);
    }


    /**
     * Parse and insert a row for the assistant's response message
     *
     * @param int $dialogAgentResponseId
     * @param array $logData
     * @throws SchoolboxException
     */
    private function insertAssistantMessage(int $dialogAgentResponseId, array $logData): void
    {
        try {
            $responseData = json_decode($logData['output_text'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SchoolboxException('Unable to decode response data', 0, $e);
        }

        $this->insertStatement->execute([
            'dialogAgentResponseId' => $dialogAgentResponseId,
            'aiResponseId' => $responseData['responseId'] ?? null,
            'role' => Response\Message\Role::Assistant->value,
            'status' => $responseData['status'] === 'success'
                ? Response\Message\Status::Ok->value
                : Response\Message\Status::Error->value,
            'content' => $responseData['message'] ?? $logData['output_text'],
            'createdAt' => $logData['created_at'],
        ]);
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

This finds any dialog agent responses with messages that are only stored in openai_log,
and migrates them into dialog_agent_response_message for proper storage.

This will only affect instances which used the dialog agents during beta but it avoids
there being really strange data in the system if we need to go and investigate old responses.

Usage: 10-migrateResponseMessages.php

HELP;
            return;
        }

        $db = $this->getDatabaseInternal();

        /* We're going be doing this more than a few times: prepare it only once, for speed */
        $this->insertStatement = $db->prepareQuery(
            "INSERT INTO dialog_agent_response_message
            (dialog_agent_response_id, ai_response_id, role, status, content, created_at)
            VALUES
            (:dialogAgentResponseId, :aiResponseId, :role, :status, :content, :createdAt)"
        );

        /**
         * Find all dialog agent messages in openai_log that are attached to
         * a dialog_agent_response with no migrated messages in dialog_agent_response_message
         */
        $logIterator = $db->getQueryIterator(
            "SELECT
                ol.id,
                dar.id AS dialog_agent_response_id,
                ol.input_text,
                ol.output_text,
                ol.created_at
            FROM dialog_agent_response dar
            JOIN openai_log ol ON ol.session_id = dar.session_id
            LEFT JOIN dialog_agent_response_message darm ON darm.dialog_agent_response_id = dar.id
            WHERE dar.session_id IS NOT NULL AND darm.id IS NULL",
        );

        $stats = [
            'user' => 0,
            'assistant' => 0,
        ];
        foreach ($logIterator as $logData) {
            $dialogAgentResponseId = $logData['dialog_agent_response_id'];

            /* Handle the user message, it will always be present */
            $this->insertUserMessage($dialogAgentResponseId, $logData);
            $stats['user'] += 1;

            /* Handle the assistant's response, if able */
            try {
                $this->insertAssistantMessage($dialogAgentResponseId, $logData);
                $stats['assistant'] += 1;
            } catch (SchoolboxException $e) {
                $this->getLogger()->warning(sprintf(
                    'Failed to migrate assistant message for log ID "%s": %s',
                    $logData['id'],
                    $e->getMessage()
                ));
            }
        }

        $this->getLogger()->info(sprintf(
            'Migrated %d dialog agent requests and %d responses to dialog_agent_response_message',
            $stats['user'],
            $stats['assistant'],
        ));
    }


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options, true))->runApp();
