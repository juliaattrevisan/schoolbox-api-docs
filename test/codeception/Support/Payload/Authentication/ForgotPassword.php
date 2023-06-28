<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Support\Payload\Authentication;

use Tests\Support\Payload\PostTrait;
use Tests\Support\Payload\Request;

class ForgotPassword extends Request
{
    use PostTrait;

    public function __construct()
    {
        $this->setReferer('/passwordReset');

        $this->setEmailOrUsername('');
    }

    public function setEmailOrUsername(string $email): void
    {
        $this->setFormParam('email', $email);
    }

    protected function getUrl(): string
    {
        return '/passwordReset/createRequest';
    }
}
