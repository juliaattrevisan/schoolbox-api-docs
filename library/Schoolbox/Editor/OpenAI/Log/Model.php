<?php

/**
 * Model
 *
 * @author      James Leckie <james.leckie@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Editor\OpenAI\Log;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

class Model
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;

    /**
     * @var User
     */
    private $user;

    private string $prompt;
    private int $return_code;
    private string $error_message;

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setPrompt(string $prompt): void
    {
        $this->prompt = $prompt;
    }

    public function getReturnCode(): int
    {
        return $this->return_code;
    }

    public function setReturnCode(int $return_code): void
    {
        $this->return_code = $return_code;
    }

    public function getErrorMessage(): string
    {
        return $this->error_message;
    }

    public function setErrorMessage(string $error_message): void
    {
        $this->error_message = $error_message;
    }
}
