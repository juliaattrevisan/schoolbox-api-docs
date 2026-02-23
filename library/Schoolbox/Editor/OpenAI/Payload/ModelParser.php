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
}
