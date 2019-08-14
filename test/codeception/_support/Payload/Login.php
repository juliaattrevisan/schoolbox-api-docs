<?php


namespace Payload;


class Login extends Base
{

    public function __construct()
    {
        Base::__construct();
        $this->setPage('/login/');
        $this->setReferer('/login/');
        $this->setFormParam('page', '/login/');
        $this->setFormParam('username', 'username');
        $this->setFormParam('password', 'password');
        $this->setFormParam('Submit', 'Login');
    }

    public function setUsername(string $username)
    {
        $this->setFormParam('username', $username);
    }

    public function setPassword(string $password)
    {
        $this->setFormParam('password', $password);
    }
}