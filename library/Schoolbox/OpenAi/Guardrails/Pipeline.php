<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Guardrails;

use Schoolbox\GenericCollection;
use Schoolbox\OpenAi\Exception\GuardrailFlagged;
use Schoolbox\OpenAi\Guardrails\Check\ICheck;

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

    public function __construct()
    {
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
            $outcome = $check->check($inputText);
            if ($outcome->isFlagged()) {
                throw new GuardrailFlagged($check, json_encode($outcome->getMetadata()));
            }
        }
    }
}
