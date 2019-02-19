<?php

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

// Moved permanently to password reset module
SitePage::localRedir('/passwordReset', false, 301);
