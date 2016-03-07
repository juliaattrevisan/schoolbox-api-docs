<?php

require_once __DIR__ . '/../include/Bootstrap.php';

// Moved permanently to password reset module
SitePage::localRedir('/passwordReset', false, 301);
