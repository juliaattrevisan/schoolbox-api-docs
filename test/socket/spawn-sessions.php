#!/usr/bin/php
<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__ . '/../../include/Bootstrap.php';

/** @var \Schoolbox\User[] $allusers */
$allusers = \Schoolbox\UserMapper::getInstance()->findAll();

$url = "http://localhost/loginForm.php";

const DELIM = "\n";

foreach($allusers as $user) {

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

	if(preg_match_all('`PHPSESSID=([^;]+);`', $response, $matches)) {
		if(isset($matches[1][1])) {
			printf("%s%s",$matches[1][1], DELIM);
			fputs(STDERR, sprintf("%s => %s\n", $postdata['username'], $matches[1][1]));
		}
		else {
			fputs(STDERR, sprintf("Authentication fail for %s\n", $postdata['username']));
		}
	}

}