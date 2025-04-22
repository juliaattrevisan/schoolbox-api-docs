#!/usr/bin/php
<?php

/**
 * This file iterates over each class and then retrieves all the assessments.  It will create those assessments
 * in the remote markbook system and then proceed to loop over all the grades and load those into the system.
 *
 * This has been designed so it can be run multiple times, if the corresponding assessment already exists it will be
 * updated.
 *
 * @stolenfrom  James Leckie <jleckie@alaress.com.au>
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use External\IConnector;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App
{
    private const OPT_LONG_HELP = 'help';
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_START = 'start:';
    private const OPT_LONG_END = 'end:';
    private const OPT_LONG_YEAR = 'year:';
    private const OPT_LONG_SEMESTER = 'semester:';
    private const OPT_LONG_UPDATE_ONLY = 'update-only';
    private const OPT_LONG_PARENT = 'parent:';

    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_START,
            self::OPT_LONG_END,
            self::OPT_LONG_YEAR,
            self::OPT_LONG_SEMESTER,
            self::OPT_LONG_UPDATE_ONLY,
            self::OPT_LONG_PARENT,
        ];
    }

    /**
     * Recursive function that retrieves the children of a folder that have class codes attached.
     *
     * @param int $folderId
     * @param Schoolbox\Resource\Folder\Mapper $folderMapper
     * @param array $catsWithCodes
     * @return Folder[]
     */
    private function traverse($folderId, $folderMapper, $catsWithCodes = [])
    {
        $allFolders = $folderMapper->findByParent($folderId);

        foreach ($allFolders as $currentFolder) {
            if (strlen($currentFolder->getCodeString())) {
                $catsWithCodes[$currentFolder->getId()] = $currentFolder;
            }

            $catsWithCodes = $this->traverse($currentFolder->getId(), $folderMapper, $catsWithCodes);
        }

        return $catsWithCodes;
    }

    /**
     * Display help text
     *
     * @param string $externalType
     */
    private function help(string $externalType)
    {
        $additionalParameters = "";
        $addParamsShort = "";

        // Synergetic receives some additional options around FileYear/FileSemester
        if ($externalType === IConnector::EXTERNAL_TYPE_SYNERGETIC) {
            $additionalParameters .= "    --year=yyyy          Synergetic FileYear into which to export the markbook and student marks, format --year=2016 (must be used with semester parameter)\n";
            $additionalParameters .= "    --semester=s         Synergetic FileSemester into which to export the markbook and student marks, --semester=1 (must be used with year parameter)\n";
            $addParamsShort = "[--year=yyyy] [--semester=s] ";
        }

        // Get pretty uppercased SIS name

        $remoteSystemName = ucwords($externalType);

            echo <<<HELP
    
    Schoolbox Markbook Sync Script
    
    Description:   Syncs existing markbook data from Schoolbox into {$remoteSystemName}
    Usage:         php sync.php --parent=[folderID] {$addParamsShort}[--start=yyyy-mm-dd] [--end=yyyy-mm-dd] [--perform] [--update-only]
    
        --parent=xxx         the Schoolbox ID of parent folder to start the traversal, defaults to 0, format --parent=237
        --start=yyyy-mm-dd   Only sync work due after this date, format --start=2016-04-20, defaults to current semester
        --end=yyyy-mm-dd     Only sync work due before this date, format --end=2016-08-15, default to current semester
        -p, --perform:       if set, will actually update anything in the remote system, otherwise just a dry run
        --update-only:       if set, will not create any new items in the remote system, only updates existing markbooks
    {$additionalParameters}
    
    HELP;
    }


    public function run(): void
    {
        $parentId = $this->getOptAsInt(self::OPT_LONG_PARENT, 0);
        $year = $this->getOptAsInt(self::OPT_LONG_YEAR);
        $semester = $this->getOptAsInt(self::OPT_LONG_SEMESTER);
        $start = $this->getOpt(self::OPT_LONG_START);
        $end = $this->getOpt(self::OPT_LONG_END);
        $updateOnly = $this->getOpt(self::OPT_LONG_UPDATE_ONLY);

        // Make sure markbook sync is actually enabled
        /** @var IConfigLoader $config */
        $config = $this->getConfig();
        $externalType = $config->get(ConfigMapper::NAME_EXTERNAL_TYPE);
        if (!$config->get(ConfigMapper::NAME_EXTERNAL_MARKBOOK)) {
            $this->getLogger()->error("ERROR: External markbook synchronisation is not enabled in Schoolbox. Please enable in your Schoolbox administrator settings and try again.");
            exit(1);
        }

        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            $this->help($externalType);
            exit(0);
        }

        /** @var AssessmentDataSource $assessmentDataSource */
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');

        // Get instance of mappers

        $folderMapper = FolderMapper::getInstance();

        // Synergetic Markbook uses FileYear/FileSemester and exposes command line parameters not available to other connectors.
        if ($externalType === IConnector::EXTERNAL_TYPE_SYNERGETIC) {
            try {
                $markbookExternal = new External_Synergetic_Markbook($config->get('synergetic_mark_sync_value'), $this->getLogger(), $year, $semester);
            } catch (InvalidArgumentException $e) {
                $this->getLogger()->error("ERROR: {$e->getMessage()}");
                $this->help($externalType);
                exit(1);
            }
        } else {
            $markbookExternal = new External_Markbook();
        }

        // Determine the time period we want to sync


        if ($start && $end) {
            // Start and end range passed on commandline, use supplied dates

            try {
                $startTime = new Alaress_Date_Format($start, Alaress_Date_Format::DATABASE_DATE_FORMAT);
                $endTime = new Alaress_Date_Format($end, Alaress_Date_Format::DATABASE_DATE_FORMAT);

                // Hardcode the time to start/end of the day

                $startTime->setTime(0, 0, 0);
                $endTime->setTime(23, 59, 59);

                if ($startTime->gt($endTime)) {
                    $this->getLogger()->error("ERROR: Invalid date range supplied, end date must be later than the start date.");
                }
            } catch (InvalidArgumentException $e) {
                // Creation of date/time objects failed, format must be wrong

                $this->getLogger()->error("ERROR: Invalid date supplied, please check date format matches (" . Alaress_Date_Format::DATABASE_DATE_FORMAT . ").");
                exit(1);
            }
        } elseif (date('n') > 6) {
            // If we have gone past June- assume a fake "Semester 2" and use the last six months of the year
            $startTime = new Alaress_Date_Format();
            $endTime = new Alaress_Date_Format();
            $startTime->setDate(date('Y'), 7, 1)->setTime(0, 0, 0);
            $endTime->setDate(date('Y'), 12, 30)->setTime(23, 59, 59);
        } else {
            // Otherwise use a fake "Semester 1" and use the first six months of the year
            $startTime = new Alaress_Date_Format();
            $endTime = new Alaress_Date_Format();
            $startTime->setDate(date('Y'), 1, 1)->setTime(0, 0, 0);
            $endTime->setDate(date('Y'), 6, 30)->setTime(23, 59, 59);
        }

        // Retrieve all of the folders that we are going to traverse
        /** @var Folder[] $folderList */
        $folderList = [];

        // If we aren't starting at the root folder, include the actual root folder

        if ($parentId) {
            $folderList[$parentId] = $folderMapper->findById($parentId);
            /** @noinspection AdditionOperationOnArraysInspection */
            $folderList += $this->traverse($parentId, $folderMapper);
        } else {
            $folderList = $folderMapper->findByPredicates(
                $folderMapper->getPredicateBuilder()->notNull('cc.code')
            );
        }


        // Make sure we got a result

        if (count($folderList) === 0) {
            $this->getLogger()->info('No matching folders were found, nothing to sync.');
            exit(0);
        }

        // Iterate all assessments in these folders

        $this->getLogger()->info('Markbook synchronisation starting...');
        $this->getLogger()->info("Syncing work due between {$startTime->getLocalShort()} and {$endTime->getLocalShort()}...");

        $totalSuccess = 0;
        $totalFailure = 0;

        foreach ($folderList as $folder) {
            $action = str_pad("Processing folder: ", 34, " ");

            if (!empty($folder->getCodeString())) {
                $code = "{$folder->getId()} - {$folder->getCodeString()}";
            } else {
                $code = "{$folder->getId()}";
            }

            $this->getLogger()->info("{$action}{$folder->getName()} ({$code})");

            // Process assessments

            $assessmentList = $assessmentDataSource->findAllByFolderIdForReview(
                $folder->getId(),
                null,
                [
                    'due_after' => $startTime,
                    'due_before' => $endTime,
                ]
            );

            foreach ($assessmentList as $assessment) {
                if (!$markbookExternal->isSyncable($assessment)) {
                    continue;
                }

                $action = str_pad("Assessment:", 30, " ");

                $this->getLogger()->info("    {$action}{$assessment->getTitle()} ({$assessment->getId()})");

                // Check if we are allowed to create new markbook entries

                if (!$updateOnly) {
                    // Creation allowed

                    $action = str_pad("Action:", 30, " ");
                    $this->getLogger()->info("    {$action}Create/Update");

                    if ($this->perform()) {
                        $markbookExternal->syncAssessment($assessment);
                    }
                } else {
                    // No creation of new assessments in markbook is allowed

                    $action = str_pad("Action:", 30, " ");
                    $this->getLogger()->info("    {$action}Update Only");

                    if ($this->perform() && $markbookExternal->hasAssessment($assessment)) {
                        $markbookExternal->syncAssessment($assessment);
                    }
                }

                // Retrieve the class list for the submission box
                $feedbackCount = 0;
                $noFeedbackCount = 0;
                foreach ($assessment->getAllParticipants() as $participant) {
                    if ($participant->hasPublishedFeedback()) {
                        $feedbackCount++;
                    } else {
                        $noFeedbackCount++;
                    }
                }

                $this->getLogger()->info(sprintf("    %s%d", str_pad("Assessment Feedback Found:", 30, " "), $feedbackCount));
                $this->getLogger()->info(sprintf("    %s%d", str_pad("Assessment Feedback Missing:", 30, " "), $noFeedbackCount));

                foreach ($assessment->getAllParticipants() as $participant) {
                    $feedback = $participant->findMostRecentInstructorGivenPublishedFeedback();
                    $student = $participant->getUser();

                    // Output the HR and normalized mark that are synced
                    $markLog = '(missing)';
                    $level = 'info';

                    if ($feedback) {
                        $hrMark = $feedback->getHumanReadableMark();
                        $normMark = $feedback->getNormalisedMark();
                        $markLog = sprintf('%s (%s)', $hrMark, isset($normMark) ? round($normMark, 2) : 'null');
                    }


                    if ($feedback && $this->perform()) {
                        try {
                            $markbookExternal->syncFeedback($feedback);
                            $totalSuccess++;
                        } catch (InvalidArgumentException | UnderflowException $e) {
                            $level = 'warning';
                            $markLog = '(' . $e->getMessage() . ')';
                            $totalFailure++;
                        }
                    }

                    $this->getLogger()->$level(
                        sprintf(
                            "        %s%s",
                            str_pad("{$student->getFirstname()} {$student->getLastname()} ({$student->getExternalId()}):", 26, " "),
                            $markLog
                        )
                    );
                }
            }
        }

        $totalAttempts = $totalFailure + $totalSuccess;
        $this->getLogger()->info(
            sprintf(
                "Sync completed: %d successful, %d failed (%d%%)",
                $totalSuccess,
                $totalFailure,
                // Maybe no results were processed: don't DBZ
                $totalAttempts === 0 ? 0 : ($totalFailure / $totalAttempts * 100)
            )
        );
    }
};

$kernel = new CliTool\Kernel($app, [
    CliTool\Kernel::OPTION_ERRORS => E_ALL
]);
$kernel->runApp();
