<?php

require_once __DIR__ . '/../conf/core/require-web.php';

// Moved permanently to password reset module
SitePage::localRedir('/passwordReset', false, 301);
