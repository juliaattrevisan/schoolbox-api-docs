<?php
/**
 * Alaress user must exist; if it does not, that is an error.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Verify alaress user exists', [
    'verbose' => [ false, false, false ],
    'errors'  => E_ALL,
]);

$kernel->run(function($di, $loop, LoggerInterface $log) {
    $database         = Database::getInstance();
    $alaressUserCount = intval($database->query("SELECT COUNT(*) FROM user WHERE username = 'alaress'")->fetch());
    if ($alaressUserCount === 0) {
        $log->error("No user \"alaress\" exists! - foreign key updates are likely to fail");
        exit(1);
    }
    $log->notice("User \"alaress\" exists - foreign key updates should succeed");
    exit(0);
});
