<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        http://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

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
     * Run the cli tool
     */
    public function run(): void
    {
        $db = $this->getDatabaseInternal();

        $newSettings = [];
        $statement = $db->prepare("UPDATE settings SET value = :value WHERE name = :name");
        // get the school code from config
        switch (strtolower($this->getConfig()->get(\Schoolbox\Config\ConfigMapper::NAME_SITE_SCHOOL_CODE))){
            case 'newic':
                // NEWIC canvas settings
                $newSettings = [
                    'canvas_redirect_homepages' => 1, // enable homepage redirection
                    'canvas_redirect_assessments' => 1, // enable assessment redirection
                    'canvas_sissourceid_template' => '{folderCode}{parentCode}',
                    'canvas_import_quiz' => 1, // import quizzes
                    'canvas_quiz_worktype' => '', // quiz work type (deleted back in 2021-12-12 15:58:18, we are apparently setting it to NULL now... which is wrong...)
                    'canvas_sync_news' => 1, // enable news sync
                    'canvas_news_topic' => 8, // as specified in puppet
                    'canvas_news_author' => 7166, // as specified in puppet
                    'canvas_import_grades' => 1, // import grades
                ];
                break;
            case 'staloy':
                // Staloy canvas settings
                $newSettings = [
                    'canvas_redirect_homepages' => 0, // disable homepage redirection
                    'canvas_sync_news' => 0, // disable news sync
                    'canvas_redirect_assessments' => 0, // disable assessment redirection
                ];
                break;
            case 'tasfg':
                // TASFG canvas settings
                $newSettings = [
                    'canvas_import_quiz' => 0, // don't import quizzes
                    'canvas_import_grades' => 0, // don't import grades
                    'canvas_sync_news' => 1, // enable news sync
                    'canvas_news_topic' => 1, // to be refined later
                    'canvas_news_author' => 1, // to be refined later
                    'canvas_redirect_homepages' => 1, // enable homepage redirection
                    'canvas_redirect_assessments' => 0, // no assessment redirection yet
                    'canvas_sissourceid_template' => '{parentCode} {folderCode}',
                    'canvas_code_redirect_homepages' => 'subject_code_section',
                ];
                break;
            case 'trigs':
                // TRIGS canvas settings
                $newSettings = [
                    'canvas_redirect_homepages' => 1, // enable homepage redirection
                    'canvas_sync_news' => 0, // disable news sync
                    'canvas_redirect_assessments' => 0, // disable assessment redirection
                ];
                break;
            case 'yvgs':
                // YVGS canvas settings
                $newSettings = [
                    'canvas_redirect_homepages' => 0, // disable homepage redirection
                    'canvas_sync_news' => 0, // disable news sync
                    'canvas_redirect_assessments' => 0, // disable assessment redirection
                ];
                break;
        }

        // update the settings
        foreach ($newSettings as $key => $value) {
            $statement->execute([':name' => $key, ':value' => $value]);
        }
    }

    /**
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

