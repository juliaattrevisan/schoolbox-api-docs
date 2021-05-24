<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/* Request params
 * strip out any injection attempts
 */
$keyword = trim($_GET['keyword']);
$type = trim(preg_replace('/[^\ A-Za-z0-9\"\'\+\-_=\@\?!\.]/', '', $_GET['type']));
$current_page = empty($_GET['p']) ? 1 : (int)$_GET['p'];
$count = array();
