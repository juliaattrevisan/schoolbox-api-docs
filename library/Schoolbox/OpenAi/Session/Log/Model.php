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

    /**
     * @return string|null
     */
    public function getAiSessionId(): ?string
    {
        return $this->aiSessionId;
    }

    /**
     * @param string|null $aiSessionId
     * @return $this
     */
    public function setAiSessionId(?string $aiSessionId): self
    {
        $this->aiSessionId = $aiSessionId;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     * @return $this
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return string
     */
    public function getPrompt(): string
    {
        return $this->prompt;
    }

    /**
     * @param string $prompt
     * @return $this
     */
    public function setPrompt(string $prompt): self
    {
        $this->prompt = self::trimToMaxBytes($prompt, self::PROMPT_MAX_LENGTH);
        return $this;
    }

    /**
     * @return int|null
     */
    public function getReturnCode(): ?int
    {
        return $this->returnCode;
    }

    /**
     * @param int|null $returnCode
     * @return $this
     */
    public function setReturnCode(?int $returnCode): self
    {
        $this->returnCode = $returnCode;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @param string|null $errorMessage
     * @return $this
     */
    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getInputTokens(): ?int
    {
        return $this->inputTokens;
    }

    /**
     * @param int|null $inputTokens
     * @return $this
     */
    public function setInputTokens(?int $inputTokens): self
    {
        $this->inputTokens = $inputTokens;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getOutputTokens(): ?int
    {
        return $this->outputTokens;
    }

    /**
     * @param int|null $outputTokens
     * @return $this
     */
    public function setOutputTokens(?int $outputTokens): self
    {
        $this->outputTokens = $outputTokens;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getInputText(): ?string
    {
        return $this->inputText;
    }

    /**
     * @param string|null $inputText
     * @return $this
     */
    public function setInputText(?string $inputText): self
    {
        $this->inputText = null === $inputText
            ? null
            : self::trimToMaxBytes($inputText, self::TEXT_MAX_LENGTH);
        return $this;
    }

    /**
     * @return string|null
     */
    public function getOutputText(): ?string
    {
        return $this->outputText;
    }

    /**
     * @param string|null $outputText
     * @return $this
     */
    public function setOutputText(?string $outputText): self
    {
        $this->outputText = null === $outputText
            ? null
            : self::trimToMaxBytes($outputText, self::TEXT_MAX_LENGTH);
        return $this;
    }

    /**
     * @return string|null
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * @param string|null $model
     * @return $this
     */
    public function setModel(?string $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    /**
     * @param string|null $endpoint
     * @return $this
     */
    public function setEndpoint(?string $endpoint): self
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLogFormat(): ?string
    {
        return $this->logFormat;
    }

    /**
     * Accepts either a {@see LogFormat} enum or its string value, and stores the string value.
     *
     * @param string|LogFormat|null $logFormat
     * @return $this
     */
    public function setLogFormat(string|LogFormat|null $logFormat): self
    {
        $this->logFormat = $logFormat instanceof LogFormat
            ? $logFormat->value
            : $logFormat;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getResponse(): ?string
    {
        return $this->response;
    }

    /**
     * @param string|null $response
     * @return $this
     */
    public function setResponse(?string $response): self
    {
        $this->response = null === $response
            ? null
            : self::trimToMaxBytes($response, self::RESPONSE_MAX_LENGTH);
        return $this;
    }

    private static function trimToMaxBytes(string $value, int $maxBytes): string
    {
        if (strlen($value) <= $maxBytes) {
            return $value;
        }

        return mb_strcut($value, 0, $maxBytes, 'UTF-8');
    }
}
