#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Custom\Tigs\Summative\CliPdfGenerator;

require_once __DIR__ . '/../../../conf/core/require-cli.php';

// TODO - START - Delete this once SA-8248 is merged. It will be handled by a single core require.php
require_once __DIR__ . '/../../../www/conf/conf.php';
require_once __DIR__ . '/../../../www/include/stdfuncs.php';
require_once __DIR__ . '/../../../library/constants.php';
set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../../../library/Autoloader.php';
Autoloader::initialise();
// TODO - END

// Usage:
//   ./export-to-pdf.php -vvv --username alaress --year-level 11  --js-delay=500 --now=2018-01-01 --curriculum=ib  -o /vagrant/vagrantcache/outfile.zip

$kernel = new Schoolbox\Application\CliTool\Kernel(new CliPdfGenerator(), 100, E_ALL);
$kernel->runApp();
