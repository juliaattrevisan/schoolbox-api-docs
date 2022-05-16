<?php

/**
 * Defines global constants that will be used throughout the system.
 * This file is architecturally compatible with both on-premises/cloud installs (ie single/multi tenant)
 */

declare(strict_types=1);

define('SITE_root', dirname(__DIR__, 2));
define('WEB_root', SITE_root . '/www');

# Per Schoolbox install config, common across all instances
$config = parse_ini_file("/etc/schoolbox/config.ini");

// Are we executing in the context of a specific tenant/instance?
$instances  = parse_ini_file("/etc/schoolbox/instances.ini", true);
$instanceId = null;
$dbHost     = null;
$dbSchema   = null;
$dbUser     = null;
$dbPass     = null;
if (PHP_SAPI === 'cli') {
    // CLI invocation.
    $instanceOpt = getopt('', ['instance:']);
    $instanceId  = $instanceOpt['instance'] ?? (getenv('INSTANCE_ID', true) ?: null);

    // TODO temp hack while we back-port multi-instance arch.
    if (!$instanceId && count($instances) === 1) {
        $instanceId = array_keys($instances)[0];
    }
    if ($instanceId) {
        if (array_key_exists($instanceId, $instances)) {
            $dbHost   = $instances[$instanceId]['db_host'] ?? null;
            $dbSchema = $instances[$instanceId]['db_schema'] ?? null;
            $dbUser   = $instances[$instanceId]['db_user'] ?? null;
            $dbPass   = $instances[$instanceId]['db_pass'] ?? null;
        } else {
            // Fall back to environment vars if not yet in instances.ini file
            // This enables us to invoke PHP script prior to deploying 'instances.ini', eg during installation
            $dbHost   = $dbHost ?? (getenv('SQL_HOST', true) ?: null);
            $dbSchema = $dbSchema ?? (getenv('SQL_SCHEMA', true) ?: null);
            $dbUser   = $dbUser ?? (getenv('SQL_USER', true) ?: null);
            $dbPass   = $dbPass ?? (getenv('SQL_PASS', true) ?: null);
        }
    }
} else {
    // Web request
    $hostname = ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? '') ?: $_SERVER['HTTP_HOST'];
    $hostname = explode(':', $hostname)[0];
    foreach ($instances as $instanceIdKey => $instance) {
        if (in_array($hostname, $instance['hostnames'], true)) {
            $instanceId = $instanceIdKey;
            $dbHost     = $instance['db_host'] ?? null;
            $dbSchema   = $instance['db_schema'] ?? null;
            $dbUser     = $instance['db_user'] ?? null;
            $dbPass     = $instance['db_pass'] ?? null;
            break;
        }
    }
}
define('INSTANCE_ID_LIST', array_keys($instances));
define('INSTANCE_ID', $instanceId);
define('SQL_host', $dbHost);
define('SQL_user', $dbUser);
define('SQL_db', $dbSchema);
define('SQL_passwd', $dbPass);
define('SQL_dsn', 'mysql:host=' . SQL_host . ';dbname=' . SQL_db);

define('TEMPLATE_body', $config['TEMPLATE_body']);
define('TEMPLATE_content', $config['TEMPLATE_content']);
define('SENTRY_TOKEN', $config['SENTRY_TOKEN']);
define('SENTRY_PROJECT', $config['SENTRY_PROJECT']);
