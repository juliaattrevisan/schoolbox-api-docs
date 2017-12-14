#!/usr/bin/php
<?php
/**
 * NESA Data Acquisition Crontab
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Help\Nesa;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Mapper\Exception as MapperException;


$fields  = ['help', 'folder:', 'reset'];
$options = require_once __DIR__ . '/../../conf/core/require-cli.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel(
    'NESA Data Acquisition',
    [
        'folder' => isset($options['folder']) ? $options['folder'] : null,
        'reset' => isset($options['reset']) ? $options['reset'] : null,
    ]
);

if (isset($options['help'])) {
    echo <<<HELP
Investigates implementations of the Teacher Course to find out which rubrics were received by users.

Usage: nesa-acquisition.php [--folder] [--reset]
    --folder:   A specific folder to investigate, otherwise processes all folders using Teacher Course
    --reset:     Reset all acquired NESA data


HELP;
    exit;
}

$kernel->run(function (Di\Container $di, LoopInterface $loop, Logger $log, array $options) {
    $folderId = $options['folder'];

    /* If a folder was specified, accumulate the rubric data for this folder only
     * Otherwise process everyone
     */
    if ($folderId) {

        /** @var AssessmentMapperProxy $assessmentMapperProxy */
        $assessmentMapperProxy = $di->get('assessmentDataSource');

        $dataSet = (new Nesa\Acquisition(
            [
                'Student engagement (3.4)',
                'Select and use resources. (3.4)',
                'Engage with professional teaching networks and broader communities. (7.4)',
                'Content selection and organisation. (2.2)',
                'Engage with colleagues and improve practice. (6.3)',
                'Information and communication technology (ICT) (2.6)',
                'Evaluate and improve teaching programs (3.6)'
            ],
            Rubric\RubricMapper::getInstance(),
            $assessmentMapperProxy
        ))->generate($folderId);

        $resultFragment = json_encode($dataSet);

        $log->critical("Acquiring {$folderId}: {$resultFragment}");
    }
    else {
        if (isset($options['reset'])) {
            $log->critical("Resetting everything");
        }
        else {
            $log->critical("Acquiring all folders");
        }
    }

    exit(0);
});
