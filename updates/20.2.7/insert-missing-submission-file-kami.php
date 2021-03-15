#!/usr/bin/php
<?php
if (!file_exists('/etc/schoolbox/instances.ini')) {
    echo "This script runs only on a production server.\n";
    exit();
}
passthru('php ' . __DIR__ .  '/../../tools/kami/insert-missing-submission-file-kami.php -p', $returnVar);
exit($returnVar);
