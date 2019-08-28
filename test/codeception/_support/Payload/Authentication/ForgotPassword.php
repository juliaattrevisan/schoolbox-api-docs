<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload;

class ForgotPassword extends Request
{
    use PostTrait;

    public function __construct()
    {
        $this->setReferer('/passwordReset');

        $this->setEmailOrUsername('');
    }

    public function setEmailOrUsername(string $email): self
    {
        return $this->setFormParam('email', $email);
    }

    protected function getUrl(): string
    {
        return '/passwordReset/createRequest';
    }
}
