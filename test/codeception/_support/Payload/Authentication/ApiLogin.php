<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Authentication;

use Payload\PostTrait;
use Payload\Request;

class ApiLogin extends Request
{
    use PostTrait;

    public function __construct(string $apiID)
    { //appId=james&username=drhodes&password=password&token=123
        $this->setFormParam('appId', $apiID);
        $this->setUsername('');
        $this->setPassword('');
        $this->setToken('');
        $this->setRedirectPageQueryParam('');
    }

    public function setUsername(string $username): void
    {
        $this->setFormParam('username', $username);
    }

    public function setPassword(string $password): void
    {
        $this->setFormParam('password', $password);
    }

    public function setToken(string $token): void
    {
        $this->setFormParam('token', $token);
    }

    public function setRedirectPageQueryParam(string $page): void
    {
        if ($page != '' && isset($page))
        {
            $this->setQueryParam('redirect', $page);
        }
        else
        {
            if ($this->getQueryParam('redirect') != null)
            {
                $this->unsetQueryParam('redirect');
            }
        }

    }

    public function getUrl(): string
    {
        return '/api/session';
    }
}
