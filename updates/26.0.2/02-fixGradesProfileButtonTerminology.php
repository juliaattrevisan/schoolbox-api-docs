<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\L10n\RosettaStone;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * CLI Tool
 *
 * Copy this to /tools folder
 *
 * To redirect log output to a file(s):
 *     STDOUT is file descriptor 1
 *     STDERR is file descriptor 2; our logger output ends up going here
 *
 *     Example: php example.php 2> log.txt
 */
$app = new class extends CliTool\App
{
    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

This fixes the "grades" profile button's label/tooltip to be in proper plural form rather than proper singular form.
If a school has customised the button text, then this will not override it.

Usage: 02-fixGradesProfileButtonTerminology.php

HELP;
            return;
        }

        $db = $this->getDatabaseInternal();

        /** @var RosettaStone $rosettaStone */
        $rosettaStone = $this->getDi()->get('l10n');

        /* If you were to run the original migration today, you'd get this: (proper singular form) */
        $originalTerm = $rosettaStone->translate('grades', false, true);
        /* It should have been the proper plural form instead: */
        $correctTerm = $rosettaStone->translate('grades', true, true);

        /* Update the button title only if it hasn't been customised yet after the original migration */
        $titleResult = $db->executeQuery(
            "UPDATE profile_button
            SET `title` = :correctTerm
            WHERE `title` = :originalTerm AND `type` = 'grades'",
            [ 'correctTerm' => $correctTerm, 'originalTerm' => $originalTerm ]
        );

        if ($titleResult->rowCount() === 0) {
            $this->getLogger()->debug('Skipped updating customised grades profile button (title).');
        } else {
            $this->getLogger()->info(sprintf('Updated grades profile button title to "%s".', $correctTerm));
        }

        /* Update the button text (tooltip) only if it hasn't been customised yet after the original migration */
        $textResult = $db->executeQuery(
            "UPDATE profile_button
            SET `text` = :correctTerm
            WHERE `text` = :originalTerm AND `type` = 'grades'",
            [ 'correctTerm' => $correctTerm, 'originalTerm' => $originalTerm ]
        );

        if ($textResult->rowCount() === 0) {
            $this->getLogger()->debug('Skipped updating customised grades profile button (text).');
        } else {
            $this->getLogger()->info(sprintf('Updated grades profile button text to "%s".', $correctTerm));
        }
    }


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options, true))->runApp();
