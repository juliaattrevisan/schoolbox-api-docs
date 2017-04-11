<?php

require_once(dirname(__FILE__) . '/../library/Bootstrap.php');

use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\v3\AccessTokenGenerator;
use \Schoolbox\Config\Loader;

$config = new Loader();

(new AccessTokenGenerator($config->get('plagscan_client_id'), $config->get('plagscan_api_key')))->generate();

