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

    private User $user;
    private string $prompt;
    private ?int $inputTokens = null;
    private ?int $outputTokens = null;
    private ?string $model = null;
    private ?string $response = null;
    private int $returnCode;
    private string $errorMessage;

    // MEDIUM TEXT max length, to trim the response, rather than die.
    private const RESPONSE_MAX_LENGTH = 16777215;


    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }


    /**
     * @param User $user
     * @return $this
     */
    public function setUser(User $user): self
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
        $this->prompt = $prompt;
        return $this;
    }


    /**
     * @return int
     */
    public function getReturnCode(): int
    {
        return $this->returnCode;
    }


    /**
     * @param int $returnCode
     * @return $this
     */
    public function setReturnCode(int $returnCode): self
    {
        $this->returnCode = $returnCode;
        return $this;
    }


    /**
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }


    /**
     * @param string $errorMessage
     * @return $this
     */
    public function setErrorMessage(string $errorMessage): self
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
        if ($response && strlen($response) > self::RESPONSE_MAX_LENGTH) {
            $response = substr($response, 0, self::RESPONSE_MAX_LENGTH);
        }
        $this->response = $response;
        return $this;
    }
}
