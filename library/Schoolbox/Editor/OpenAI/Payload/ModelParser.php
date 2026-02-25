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

        // GPT-5 doesn't support temperature and top_p, however GPT-5.2 does,
        // or at least does not error when you pass them?
        // Or does it? I tested with image generation and it failed.
        if (in_array($version, ['5', '5.2'])) {
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
