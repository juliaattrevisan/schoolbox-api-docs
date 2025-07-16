<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

$command = 'php ' . __DIR__ .  '/../../../tools/learnosity/archive-mass-restore.php --perform';

echo "Running Learnosity mass restoration in background process:\n{$command}\n";

$proc = proc_open(
    "($command)",
    [
        0 => STDIN,
        1 => STDOUT,
        2 => STDERR,
    ],
    $pipes,
);

$procStatus = proc_get_status($proc);

printf("Beginning Learnosity mass restoration (pid: %d)\n", $procStatus['pid']);

// Exit: the import will continue to run in background
exit();
