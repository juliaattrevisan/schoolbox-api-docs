#!/usr/bin/php
<?php

/**
 * Auto-completes quiz attempts which have expired.
 *
 * The attempt completion process usually runs when the student visits
 * `/assess/end/$quizId/$attemptId` - this happens when they click the "Finish"
 * button, or their quiz attempt times out.
 *
 * If the student closes their browser without either of the above happening,
 * the attempt will never be completed. This script finds attempts in the
 * following state:
 * * attempt.finish_time not set
 * * attempt.start_time + attempt.assess.duration + 5 minutes leeway < now
 * and marks them as completed.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new \Schoolbox\Learning\Learnosity\Cli\AutofinishTool();
$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]);
$kernel->runApp();
