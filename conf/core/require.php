<?php

/**
 * Schoolbox global dependencies
 *
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// Instance config
require_once __DIR__ . '/../../www/conf/conf.php';

// Fast fail if INSTANCE_ID not set or empty
if (!defined('INSTANCE_ID') || trim((string)INSTANCE_ID) === '') {
    // Did front-controller not declare that we explicitly wish to permit running without a valid INSTANCE_ID ?
    if (!defined('PERMIT_INVALID_INSTANCE_ID') || !PERMIT_INVALID_INSTANCE_ID) {
        $availableInstanceIds = implode(',', INSTANCE_ID_LIST ?: []);
        throw new UnexpectedValueException("'INSTANCE_ID' not set. Available: [$availableInstanceIds]");
    }
}

// I'm not sure what this does anymore?
\set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . \get_include_path());

// Configure autoloading with Composer
require __DIR__ . '/../../vendor/autoload.php';

// Global functions and constants
require_once __DIR__ . '/../../www/include/stdfuncs.php';
