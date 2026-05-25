<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Payload;

use Schoolbox\Editor\OpenAI\Helper;

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
        $parts = explode('-', $model, 3);
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
        $parts = explode('-', $model, 3);
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
        $parts = explode('-', $model, 3);
        return $parts[2] ?? null;
    }

    /**
     * Determine the model value to pass in the payload to the OpenAI API call.
     *
     * If `$endpoint` is an Azure endpoint, use the `$azureDeploymentId`:
     * otherwise, use `$openAiModel`.
     *
     * @param string $endpoint
     * @param string $openAiModel
     * @param string $azureDeploymentId
     * @return string
     */
    public function determinePayloadModel(string $endpoint, string $openAiModel, string $azureDeploymentId): string
    {
        return $endpoint === Helper::ENDPOINT_TYPE_AZURE
            ? $azureDeploymentId
            : $openAiModel;
    }

    /**
     * Determine the model value to use so that the payload may be filtered in
     * `filterPayload`.
     *
     * If `$endpoint` is an Azure endpoint, use the `$azureDeploymentModel`:
     * otherwise, use `$openAiModel`.
     *
     * @param string $endpoint
     * @param string $openAiModel
     * @param string $azureDeploymentModel
     * @return string
     */
    public function determineFilterModel(string $endpoint, string $openAiModel, string $azureDeploymentModel): string
    {
        return $endpoint === Helper::ENDPOINT_TYPE_AZURE
            ? $azureDeploymentModel
            : $openAiModel;
    }

    /**
     * Perform some common filters on the payload, as appropriate for the model.
     *
     * These filters are not any guess as to what the payload _should_ be: they
     * only keep the payload within the bounds of what will actually work.
     *
     * @param array  $payload  The data which would be sent to the AI provider.
     * @param string $model    The name of the model to use.
     *                         For Azure, this is _not_ the deployment ID:
     *                         instead, it is the selected deployment model.
     *                         (This value _should_ match the model deployed in
     *                         Azure, but this is not enforceable.)
     * @return array
     */
    public function filterPayload(array $payload, string $model): array
    {
        $version = $this->extractModelVersion($model);
        $variant = $this->extractModelVariant($model);

        // chat variants:
        // * all support only medium verbosity
        // * 5 does not support reasoning at all
        // * 5.1, 5.2 and 5.3 support only medium reasoning, and thus no
        //   sampling settings
        // Therefore, don't set reasoning at all
        if ($variant === 'chat-latest') {
            $payload['text']['verbosity'] = 'medium';
            if ($version === '5') {
                unset($payload['reasoning']);
            }
            if (in_array($version, ['5.1', '5.2', '5.3'])) {
                $payload['reasoning']['effort'] = 'medium';
                unset($payload['temperature'], $payload['top_p']);
            }
            return $payload;
        }

        // GPT-4o and 4.1 family models:
        // * don't support reasoning, and always support sampling params
        //   (top_p, temperature)
        // * only support medium verbosity
        $retainSamplingParams = false;
        if (in_array($version, ['4o', '4.1'])) {
            unset($payload['reasoning']);
            $retainSamplingParams = true;
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
        if ($version === '5' && isset($payload['reasoning'])) {
            $reasoningEffort = $payload['reasoning']['effort'] ?? 'medium';
            if ($reasoningEffort === 'none') {
                $reasoningEffort = 'minimal';
            }
        }

        // GPT 5.1, 5.2 and 5.4 default to `none` for reasoning effort
        if (in_array($version, ['5.1', '5.2', '5.4']) && isset($payload['reasoning'])) {
            $reasoningEffort = $payload['reasoning']['effort'] ?? 'none';
            // Also don't support `minimal` reasoning
            if ($reasoningEffort === 'minimal') {
                $reasoningEffort = 'low';
            }
        }

        // Set reasoning effort, and clear sampling params temperature and top_p
        // if using a GPT-4 model, or any effort other than none was set
        if (isset($reasoningEffort)) {
            $payload['reasoning']['effort'] = $reasoningEffort;
        }
        if (!($retainSamplingParams || $reasoningEffort === 'none')) {
            unset($payload['temperature'], $payload['top_p']);
        }

        // GPT 5.2 and 5.4 pro variants supports no reasoning levels other than:
        // `medium`, `high`, `xhigh`
        if (
            in_array($version, ['5.2', '5.4'])
            && isset($payload['reasoning']['effort'])
            && $variant === 'pro'
        ) {
            if (!in_array($payload['reasoning']['effort'], ['medium', 'high', 'xhigh'])) {
                $payload['reasoning']['effort'] = 'medium';
            }
        }
        return $payload;
    }
}
