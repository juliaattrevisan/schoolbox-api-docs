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
        return $this->return_code;
    }


    /**
     * @param int $return_code
     * @return $this
     */
    public function setReturnCode(int $return_code): self
    {
        $this->return_code = $return_code;
        return $this;
    }


    /**
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->error_message;
    }


    /**
     * @param string $error_message
     * @return $this
     */
    public function setErrorMessage(string $error_message): self
    {
        $this->error_message = $error_message;
        return $this;
    }
}
