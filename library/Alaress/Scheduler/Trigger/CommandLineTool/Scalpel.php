<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Scheduler\Trigger\CommandLineTool;

use Alaress\Redis;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Realtime\Schedule\Event\CrudEventInterface;

/**
 * Command-line tool for searching and removing trigger messages.
 *
 * This script is used to remove message triggers from the system PERMANENTLY.
 * It will search the entire trigger message list and delete any records it matches.
 */
class Scalpel extends CliTool\App
{
    /**
     * Print help
     */
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    /**
     * -r: use regex mode for string matching
     */
    private const OPT_SHORT_REGEX = 'r';

    /**
     * --instance-id={id}: match triggers from this instance
     */
    private const OPT_LONG_INSTANCE_ID = 'instance-id:';

    /**
     * --action={action}: match triggers with this action
     */
    private const OPT_LONG_ACTION = 'action:';

    /**
     * --mapper={mapper}: match triggers with this mapper class name
     */
    private const OPT_LONG_MAPPER = 'mapper:';

    /**
     * --model={model}: match triggers with this model class name
     */
    private const OPT_LONG_MODEL = 'model:';

    /**
     * --id={id}: match triggers with this ID
     */
    private const OPT_LONG_ID = 'id:';

    /**
     * --start={date}: only match triggers scheduled after this date
     */
    private const OPT_LONG_START = 'start:';

    /**
     * --end={date}: only match triggers scheduled before this date
     */
    private const OPT_LONG_END = 'end:';

    /**
     * --output-hash: output each message's hash to stdout
     */
    private const OPT_LONG_OUTPUT_HASH = 'output-hash';

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
            self::OPT_SHORT_REGEX,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_INSTANCE_ID,
            self::OPT_LONG_ACTION,
            self::OPT_LONG_MAPPER,
            self::OPT_LONG_MODEL,
            self::OPT_LONG_ID,
            self::OPT_LONG_START,
            self::OPT_LONG_END,
            self::OPT_LONG_OUTPUT_HASH,
        ];
    }

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            echo <<<HELP
NAME
    trigger-scalpel.php - search and remove message triggers from Redis

SYNOPSIS
    trigger-scalpel.php [OPTION]...

DESCRIPTION
    This script is used to remove message triggers from the system PERMANENTLY. It will search the entire trigger
    message list and delete any records it matches. To avoid unnecessary damage, specify as many parameters as
    possible to limit the search space.

    Please ensure you do a dry run before you run the script as this is unrecoverable.

    Note that at the time of creating a message trigger, the notification subsystem has not yet created the actual
    message to be delivered. This means that the following information is as yet unknown:
    * the recipients of the message
    * any content of the message: e.g. its topic, href, title, body, etc.
    * the mechanism via which the message will be delivered
    * whether the message is an actual message to be sent, or a retraction of a message which has been scheduled for
      delivery but not yet actually delivered

OPTIONS
    -h, --help              Print this help text and exit
    -p                      perform: *without* this flag, this tool will only do a dry run, outputting what would be
                            deleted
    -v[v[v]]                verbosity: how much activity will be logged
    --instance-id={id}      If set, match triggers from this instance.
                            If not set, falls back to:
                            * the INSTANCE_ID environment variable
                            * the first instance defined for this system, if INSTANCE_ID is not set
    --action={action}       If set, delete triggers which were triggered by this action.
                            Should match one of the following: create, update, delete.
    --mapper={mapper}       If set, delete triggers whose event's mapper matches this class name.
    --model={model}         If set, delete triggers whose event's model matches this class name.
    --id={id}               If set, delete triggers whose event's ID matches this value.
    --start={date}          Only triggers scheduled after this date will be matched.
                            If provided, must be supplied as one of:
                            * a "YYYY-MM-DD HH:MM:SS" date
                            * a timestamp
                            * a relative date value understandable by `strtotime`
    --end={date}            Only triggers scheduled before this date will be matched.
                            If provided, must be supplied as one of:
                            * a "YYYY-MM-DD HH:MM:SS" date
                            * a timestamp
                            * a relative date value understandable by `strtotime`
    -r                      If present, action, mapper and model will be matched as regular expressions.
    --output-hash           If present, also output each trigger's hash to stdout.
                            This can then be piped to other tools.

