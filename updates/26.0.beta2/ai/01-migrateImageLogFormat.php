<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\OpenAi\AgentChat;
use Schoolbox\OpenAi\EventStream\Message;
use Schoolbox\OpenAi\Session\Log;

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
     * Attempt to decode an image generation prompt and convert it to an AgentChat input log
     * @param Log\Model $log
     * @return Log\Model
     * @throws SchoolboxException
     */
    private function parsePrompt(Log\Model $log): Log\Model
    {
        try {
            $prompt = json_decode($log->getPrompt(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SchoolboxException('Unable to decode prompt', 0, $e);
        }

        /** theoretically this matches directly with OpenAI/Payload/ImageGeneration.php::generate as we haven't changed it this release */

        $userInput = array_find($prompt['input'] ?? [], fn($item) => ($item['role'] ?? null) === 'user');
        if (null === $userInput) {
            throw new SchoolboxException('No user input found in prompt');
        }

        $promptText = array_find($userInput['content'] ?? [], fn($item) => ($item['type'] ?? null) === 'input_text')['text'] ?? null;
        if (null === $promptText) {
            throw new SchoolboxException('No input text found in prompt content');
        }

        $log->setInputText($promptText);
        return $log;
    }


    /**
     * Attempt to decode an image generation response and convert it to an AgentChat output log
     * @param Log\Model $log
     * @return Log\Model
     * @throws SchoolboxException
     */
    private function parseResponse(Log\Model $log): Log\Model
    {
        $responseListener = new AgentChat\EventStream\ResponseListener(requireFinalImage: true);
        $logListener = new AgentChat\LogHandler\LogOutputText();

        if (null === $log->getResponse()) {
            /**
             * Having no response at all is valid (i.e. the request to OpenAI failed),
             * the "outputText" will remain NULL and will be correctly handled by the translator as a failed response
             */
            return $log;
        }

        /**
         * Assuming the OpenAI format is the same, which it should be since we haven't changed anything...
         * try to re-parse the entire thing as an event stream
         */
        $messages = explode("\n\n", trim($log->getResponse()));
        foreach ($messages as $rawMessage) {
            $message = Message::fromString($rawMessage);
            $message = $responseListener->modifyMessage($message);

            // No output message? Output nothing
            if (!$message) {
                continue;
            }

            $log = $logListener->handleMessage($message, $message, $log);
        }

        return $log;
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

This finds all image generation requests in the openai log
and attempts to migrate them to use the more maintainable Agent Chat log format.

Usage: 01-migrateImageLogFormat.php

HELP;
            return;
        }

        $db = $this->getDatabaseInternal();

        /* We're going be doing this more than a few times: prepare it only once, for speed */
        $updateStatement = $db->prepareQuery("
            UPDATE openai_log
            SET `log_format` = :log_format, `input_text` = :input, `output_text` = :output
            WHERE `id` = :id;
        ");

        /* Find all image generation logs that haven't already been migrated (any without a log_format) */
        $logIterator = $db->getQueryIterator(
            "SELECT id, prompt, response
            FROM openai_log
            WHERE log_format IS NULL AND feature = :feature",
            ['feature' => \Schoolbox\OpenAi\Feature::IMAGE->value]
        );

        foreach ($logIterator as $logData) {
            $log = new Log\Model()
                ->setId($logData['id'])
                ->setPrompt($logData['prompt'])
                ->setResponse($logData['response'])
                ->setInputText(null)
                ->setOutputText(null);

            try {
                $log = $this->parsePrompt($log);
            } catch (SchoolboxException $e) {
                $this->getLogger()->warning(sprintf(
                    'Failed to parse image prompt for log ID %s: %s',
                    $logData['id'],
                    $e->getMessage()
                ));
                continue;
            }

            try {
                $log = $this->parseResponse($log);
            } catch (Exception $e) {
                $this->getLogger()->warning(sprintf(
                    'Failed to parse image response for log ID %s: %s',
                    $logData['id'],
                    $e->getMessage()
                ));
                continue;
            }

            $updateStatement->execute([
                'log_format' => Schoolbox\Editor\OpenAI\Log\LogFormat::AGENT_CHAT->value,
                'input'    => $log->getInputText(),
                'output'   => $log->getOutputText(),
                'id'       => $log->getId(),
            ]);

            $this->getLogger()->info(sprintf('Updated AI log with ID %s', $log->getId()));
        }
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
