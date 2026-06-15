<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Guardrails;

use Schoolbox\GenericCollection;
use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\OpenAi\Exception\GuardrailFlagged;
use Schoolbox\OpenAi\Feature;
use Schoolbox\OpenAi\Guardrails\Check\ICheck;
use Schoolbox\OpenAi\IClient;
use Schoolbox\User;

/**
 * Guardrails pipeline
 *
 * This is used to combine multiple guardrail checks into a single pipeline
 * dependent on each agent's implementation and logic.
 *
 * OpenAI defines preflight, input, and output checks.
 * Usually "input" checks are meant to run in parallel with the LLM call but
 * for simplicity at the moment i've combined preflight/input and they will run
 * sequentially before the LLM call instead.
 */
class Pipeline
{
    /**
     * Input Checks: Runs on the input before the LLM call
     * @var GenericCollection<ICheck>
     */
    private GenericCollection $inputChecks;

    // TODO: Output checks?

    /**
     * @param IClient $client The AI provider client which should be used to create logs
     * @param User $user
     * @param Feature $feature
     * @param string $sessionId
     */
    public function __construct(
        private IClient $client,
        private User $user,
        private Feature $feature,
        private string $sessionId,
    ) {
        $this->inputChecks = new GenericCollection(ICheck::class);
    }

    /**
     * Add a new input check to the pipeline
     * @param ICheck $check The check to add
     */
    public function addInputCheck(ICheck $check): void
    {
        $this->inputChecks->add($check);
    }

    /**
     * Run all input guardrail checks against the input text
     * @param string $inputText The text to check
     * @throws GuardrailFlagged if any check flags the input
     */
    public function checkInputGuardrails(string $inputText): void
    {
        foreach ($this->inputChecks as $check) {
            /**
             * For now, create a separate log here with the relevant info
             * This is a bit of a workaround for how logs work, we ideally want to store a log
             * on failure of a guardrail, but successful checks don't necessarily need a log
             * unless they are AI-based checks in which case we want the log for AI audit info.
             */
            $log = $this->client->createLog(
                ['guardrail' => $check->getName()], // this will either be overridden (by an AI-based check) or remain like this (for non-AI checks such as regex)
                $inputText,
                $this->user,
                $this->feature,
                $this->sessionId,
                // Guardrail logs share the conversation session, so session summaries can derive "guardrail"
                // even when later logs in the same session are "agent_chat".
                LogFormat::GUARDRAIL,
            );

            $outcome = $check->check($inputText, $log);

            $log->setOutputText(json_encode($outcome->asArray(), JSON_THROW_ON_ERROR));

            if ($outcome->isFlagged()) {
                $e = new GuardrailFlagged($outcome, $check);
                $log->setErrorMessage($e->getMessage());
                $log->setReturnCode($e->getCode());
                $this->client->saveLog($log);
                throw $e;
            }

            // Note $log may or may not have a return code set.  This is expected, because some checks involve API
            // calls and some do not (e.g. OpenAi/Guardrails/Check/CharacterLimit).

            $this->client->saveLog($log);
        }
    }
}
