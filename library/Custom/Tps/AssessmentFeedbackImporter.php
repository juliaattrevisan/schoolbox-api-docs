<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Tps;

use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Learning\Assessment\DueWork\Feedback\MapperProxy as DueWorkFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Schoolbox\Admin\Import\Profile\Reader;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Factory;

/**
 * A CLI App for batch saving feedback for assessments
 */
class AssessmentFeedbackImporter extends CliTool\App
{
    // This tools CLI options
    private const OPT_SHORT_HELP  = 'h';                 // print help
    private const OPT_LONG_HELP   = 'help';              // print help
    private const OPT_LONG_FILE   = 'file:';             // csv file
    private const OPT_LONG_FOLDER = 'folder:';           // attachment folder

    // Columns of csv file
    private const         COL_STUDENT_SYNERGETIC_ID = 'Student Synergetic ID';
    private const         COL_STAFF_SYNERGETIC_ID   = 'Marker Synergetic ID';
    private const         COL_COMMENT               = 'Comment';
    private const         COL_ASSESSMENT_ID         = 'Assessment Id';

    /** @var string|null */
    private $file;

    /** @var string|null */
    private $folder;

    /** @var int */
    private $count = 0;

    /** @var UserMapper */
    private $userMapper;

    /** @var AssessmentDataSource */
    private $assessmentDataSource;

    /** @var ParticipantDataSource */
    private $participantDataSource;

    /** @var StorageMapper */
    private $storageMapper;

    /** @var DueWorkFeedbackMapperProxy $feedbackDataSource */
    private $feedbackDataSource;

