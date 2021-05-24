#!/usr/bin/php
<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\Daemon\Kernel(__FILE__);

/** @var \Schoolbox\User[] $allusers */
$allusers = \Schoolbox\UserMapper::getInstance()->findAll();

$url = "http://localhost/loginForm.php";

const DELIM = "\n";

foreach ($allusers as $user) {
    $postdata = array(
        'username' => $user->getUsername(),
        'password' => 'password',
        'page'     => '/',
        'Submit'   => 'Sign in',
        'rememberme' => 1
    );

    $c = curl_init($url);
    curl_setopt_array($c, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postdata,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true
    ));

    $response = curl_exec($c);

    if (preg_match_all('`PHPSESSID=([^;]+);`', $response, $matches)) {
        if (isset($matches[1][1])) {
            printf("%s%s", $matches[1][1], DELIM);
            fputs(STDERR, sprintf("%s => %s\n", $postdata['username'], $matches[1][1]));
        } else {
            fputs(STDERR, sprintf("Authentication fail for %s\n", $postdata['username']));
        }
    }
}
