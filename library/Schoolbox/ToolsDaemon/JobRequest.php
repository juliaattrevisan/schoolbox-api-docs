<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\ToolsDaemon;

use Psr\Http\Message\UriInterface;

/**
 * A struct which encapsulates a request to run a job.
 */
readonly class JobRequest
{
    /**
     * Constructor
     *
     * @param string                     $tool         The tool to run: must match the name of a tool defined in the
     *                                                 tools daemon's configuration file.
     * @param string|null                $instanceId   If provided: run the tool in the context of this instance.
     *                                                 A tool's definition will either mandate an instance ID, or
     *                                                 prohibit one.
     * @param array<string>              $options      A list of options to pass to the tool: these will be passed as
     *                                                 additional command-line parameters to the tool.
     * @param array<string, string>|null $environment  Any environment variables to pass to the tool.
     *                                                 A tool's definition will define its required and allowed
     *                                                 environment variables.
     * @param UriInterface|null          $webhook      If provided: a webhook URL to call once the tool has run.
     */
    public function __construct(
        public string $tool,
        public ?string $instanceId = null,
        public array $options = [],
        public ?array $environment = null,
        public ?UriInterface $webhook = null,
    ) {
    }

    /**
     * Create a new job request with a different tool.
     *
     * @param string $tool
     * @return self
     */
    public function withTool(string $tool): self
    {
        return new self($tool, $this->options, $this->instanceId, $this->environment, $this->webhook);
    }

    /**
     * Create a new job request with different options.
     *
     * @param array<string> $options
     * @return self
     */
    public function withOptions(array $options): self
    {
        return new self($this->tool, $options, $this->instanceId, $this->environment, $this->webhook);
    }

    /**
     * Create a new job request with a different instance ID (if any).
     *
     * @param ?string $instanceId
     * @return self
     */
    public function withInstanceId(?string $instanceId): self
    {
        return new self($this->tool, $this->options, $instanceId, $this->environment, $this->webhook);
    }

    /**
     * Create a new job request with different environment variables.
     *
     * @param array<string, string>|null $environment
     * @return self
     */
    public function withEnvironment(?array $environment): self
    {
        return new self($this->tool, $this->options, $this->instanceId, $environment, $this->webhook);
    }

    /**
     * Create a new job request with a different webhook.
     *
     * @param UriInterface|null $webhook
     * @return self
     */
    public function withWebhook(?UriInterface $webhook): self
    {
        return new self($this->tool, $this->options, $this->instanceId, $this->environment, $webhook);
    }

    /**
     * Convert this request to a tools daemon payload.
     *
     * @return array
     */
    public function toPayload(): array
    {
        $payload = [
            'tool'    => $this->tool,
            'options' => $this->options,
        ];
        if (isset($this->instanceId)) {
            $payload['instanceId'] = $this->instanceId;
        }
        if (isset($this->environment)) {
            $payload['environment'] = $this->environment;
        }
        if (isset($this->webhook)) {
            $payload['webhook'] = $this->webhook->__toString();
        }
        return $payload;
    }
}