    /** @inheritdoc */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FILE,
            self::OPT_LONG_FOLDER,
        ];
    }

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        // Did user request help?
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        // Parse command-line options. If invalid options detected, then print help and exit.
        if (!$this->parseAppOptions()) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        if (!$this->perform()) {
            $this->getLogger()->notice('Executing in dry run mode. Provide --perform to update DB.');
        }

        // Get mappers
        $di = $this->getDi();
        /** @var MapperFactory $mapperFactory */
        $mapperFactory               = $this->getDi()->get('mapperFactory');
        $this->userMapper            = $mapperFactory->get(UserMapper::class);
        $this->assessmentDataSource  = $di->get('assessmentDataSource');
        $this->participantDataSource = $di->get('participantDataSource');
        $this->storageMapper         = StorageMapper::getInstance();
        $this->feedbackDataSource    = $di->get('feedbackDataSource');

        // Import csv
        $reader = new Reader($this->file, true, true);
        if (!$reader->readHeaderRow()) {
            $this->getLogger()->warning("Unable to read header from the file: {$this->file}");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        $reader->setExpectedColumns([
            self::COL_ASSESSMENT_ID,
            self::COL_STAFF_SYNERGETIC_ID,
            self::COL_STUDENT_SYNERGETIC_ID,
            self::COL_COMMENT,
        ]);
        $reader->next(); // header

        $currentFeedbackRows = [];
        $currentAssessmentId = 0;

        $reader->next();
        while ($reader->valid()) {
            try {
                $row              = $reader->current();
                $nextAssessmentId = (int)$row[self::COL_ASSESSMENT_ID];
            } catch (\InvalidArgumentException $e) {
                // noop
                $reader->next();
                continue;
            }
            if ($currentAssessmentId !== $nextAssessmentId) {
                $this->process($currentAssessmentId, $currentFeedbackRows);
                // reset
                $currentFeedbackRows = [$row];
                $currentAssessmentId = $nextAssessmentId;
            } else {
                $currentFeedbackRows[] = $row;
            }
            $reader->next();
        }

        // Process remaining feedback
        $this->process($currentAssessmentId, $currentFeedbackRows);

        if ($this->perform()) {
            $this->getLogger()->info("Processed {$this->count} feedback.");
        } else {
            $this->getLogger()->info("{$this->count} feedback will be processed.");
        }
    }

    /**
     * Inserts feedback for given assessment
     * @param int   $assessmentId
     * @param array $feedbackRows
     */
    private function process(int $assessmentId, array $feedbackRows)
    {
        if ($assessmentId === 0 || empty($feedbackRows)) {
            return;
        }

        try {
            $assessment = $this->assessmentDataSource->findById($assessmentId);
            $this->getLogger()->info("Assessment: {$assessment->getTitle()}");
            $this->participantDataSource->loadParticipationIntoAssessment($assessment);
        } catch (MapperException $e) {
            $this->getLogger()->warning("Unable to find assessment with id: {$assessmentId}");
            return;
        }

        foreach ($feedbackRows as $row) {
            $studentExternalId = $row[self::COL_STUDENT_SYNERGETIC_ID];
            $staffExternalId   = $row[self::COL_STAFF_SYNERGETIC_ID];
            $comment           = $row[self::COL_COMMENT];

            try {
                $staff = $this->userMapper->findByExternalId($staffExternalId, Role::STAFF);
            } catch (MapperException $e) {
                $this->getLogger()->warning("Unable to find staff with external ID: {$staffExternalId}");
                continue;
            }
            try {
                // not limiting to the student role for now, to allow non-student participants.
                $student = $this->userMapper->findByExternalId($studentExternalId);
            } catch (MapperException $e) {
                $this->getLogger()->warning("Unable to find student with external ID: {$studentExternalId}");
                continue;
            }
            $participant = $assessment->getParticipantIfExtant($student);
            if (!$participant) {
                $this->getLogger()->warning("{$student->getFullName()} {$studentExternalId} is not a participant of this assessment");
                continue;
            }

            $attachments       = null;
            $attachmentMessage = '';
            if ($this->folder) {
                $attachmentFilename = "{$studentExternalId}-{$assessmentId}.pdf";
                $path               = "{$this->folder}/{$attachmentFilename}";
                if (file_exists($path)) {
                    if (is_file($path) && is_readable($path)) {
                        $attachmentMessage = " with attachment {$attachmentFilename}";
                        if ($this->perform()) {
                            try {
                                $attachment  = $this->storageMapper->acceptStream($attachmentFilename, file_get_contents($path));
                                $attachments = [NodeFactory::STORE . ':' . $attachment->getHash()];
                            } catch (MapperException $e) {
                                $attachmentMessage = " (failed to attach {$attachmentFilename})";
                                $this->getLogger()->debug($e->getMessage());
                            }
                        }
                    } else {
                        $attachmentMessage = " (unable to attach {$attachmentFilename})";
                        $this->getLogger()->warning("Unable to read {$path}");
                    }
                }
            }

            $this->getLogger()->info("Feedback for {$student->getFullName()} by {$staff->getFullName()}{$attachmentMessage}");
            if ($this->perform()) {
                $dueWorkFeedbackFactory = new Factory(
                    $this->feedbackDataSource,
                    $participant,
                    $staff,
                    '',
                    $comment,
                    '1',
                    $attachments,
                    true,
                    ReturnedWork::REVIEW_ROUTE_INSTRUCTOR,
                    $participant->findMostRecentInstructorGivenFeedback(),
                    null
                );

                try {
                    $feedbackRows = $dueWorkFeedbackFactory->produce();
                    $this->feedbackDataSource->save($feedbackRows);
                } catch (MapperException $e) {
                    $this->getLogger()->warning('Failed to save feedback');
                    continue;
                }
            }
            $this->count++;
        }
    }

    /**
     * Parses options and returns whether all options are valid
     *
     * @return bool
     */
    private function parseAppOptions(): bool
    {
        $valid        = true;
        $this->file   = $this->getOpt(self::OPT_LONG_FILE);
        $this->folder = $this->getOpt(self::OPT_LONG_FOLDER);

        if (null === $this->file) {
            $this->getLogger()->error("Filename is required.");
            $valid = false;
        } elseif (!(is_file($this->file) && is_readable($this->file))) {
            $this->getLogger()->error("File \"$this->file\" does not exist, or is not readable.");
            $valid = false;
        }

        if ($this->folder && !(is_dir($this->folder) && is_readable($this->folder))) {
            $this->getLogger()->error("Folder \"$this->folder\" does not exist, or is not readable.");
            $valid = false;
        }

        return $valid;
    }


    /**
     * Prints a help message, always to STDOUT, regardless of Kernel's output file option
     */
    private function printHelp(): void
    {
        $file = $this->getToolFilename();

        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;

        $help = "$white

Bulk imports feedback for assessments provided by a csv file and attachments.

For each row a published feedback will be created with the comment on the assessment ID.
The feedback will be for the Student ID (external) and from the Staff ID (external).
Any existing draft feedback for that student+assessment will be overwritten, and no longer visible via the UI.
The system will also attempt to attach a file to the feedback by looking in the provided folder for a file named “studentID-assessmentID.pdf”.
If no file is found just the comment will be provided.

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

DESCRIPTION
    $yellow-h, --help$white
        Print this help and exit
        
    $yellow-v[v[v]]$white
        Log verbosity. Max 3 levels, eg -vvv

    $yellow-p, --perform$white
        If set, updates DB - otherwise, performs a dry run.
    
    $yellow--file=FILE$white (required)
        Path to a spreadsheet file which contains the following data:
        Assessment ID, Student ID (external), Staff ID (external), Comment
        
    $yellow--folder=FOLDER$white
        Path of a folder which has files to be attached to feedback.
        Name of the files should be in the format of “studentExternalID-assessmentID.pdf”.
        If not provided or no file is found in this folder, just the comment will be imported.
";
        \fwrite(STDOUT, $help);
    }
}
