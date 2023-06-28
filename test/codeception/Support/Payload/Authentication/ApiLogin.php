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

class ApiLogin extends Request
{
    use PostTrait;

    public function __construct()
    {
        $this->setUsername('');
        $this->setPassword('');
        $this->setRedirectPageQueryParam('');
    }

    public function setUsername(string $username): void
    {
        $this->setFormParam('username', $username);
    }

    public function setPassword(#[SensitiveParameter] string $password): void
    {
        $this->setFormParam('password', $password);
    }

    public function setToken(string $token): void
    {
        $this->setFormParam('token', $token);
    }

    public function setRedirectPageQueryParam(string $page): void
    {
        if ($page !== '') {
            $this->setQueryParam('redirect', $page);
        } else {
            if ($this->getQueryParam('redirect') != null) {
                $this->unsetQueryParam('redirect');
            }
        }
    }

    public function getUrl(): string
    {
        return '/api/session';
    }
}