HELP;
            exit;
        }

        $logger = $this->getLogger();

        // Build search criteria from command line options
        $criteria = $this->buildCriteria();
        if (empty($criteria)) {
            $logger->error("Insufficient search parameters specified! Try -h for more information and help.");
            exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
        }

        $instanceId = $this->getOpt(self::OPT_LONG_INSTANCE_ID) ?: INSTANCE_ID;
        $regexMode = (bool)$this->getOpt(self::OPT_SHORT_REGEX);
        $outputHash = (bool)$this->getOpt(self::OPT_LONG_OUTPUT_HASH);

        // Get Redis instance
        $redis = Redis::getStorageClientInstance();
        if (!$redis) {
            $logger->critical("Redis instance not available");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        // Inform user of mode
        if ($this->perform()) {
            $logger->notice('Running in perform mode: this will permanently delete matching triggers.');
        } else {
            $logger->notice('Running in dry-run mode: use -p flag to permanently delete triggers.');
        }

        // Get all trigger message keys from Redis
        $redisList = $redis->keys('msg:' . $instanceId . ':s:*');
        $logger->info("Checking redis data...");
        $matchCount = 0;

        foreach ($redisList as $key) {
            $logger->debug("Checking key: $key");

            // Decompress and unserialize the message
            $compressed = $redis->get($key);
            $uncompress = gzuncompress($compressed);
            $msg = unserialize($uncompress);

            // Validate message is a trigger
            if (!$msg instanceof TriggerInterface) {
                $logger->warning(sprintf("Key %s does not contain a %s instance: skipping", $key, TriggerInterface::class));
                continue;
            }

            $event = $msg->getEvent();
            if (!$event instanceof CrudEventInterface) {
                $logger->warning(sprintf("Key %s event does not contain a %s instance: skipping", $key, CrudEventInterface::class));
                continue;
            }

            // Check if message matches criteria
            if ($this->matchesCriteria($criteria, $regexMode, $msg)) {
                if ($outputHash) {
                    $keyParts = explode(':', $key);
                    printf("%s\n", implode(':', array_slice($keyParts, -1)));
                }

                $logger->info(
                    sprintf(
                        'Found %s: %s (%s #%s)',
                        $key,
                        $event->getAction(),
                        get_class($event->getObject()),
                        $event->getMapper()->getModelProperty($event->getObject(), $event->getMapper()->getIdentifierColumn()),
                    )
                );

                if ($this->perform()) {
                    $redis->del($key);
                }
                ++$matchCount;
            }
        }

        // Summary
        if ($matchCount === 0) {
            $logger->info("No matching triggers found!");
        } elseif ($this->perform()) {
            $logger->info("$matchCount triggers matched and removed");
        } else {
            $logger->info("$matchCount triggers matched, no action taken (run with -p to delete)");
        }

        exit(CliKernelAbstract::EXIT_STATUS_OK);
    }

    /**
     * Build search criteria from command line options.
     *
     * @return array<string, mixed>
     */
    private function buildCriteria(): array
    {
        $criteria = [];

        if ($action = $this->getOpt(self::OPT_LONG_ACTION)) {
            $criteria['action'] = $action;
        }

        if ($mapper = $this->getOpt(self::OPT_LONG_MAPPER)) {
            $criteria['mapper'] = $mapper;
        }

        if ($model = $this->getOpt(self::OPT_LONG_MODEL)) {
            $criteria['model'] = $model;
        }

        if ($id = $this->getOpt(self::OPT_LONG_ID)) {
            $criteria['id'] = $id;
        }

        if ($start = $this->getOpt(self::OPT_LONG_START)) {
            $criteria['start'] = strtotime($start);
        }

        if ($end = $this->getOpt(self::OPT_LONG_END)) {
            $criteria['end'] = strtotime($end);
        }

        return $criteria;
    }

    /**
     * Check whether the given trigger matches the search criteria.
     *
     * @param array<string, mixed> $criteria   The search criteria
     * @param bool                 $regexMode  Match on regular expression?
     * @param TriggerInterface     $trigger    The trigger to match against
     * @return bool
     */
    private function matchesCriteria(array $criteria, bool $regexMode, TriggerInterface $trigger): bool
    {
        $event = $trigger->getEvent();
        if (!$event instanceof CrudEventInterface) {
            return false;
        }

        $success = true;

        if (isset($criteria['topic'])) {
            $topicSuccess = false;
            foreach ($trigger['topics'] as $topic) {
                if ($this->matchString($regexMode, $criteria['topic'], $topic)) {
                    $topicSuccess = true;
                    break;
                }
            }
            $success = $topicSuccess;
        }

        if (isset($criteria['action'])) {
            $success = $success && $this->matchString($regexMode, $criteria['action'], $event->getAction());
        }

        if (isset($criteria['mapper'])) {
            $success = $success && $this->matchString($regexMode, $criteria['mapper'], get_class($event->getMapper()));
        }

        if (isset($criteria['model'])) {
            $success = $success && $this->matchString($regexMode, $criteria['model'], get_class($event->getObject()));
        }

        if (isset($criteria['id'])) {
            $success = $success && $criteria['id'] == $event->getMapper()->getModelProperty(
                $event->getObject(),
                $event->getMapper()->getIdentifierColumn(),
            );
        }

        if (isset($criteria['start'])) {
            $success = $success && $criteria['start'] <= $trigger->getTimestamp();
        }

        if (isset($criteria['end'])) {
            $success = $success && $criteria['end'] >= $trigger->getTimestamp();
        }

        return $success;
    }

    /**
     * Match a string against a test value, optionally using regex.
     *
     * @param bool   $regexMode Whether to use regex matching
     * @param string $test      The test pattern or substring
     * @param string $text      The text to match against
     * @return bool
     */
    private function matchString(bool $regexMode, string $test, string $text): bool
    {
        if ($regexMode) {
            return (bool)preg_match("`$test`", $text);
        }
        return stripos($text, $test) !== false;
    }

    /**
     * @inheritdoc
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
}
