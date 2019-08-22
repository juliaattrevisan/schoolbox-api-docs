<?php


namespace Payload;


class ForgotPassword extends Base
{

    public function __construct()
    {
        Base::__construct();
        $this->setPage('/passwordReset/createRequest');
        $this->setReferer( '/passwordReset');
        $this->setFormParam('email', 'username or email');
    }

    public function setEmailOrUsername(string $email)
    {
        $this->setFormParam('email', $email);
    }
}