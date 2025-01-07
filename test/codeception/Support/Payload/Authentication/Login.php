<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Payload\Authentication;

use Tests\Support\Payload\PostTrait;
use Tests\Support\Payload\Request;
use SensitiveParameter;

class Login extends Request
{
    use PostTrait;

    const LOGIN_URL = '/login/';

    public function __construct()
    {
        $this->setReferer(self::LOGIN_URL);

        $this->setFormParam('page', '/login/');
        $this->setFormParam('username', '');
        $this->setFormParam('password', '');
        $this->setFormParam('Submit', 'Login');
    }

    public function setUsername(string $username): void
    {
        $this->setFormParam('username', $username);
    }

    public function setPassword(#[SensitiveParameter] string $password): void
    {
        $this->setFormParam('password', $password);
    }

    public function getUrl(): string
    {
        return '/login/';
    }
}
