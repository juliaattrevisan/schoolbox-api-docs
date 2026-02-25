<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Payload;

/**
 * Helper for parsing a model string into its constituent parts:
 * * model name
 * * version number
 * * variant
 */
class ModelParser
{
    /**
     * Extract the name from the model string.
     *
     * Assumes that the model string is either:
     * * `gpt-${version}`
     * * `gpt-${version}-${variant}`
     *
     * @param string $model
     * @return string|null
     */
    public function extractModelName(string $model): ?string
    {
        $parts = explode('-', $model);
        return $parts[0] ?? null;
    }

    /**
     * Extract the version number from the model string.
     *
     * Assumes that the model string is either:
     * * `gpt-${version}`
     * * `gpt-${version}-${variant}`
     *
     * @param string $model
     * @return string|null
     */
    public function extractModelVersion(string $model): ?string
    {
        $parts = explode('-', $model);
        return $parts[1] ?? null;
    }

    /**
     * Extract the variant from the model string.
     *
     * Assumes that the model string is either:
     * * `gpt-${version}`
     * * `gpt-${version}-${variant}`
     *
     * @param string $model
     * @return string|null
     */
    public function extractModelVariant(string $model): ?string
    {
        $parts = explode('-', $model);
        return $parts[2] ?? null;
    }

    /**
     * Perform some common filters on the payload, as appropriate for the model.
     *
     * These filters are not any guess as to what the payload _should_ be: they
     * only keep the payload within the bounds of what will actually work.
     *
     * @param array  $payload
     * @param string $model
     * @return array
     */
    public function filterPayload(array $payload, string $model): array
    {
        // GPT-4o and 4.1 family models:
        // * don't support reasoning
        // * only support medium verbosity
        $version = $this->extractModelVersion($model);
        if (in_array($version, ['4o', '4.1'])) {
            unset($payload['reasoning']);
            if (isset($payload['text']['verbosity'])) {
                $payload['text']['verbosity'] = 'medium';
            }
        }

        // GPT-5 defaults to `medium` reasoning, and does not support `none`:
        // this means that `temperature` and `top_p` can never be set, as these
        // are sampling parameters and don't apply to reasoning models.
        //
        // I've not been able to find exact details of why, but this post in the
        // OpenAI community alludes to it:
        // https://community.openai.com/t/top-p-problem-when-running-gpt-5-2-api/1369144/6
        //
        // The relevant quote being:
        // > Reasoning models do not accept sampling parameters such as temperature or top_p.
        $reasoningEffort = null;
        if ($version === '5') {
            $reasoningEffort = $payload['reasoning']['effort'] ?? 'medium';
            if ($reasoningEffort === 'none') {
                $reasoningEffort = 'low';
            }
        }

        // GPT 5.1 and 5.2 default to `none` for reasoning effort
        if (in_array($version, ['5.1', '5.2'])) {
            $reasoningEffort = $payload['reasoning']['effort'] ?? 'none';
        }

        // Set reasoning effort, and clear temperature and top_p if any effort
        // other than null was set
        if (isset($reasoningEffort)) {
            $payload['reasoning']['effort'] = $reasoningEffort;
        }
        if ($reasoningEffort !== 'none') {
            unset($payload['temperature'], $payload['top_p']);
        }

        // GPT-5.2-pro supports no reasoning levels other than:
        // `medium`, `high`, `x-high`
        if (
            $version === '5.2'
            && isset($payload['reasoning']['effort'])
            && $this->extractModelVariant($model) === 'pro'
        ) {
            if (!in_array($payload['reasoning']['effort'], ['medium', 'high', 'xhigh'])) {
                $payload['reasoning']['effort'] = 'medium';
            }
        }
        return $payload;
    }
}
