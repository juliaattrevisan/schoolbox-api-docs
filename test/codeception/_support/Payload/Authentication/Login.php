<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload;

class Login extends Request
{
    use PostTrait;

    public function __construct()
    {
        $this->setReferer(\Page\Login::URL);
        $this->setFormParam('page', '/login/');
        $this->setFormParam('username', '');
        $this->setFormParam('password', '');
        $this->setFormParam('Submit', 'Login');
    }

    public function setUsername(string $username): void
    {
        $this->setFormParam('username', $username);
    }

    public function setPassword(string $password): void
    {
        $this->setFormParam('password', $password);
    }

    public function getUrl(): string
    {
        return '/login/';
    }
}
