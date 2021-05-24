<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Proxy\Test;

class ProxyServerTestConfigLoader implements \Schoolbox\IConfigLoader
{

    protected $auth = [];

    public function __construct($host, $port, $user, $pass)
    {
        $this->auth = [
            'proxy_host' => $host,
            'proxy_port' => $port,
            'proxy_user' => $user,
            'proxy_pass' => $pass,
        ];
    }

    public function get($var)
    {
        if (isset($this->auth[$var])) {
            return $this->auth[$var];
        }
        return null;
    }

    public function dropCache()
    {
    }
}
