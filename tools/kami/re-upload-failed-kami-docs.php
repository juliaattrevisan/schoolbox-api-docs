#!/usr/bin/php
<?php

/**
 * This script searches for all docs with status of 'error' and error_message of 'Conversion failed',
 * and puts them back into the queue for re-uploading.
 *
 * This *must* be deployed in a later release that the code-fix in SA-6354 to guarantee that this script
 * does not execute while we have old-format documents still asynchronously processing in Kami.
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Jitter\Jitter;
use Schoolbox\Learning\InlineGrader\Kami;
use Alaress\Random;
use Schoolbox\MapperFactory;
use Schoolbox\Application\CliTool;

require __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Reupload files to kami if they Kami has reported the files failed to be converted.
These are in the student_submission_file_kami table and this tool only works if
they have status "error" and error_message "Conversion failed".

Usage: {$this->getToolFilename()}


HELP;
            exit;
        }

        /**
         * @var MapperFactory  $mapperFactory
         * @var Kami\Connector $kamiConnector
         * @var Kami\Mapper    $kamiMapper
         */
        $mapperFactory = $this->getDi()->get('mapperFactory');
        $kamiConnector = $this->getDi()->get('kamiConnector');
        $kamiMapper    = $mapperFactory->get(Kami\Mapper::class);
        $jitter        = new Jitter(new Random\FloatGenerator());

        try {
            // Ensure we are as up-to-date with Kami as possible
            $statusChecker = new Kami\StatusUpdater($kamiConnector, $kamiMapper, $jitter, $this->getLogger());
            $statusChecker->checkProcessingDocumentsStatus(false, Kami\StatusUpdater::DEFAULT_MAX_SKIPS);
            $this->getLogger()->info('');

            // Re-queue any docs that are in 'error' because 'Conversion failed'
            $requeueErrors = new Kami\ReQueueErrors($kamiConnector, $kamiMapper, $jitter, $this->getLogger());
            $requeueErrors->deleteAndReQueue(false, Kami\ReQueueErrors::DEFAULT_MAX_SKIPS, 'Conversion failed');
        } catch (Throwable $e) {
            $this->getLogger()->error($e->getMessage(), ['exception' => $e]);
        }
    }
};

try {
    $kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_ERRORS => E_ALL,]);
    $kernel->runApp();
    exit(0);
} catch (Throwable $e) {
    exit(2);
}
