#!/usr/bin/php
<?php

/**
 * Strip out base64 images from CKEditor fields
 *
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool\App;

require_once __DIR__ . '/../../conf/core/require.php';

$sudo         = 'sudo -u ' . escapeshellarg(App::WEB_PROCESS_USER);
$importScript = 'php ' . __DIR__ . '/../../tools/ckImage/ckImage2Storage.php';
$command      = $sudo . ' ' . $importScript . ' --perform --stdout-stats';

// Run the script, capturing stats output and using that to determine whether to
// background the next part (fixing storage perms)
echo "Running CK2Image extractor command:\n" . $command . "\n";
ob_start();
passthru($command, $resultCode);
$stdout = ob_get_clean();

// What was written to stdout?
$output = json_decode($stdout);

exit($resultCode);
