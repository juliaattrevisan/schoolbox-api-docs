<?php

/**
 * Model
 *
 * @author      James Leckie <james.leckie@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Editor\OpenAI\Log;

use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

class Model
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;

    private ?User $user = null;
    private string $prompt;
    private ?int $inputTokens = null;
    private ?int $outputTokens = null;
    private ?string $inputText = null;
    private ?string $outputText = null;
    private ?string $model = null;
    private ?string $endpoint = null;
    private ?string $feature = null;
    private ?string $logType = null;
    private ?string $response = null;
    private ?int $returnCode = null;
    private ?string $errorMessage = null;
    private ?string $sessionId = null;

    // TEXT max length, to trim the prompt, rather than die.
    private const PROMPT_MAX_LENGTH = 65535;

    // TEXT max length for input_text and output_text columns
    private const TEXT_MAX_LENGTH = 65535;

    // MEDIUM TEXT max length, to trim the response, rather than die.
    private const RESPONSE_MAX_LENGTH = 16777215;

    public const MISSING_SESSIONID = 'no-session-';


    /**
     * @return null|User
     */
    public function getUser(): ?User
    {
        return $this->user;
    }


    /**
     * @param ?User $user
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
        // Limit the prompt length to prevent database errors
        if ($prompt && mb_strlen($prompt) > self::PROMPT_MAX_LENGTH) {
            $prompt = mb_substr($prompt, 0, self::PROMPT_MAX_LENGTH);
        }
        $this->prompt = $prompt;
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
        // Limit the input text length to prevent database errors
        if ($inputText && mb_strlen($inputText) > self::TEXT_MAX_LENGTH) {
            $inputText = mb_substr($inputText, 0, self::TEXT_MAX_LENGTH);
        }
        $this->inputText = $inputText;
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
        // Limit the output text length to prevent database errors
        if ($outputText && mb_strlen($outputText) > self::TEXT_MAX_LENGTH) {
            $outputText = mb_substr($outputText, 0, self::TEXT_MAX_LENGTH);
        }
        $this->outputText = $outputText;
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
    public function getFeature(): ?string
    {
        return $this->feature;
    }

    /**
     * @param string|null $feature
     * @return $this
     */
    public function setFeature(?string $feature): self
    {
        $this->feature = $feature;
        return $this;
    }

    /**
     * @return LogType|null
     */
    public function getLogType(): ?LogType
    {
        return LogType::tryFrom($this->logType);
    }

    /**
     * @param string|LogType|null $logType
     * @return $this
     */
    public function setLogType(string|LogType|null $logType): self
    {
        $this->logType = $logType instanceof LogType ? $logType->value : $logType;

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
        // Limit the response length to prevent database errors
        if ($response && mb_strlen($response) > self::RESPONSE_MAX_LENGTH) {
            $response = mb_substr($response, 0, self::RESPONSE_MAX_LENGTH);
        }
        $this->response = $response;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * @param string|null $sessionId
     * @return $this
     */
    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }
}
