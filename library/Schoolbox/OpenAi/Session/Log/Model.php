<?php

/**
 * Model for a single AI session log (one row of the `ai_session_log` table).
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Log;

use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

class Model
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;

    private ?string $aiSessionId = null;
    private ?User $user = null;
    private string $prompt;
    private ?int $inputTokens = null;
    private ?int $outputTokens = null;
    private ?string $inputText = null;
    private ?string $outputText = null;
    private ?string $model = null;
    private ?string $endpoint = null;
    private ?string $logFormat = null;
    private ?string $response = null;
    private ?int $returnCode = null;
    private ?string $errorMessage = null;

    // TEXT max length, to trim the prompt, rather than die.
    private const PROMPT_MAX_LENGTH = 65535;

    // TEXT max length for input_text and output_text columns
    private const TEXT_MAX_LENGTH = 65535;

    // MEDIUM TEXT max length, to trim the response, rather than die.
    private const RESPONSE_MAX_LENGTH = 16777215;

    public function getAiSessionId(): ?string
    {
        return $this->aiSessionId;
    }

    public function setAiSessionId(?string $aiSessionId): self
    {
        $this->aiSessionId = $aiSessionId;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setPrompt(string $prompt): self
    {
        // Limit the prompt length to prevent database errors
        if ($prompt && mb_strlen($prompt) > self::PROMPT_MAX_LENGTH) {
            $prompt = mb_substr($prompt, 0, self::PROMPT_MAX_LENGTH);
        }
        $this->prompt = $prompt;
        return $this;
    }

    public function getReturnCode(): ?int
    {
        return $this->returnCode;
    }

    public function setReturnCode(?int $returnCode): self
    {
        $this->returnCode = $returnCode;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getInputTokens(): ?int
    {
        return $this->inputTokens;
    }

    public function setInputTokens(?int $inputTokens): self
    {
        $this->inputTokens = $inputTokens;
        return $this;
    }

    public function getOutputTokens(): ?int
    {
        return $this->outputTokens;
    }

    public function setOutputTokens(?int $outputTokens): self
    {
        $this->outputTokens = $outputTokens;
        return $this;
    }

    public function getInputText(): ?string
    {
        return $this->inputText;
    }

    public function setInputText(?string $inputText): self
    {
        // Limit the input text length to prevent database errors
        if ($inputText && mb_strlen($inputText) > self::TEXT_MAX_LENGTH) {
            $inputText = mb_substr($inputText, 0, self::TEXT_MAX_LENGTH);
        }
        $this->inputText = $inputText;
        return $this;
    }

    public function getOutputText(): ?string
    {
        return $this->outputText;
    }

    public function setOutputText(?string $outputText): self
    {
        // Limit the output text length to prevent database errors
        if ($outputText && mb_strlen($outputText) > self::TEXT_MAX_LENGTH) {
            $outputText = mb_substr($outputText, 0, self::TEXT_MAX_LENGTH);
        }
        $this->outputText = $outputText;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(?string $endpoint): self
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function getLogFormat(): ?string
    {
        return $this->logFormat;
    }

    /**
     * @param string|LogFormat|null $logFormat
     */
    public function setLogFormat(string|LogFormat|null $logFormat): self
    {
        $this->logFormat = $logFormat instanceof LogFormat
            ? $logFormat->value
            : $logFormat;

        return $this;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): self
    {
        // Limit the response length to prevent database errors
        if ($response && mb_strlen($response) > self::RESPONSE_MAX_LENGTH) {
            $response = mb_substr($response, 0, self::RESPONSE_MAX_LENGTH);
        }
        $this->response = $response;
        return $this;
    }
}
