<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        http://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

require_once __DIR__ . '/../../../conf/core/require.php';

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

        // default options
        $defaultOptions = [
            'canvas_import_quiz' => 1,
            'canvas_quiz_worktype' => '', // school requires to set this
            'canvas_import_grades' => 1,
            'canvas_grade_groups' => '', // school requires to set this
            'canvas_sissourceid_template' => '{folderCode}{parentCode}',

            'canvas_sync_news' => 0,
            'canvas_news_topic' => '', // school requires to set this
            'canvas_news_author' => '', // school requires to set this

            'canvas_redirect_assessments' => 0,
            'canvas_redirect_homepages' => 0,
            'canvas_code_redirect_homepages' => 'course_code',
        ];

        $newSettings = [];
        $statement = $db->prepare("UPDATE config SET value = :value WHERE name = :name");
        // get the school code from config
        switch (strtolower($this->getConfig()->get(\Schoolbox\Config\ConfigMapper::NAME_SITE_SCHOOL_CODE))) {
            case 'newic':
                $this->getLogger()->notice("Found NEWIC school code, applying specific canvas settings.");
                // NEWIC canvas settings
                $newSettings = array_merge($defaultOptions, [
                    'canvas_redirect_homepages' => 1, // enable homepage redirection
                    'canvas_redirect_assessments' => 1, // enable assessment redirection
                    'canvas_quiz_worktype' => '', // quiz work type (deleted back in 2021-12-12 15:58:18, we are apparently setting it to NULL now... which is wrong...)
                    'canvas_sync_news' => 1, // enable news sync
                    'canvas_news_topic' => 8, // as specified in puppet
                    'canvas_news_author' => 7166, // as specified in puppet
                ]);
                break;
            case 'staloy':
                $this->getLogger()->notice("Found STALOY school code, applying specific canvas settings.");
                // Staloy canvas settings
                $newSettings = array_merge($defaultOptions, [
                    'canvas_import_quiz' => 0, // don't import quizzes
                    'canvas_import_grades' => 0, // don't import grades
                ]);
                break;
            case 'tasfg':
                // TASFG canvas settings
                $this->getLogger()->notice("Found TASFG school code, applying specific canvas settings.");
                $newSettings = array_merge($defaultOptions, [
                    'canvas_import_quiz' => 0, // don't import quizzes
                    'canvas_import_grades' => 0, // don't import grades
                    'canvas_sync_news' => 1, // enable news sync
                    'canvas_news_topic' => 1, // to be refined later
                    'canvas_news_author' => 1, // to be refined later
                    'canvas_redirect_homepages' => 1, // enable homepage redirection
                    'canvas_redirect_assessments' => 0, // no assessment redirection yet
                    'canvas_sissourceid_template' => '{parentCode} {folderCode}',
                    'canvas_code_redirect_homepages' => 'subject_code_section',
                ]);
                break;
            case 'trigs':
                // TRIGS canvas settings
                $this->getLogger()->notice("Found TRIGS school code, applying specific canvas settings.");
                $newSettings = array_merge($defaultOptions, [
                    'canvas_redirect_homepages' => 1, // enable homepage redirection
                    'canvas_sissourceid_template' => '{folderCode}_{parentCode}',
                    'canvas_code_redirect_homepages' => 'subject_code',
                ]);
                break;
            case 'yvgs':
                // YVGS canvas settings
                $this->getLogger()->notice("Found YVGS school code, applying specific canvas settings.");
                $newSettings = array_merge($defaultOptions, [
                ]);
                break;
            default:
                // check if we are a canvas plan (generic)
                if (
                    $this->getConfig()->get(\Schoolbox\Config\ConfigMapper::NAME_CANVAS_ENABLED) ||
                    strtolower($this->getConfig()->get(\Schoolbox\Config\ConfigMapper::NAME_SITE_PLAN)) === 'canvas-elite' || // canvas plan, if the flag is not set for some reason
                    strtolower($this->getConfig()->get(\Schoolbox\Config\ConfigMapper::NAME_SITE_PLAN)) === 'canvas-enhanced' // other canvas plan, if the flag is not set for some reason
                ) {
                    $this->getLogger()->notice("Generic Canvas school, applying default settings.");
                    $newSettings = $defaultOptions;
                } else {
                    $this->getLogger()->notice("No specific canvas settings for this school code. Applying nothing");
                }
        }

        // update the settings
        foreach ($newSettings as $key => $value) {
            $this->getLogger()->debug("Setting {$key}: {$value}");
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
