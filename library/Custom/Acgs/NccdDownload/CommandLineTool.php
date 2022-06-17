<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Acgs\NccdDownload;

use Alaress\Database\Predicate\Builder;
use DateTimeImmutable;
use DateTimeInterface;
use Laminas\Uri\Uri;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as DueWorkResponseFile;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\File\Model as FileModel;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Url\Factory as UrlFactory;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Scrub;
use TheCodingMachine\Gotenberg\DocumentFactory;
use TheCodingMachine\Gotenberg\GotenbergClientInterface;
use TheCodingMachine\Gotenberg\HTMLRequest;
use ZipArchive;

/**
 * Command-line tool for downloading files from NCCD courses.
 */
class CommandLineTool extends App
{
    /**
     * Outfile option
     */
    private const OPT_LONG_OUTFILE = 'outfile:';

    /**
     * Limit option
     */
    private const OPT_LONG_LIMIT = 'limit:';

    /**
     * Start date option
     */
    private const OPT_LONG_START = 'start:';

    /**
     * End date option
     */
    private const OPT_LONG_END = 'end:';

    /**
     * Help option
     */
    private const OPT_LONG_HELP = 'help';

    /**
     * Help option
     */
    private const OPT_SHORT_HELP = 'h';

    /**
     * The ID(s) of the NCCD information courses.
     */
    private const COURSE_IDS = [468, 509];

    /**
     * Pattern for extracting an external ID out of the folder name.
     *
     * A folder name should look something like: "Student Name {$externalId}".
     */
    private const FOLDER_PATTERN = '/\{(?P<externalId>\\d+)\}/';

    /**
     * Columns via which downloaded files are sorted (ascending order).
     */
    private const SORT_ORDER = ['filemtime', 'id'];

    /**
     * Base URI, for use when generating PDFs: HTML uploads may contain
     * relative URIs, we need to provide a base URI so that they may be made
     * absolute.
     */
    private string $baseUri;

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP];
    }

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_OUTFILE,
            self::OPT_LONG_LIMIT,
            self::OPT_LONG_START,
            self::OPT_LONG_END,
            self::OPT_LONG_HELP,
        ];
    }

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->outputHelp();
        }
        $logger = $this->getLogger();

        // Parse outfile
        $logger->notice(sprintf('Params:'));
        $outfile = $this->getOpt(self::OPT_LONG_OUTFILE);
        if (!$outfile) {
            $logger->error('An outfile (--outfile) must be supplied.');
            exit(1);
        } else {
            $logger->notice(sprintf('    Outfile: %s', $outfile));
        }

        // Parse start time
        /** @var DateTimeImmutable|null $startTime */
        $startTime = null;
        $startOpt  = $this->getOpt(self::OPT_LONG_START);
        if (isset($startOpt)) {
            $startTime = DateTimeImmutable::createFromFormat('Y-m-d', $startOpt);
            if (!$startTime) {
                $logger->error('If provided: start must be in YYYY-MM-DD format.');
                exit(1);
            }
            $startTime = $startTime->modify('00:00:00');
            $logger->notice(sprintf('    Start:   %s', $startTime->format(DateTimeInterface::RFC3339)));
        }

        // Parse end time
        /** @var DateTimeImmutable|null $endTime */
        $endTime = null;
        $endOpt   = $this->getOpt(self::OPT_LONG_END);
        if (isset($endOpt)) {
            $endTime = DateTimeImmutable::createFromFormat('Y-m-d', $endOpt);
            if (!$endTime) {
                $logger->error('If provided: end must be in YYYY-MM-DD format.');
                exit(1);
            }
            $endTime = $endTime->modify('00:00:00 +1 day -1 second');
            $logger->notice(sprintf('    End:     %s', $endTime->format(DateTimeInterface::RFC3339)));
        }

        // Validate start <= end
        if ($startTime && $endTime && $startTime > $endTime) {
            $logger->error('If both start and end are provided: start must be before or equal to end.');
            exit(1);
        }

        // Parse limit
        $limit = $this->getOptAsInt(self::OPT_LONG_LIMIT);
        if ($limit) {
            $logger->notice(sprintf('    Limit:   %d', $limit));
        }


        $this->baseUri = (new UrlFactory($this->getConfig()))->buildFullyQualified()->toString();

        // Find every folder which imports the NCCD Information course
        $params = Builder::getArrayPlaceholders(self::COURSE_IDS);
        $query  = "
            SELECT DISTINCT f.id, f.name
            FROM folder f
            INNER JOIN course_implementation ci ON f.id = ci.folder_id
            INNER JOIN course_specification  cs ON ci.course_specification_id = cs.id
            WHERE f.deleted_at IS NULL
            AND   ci.deleted_at IS NULL
            AND   cs.deleted_at IS NULL
            AND   cs.id IN {$params}
            ORDER BY f.name, f.id
        ";
        if ($limit) {
            // I'd rather use bound params for this,
            // but I can't get them to bind using this API
            $query .= sprintf(' LIMIT %d', $limit);
        }
        $statement = $this->getDatabaseInternal()->executeQuery($query, Builder::getArrayParameters(self::COURSE_IDS));

        $logger->notice(sprintf('Found %d folders which have imported the NCCD courses:', $statement->rowCount()));

        // Collate assessment and folder files
        $assessmentFiles = [];
        $folderFiles     = [];
        $fileMapper      = FileMapper::getInstance();
        foreach ($statement->getIterator() as $row) {
            // Figure out which student is referenced by the folder:
            // If there is none, skip this folder
            $student = $this->extractStudentFromFolderName($row['name']);
            if (!$student) {
                $logger->notice(sprintf('... #%d (%s) does not contain a student ID: skipping', $row['id'], $row['name']));
                continue;
            } else {
                $logger->info(sprintf('... #%d (%s)', $row['id'], $row['name']));
            }

            // Collate folder files
            /** @var FileModel[] $files */
            $files = $fileMapper->findByFolderId($row['id']);
            foreach ($files as $file) {
                $folderFiles[$student->getExternalId()] ??= [
                    'student' => $student,
                    'files'   => [],
                ];
                $folderFiles[$student->getExternalId()]['files'][$file->getId()] = [
                    'storage'   => $file,
                    'filename'  => $file->getTitle(),
                    'filemtime' => $file->getModifiedAt()->getUnixTimeStamp(),
                ];
            }

            // Collate assessment response files, but only for assessments which
            // are within one of the NCCD information courses.
            /** @var MapperProxy $assessmentDataSource */
            $assessmentDataSource = $this->getDi()->get('assessmentDataSource');
            $assessments          = $assessmentDataSource->findAllByFolderIdForReview($row['id']);
            foreach ($assessments as $assessment) {
                $context = $assessment->getContext();
                if (
                    $context instanceof UnitImplementation
                    && in_array($context->getCourse()->getSpecification()->getId(), self::COURSE_IDS)
                ) {
                    $assessmentFiles[$student->getExternalId()] ??= [
                        'student' => $student,
                        'files'   => [],
                    ];
                    $assessmentFiles[$student->getExternalId()]['files'] = array_merge(
                        $assessmentFiles[$student->getExternalId()]['files'],
                        $this->collectFilesFromAssessment($assessment),
                    );
                }
            }
        }

        // Collate the assessment files into filenames
        /**
         * @var array<int, array{
         *     file:IStorage|null,
         *     fileContent:string|null,
         *     mimetype:string,
         *     filename:string,
         *     path:string,
         *     filemtime:int
         * }> $files
         */
        $files = [];
        foreach ($folderFiles as $folderFilesTuple) {
            /** @var User $student */
            $student = $folderFilesTuple['student'];
            usort($folderFilesTuple['files'], [$this, 'fileSort']);
            foreach (array_values($folderFilesTuple['files']) as $i => $file) {
                /** @var IStorage $storage */
                $storage    = $file['storage'];
                $yearLevel  = $student->getYear()->getName();
                $externalId = $student->getExternalId();
                $filename   = $file['filename'] . '.' . $storage->getExtension();
                $files[]    = [
                    'file'        => $storage,
                    'fileContent' => null,
                    'mimetype'    => $storage->getMime(),
                    'filename'    => $filename,
                    'filemtime'   => $file['filemtime'],
                    'path'        => sprintf('%s/%s_%d_%s', $yearLevel, $externalId, $i + 1, $filename),
                ];
            }
        }
        foreach ($assessmentFiles as $assessmentFilesTuple) {
            /** @var User $student */
            $student = $assessmentFilesTuple['student'];
            usort($assessmentFilesTuple['files'], [$this, 'fileSort']);
            foreach (array_values($assessmentFilesTuple['files']) as $j => $file) {
                /** @var IStorage $storage */
                $storage    = $file['storage'];
                $yearLevel  = $student->getYear()->getName();
                $externalId = $student->getExternalId();
                $filename   = $file['filename'] . '.' . $storage->getExtension();
                $files[]    = [
                    'file'        => $storage,
                    'fileContent' => null,
                    'mimetype'    => $storage->getMime(),
                    'filename'    => $filename,
                    'filemtime'   => $file['filemtime'],
                    'path'        => sprintf('%s/%s_Evidence_%d_%s', $yearLevel, $externalId, $j + 1, $filename),
                ];
                $comment = $file['comment'] ?? '';
                if ($comment !== '') {
                    $filename = $file['filename'] . '_comment.html';
                    $files[]  = [
                        'file'        => null,
                        'fileContent' => nl2br($comment),
                        'mimetype'    => 'text/html',
                        'filename'    => $filename,
                        'filemtime'   => $file['filemtime'],
                        'path'        => sprintf('%s/%s_Evidence_%d_%s', $yearLevel, $externalId, $j + 1, $filename),
                    ];
                }
            }
        }

        // Put the files into the zip, skipping files outside of the start/end
        // time (if one provided)
        $zip       = new ZipArchive();
        $zipStatus = $zip->open($outfile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($zipStatus !== true) {
            $logger->error(sprintf('Could not create ZIP file (%s)', $zip->getStatusString()));
            exit(1);
        }
        /** @var FilesystemProxy $filesystemProxy */
        $filesystemProxy = $this->getDi()->get('filesystem.storageProxy');
        foreach ($files as $fileTuple) {
            // Start is after the file's modified time: skip
            $fileTime = (new DateTimeImmutable())->setTimestamp($fileTuple['filemtime']);
            if ($startTime && $startTime > $fileTime) {
                $logger->debug(
                    sprintf(
                        'Skipping file "%s": mtime %s is before start time %s',
                        $fileTuple['filename'],
                        $fileTime->format(DateTimeInterface::RFC3339),
                        $startTime->format(DateTimeInterface::RFC3339),
                    )
                );
                continue;
            }
            // End is before the file's modified time: skip
            if ($endTime && $endTime < $fileTime) {
                $logger->debug(
                    sprintf(
                        'Skipping file "%s": mtime %s is after start time %s',
                        $fileTuple['filename'],
                        $fileTime->format(DateTimeInterface::RFC3339),
                        $startTime->format(DateTimeInterface::RFC3339),
                    )
                );
                continue;
            }
            $path = $fileTuple['path'];
            $logger->info(sprintf('Adding file "%s" to "%s"', $fileTuple['filename'], $path));

            // Read file content: raise a warning if it couldn't be added.
            // (If debug is on: give a hint where it might be found on prod)
            /** @var IStorage|null $file */
            $file        = $fileTuple['file'];
            $fileContent = $file
                ? $filesystemProxy->read($file)
                : ($fileTuple['fileContent'] ?? false);
            if ($fileContent === false) {
                $logger->warning(sprintf('Could not add file "%s" to "%s": skipping', $fileTuple['filename'], $path));
                if ($file) {
                    $logger->debug(
                        sprintf(
                            '(Try downloading from %s, and uploading to staging)',
                            (new Uri('https://schoolbox.churchie.com.au/'))
                                ->setPath('/storage/fetch.php')
                                ->setQuery(['hash' => $file->getHash()])
                        )
                    );
                }
                continue;
            }

            // Convert this to PDF if it's HTML
            if ($fileTuple['mimetype'] === 'text/html') {
                $logger->info(sprintf('Converting file "%s" to PDF', $path));
                // Convert extension
                $path        = sprintf(
                    '%s/%s.pdf',
                    pathinfo($path, PATHINFO_DIRNAME),
                    pathinfo($path, PATHINFO_FILENAME),
                );
                $fileContent = $this->convertToPdf($path, $fileContent);
            }

            // Add file: exit if it couldn't be added
            if (!$zip->addFromString($path, $fileContent)) {
                $logger->error(
                    sprintf('Could not add file "%s" to ZIP (%s)', $fileTuple['filename'], $zip->getStatusString()),
                );
                $zip->close();
                exit(1);
            }
            $zip->setMtimeName($path, $fileTuple['filemtime']);
        }

        if ($zip->numFiles === 0) {
            $logger->warning('No files were added to the ZIP');
        } else {
            $logger->notice(sprintf('Added %d files to ZIP', $zip->numFiles));
        }
        $zip->close();
    }

    /**
     * Collect the files which have been uploaded to this assessment as part of
     * responses by teachers.
     *
     * This fetches the files from all responses: if a file with the same name
     * has been submitted twice, the later submitted version is used.
     *
     * @param IAssessable $assessment
     * @return array<int, array{storage: IStorage, filename: string, filemtime: int, comment:string|null}>
     */
    private function collectFilesFromAssessment(IAssessable $assessment): array
    {
        $files = [];
        foreach ($assessment->getAllParticipants() as $participant) {
            $participantFiles = [];
            foreach ($participant->getSubmittedResponses() as $response) {
                /** @var array<string, array{storage: DueWorkResponseFile, filename: string, filemtime: int}> $participantFiles */
                if ($response instanceof DueWorkResponse) {
                    if ($response->getAllFiles()->count()) {
                        $feedback = $participant->findMostRecentInstructorGivenPublishedFeedback();
                        foreach ($response->getAllFiles() as $file) {
                            $filename  = $file->getBasename();
                            $filemtime = $response->getSubmittedAt()->getUnixTimeStamp();
                            // Override a previous file with the same name for
                            // this participant, if it was submitted later.
                            if (
                                !isset($participantFiles[$filename])
                                || $participantFiles[$filename]['filemtime'] < $filemtime
                            ) {
                                $participantFiles[$filename] = [
                                    'storage'   => $file,
                                    'filename'  => $file->getFilename(),
                                    'filemtime' => $filemtime,
                                    'comment'   => $feedback ? $feedback->getComment() : null,
                                ];
                                // Only attach comment to the first file in the response
                                $feedback = null;
                            }
                        }
                    }
                }
            }
            foreach ($participantFiles as $participantFile) {
                $files[$participantFile['storage']->getId()] = $participantFile;
            }
        }
        return $files;
    }

    /**
     * Extracts a student from the folder name.
     *
     * See `self::FOLDER_PATTERN` for the pattern which identifies a student
     * given a folder name: if the folder name doesn't match the pattern, or
     * the identifier in the pattern doesn't then resolve to a user, this
     * returns null.
     *
     * @param string $folderName
     * @return User|null
     */
    private function extractStudentFromFolderName(string $folderName): ?User
    {
        if (preg_match(self::FOLDER_PATTERN, $folderName, $matches)) {
            try {
                return UserMapper::getInstance()->findByExternalId($matches['externalId']);
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Sort function for a set of files.
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    private function fileSort(array $a, array $b): int
    {
        foreach (self::SORT_ORDER as $column) {
            if (isset($a[$column], $b[$column])) {
                $cmp = $a[$column] <=> $b[$column];
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
        }
        return 0;
    }

    /**
     * Converts HTML or text input to PDF.
     *
     * @param string $filename
     * @param string $input
     * @return string
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TheCodingMachine\Gotenberg\FilesystemException
     */
    private function convertToPdf(string $filename, string $input): string
    {
        $document = DocumentFactory::makeFromString($filename, Scrub::htmlAbsoluteUri($input, $this->baseUri));
        $request  = new HTMLRequest($document);
        $tmpfile  = tempnam(sys_get_temp_dir(), 'pdf');
        $request->addWebhookURLHTTPHeader('SBAuth', $this->getConfig()->get(ConfigMapper::NAME_GOTENBERG_API_KEY));

        /** @var GotenbergClientInterface $gotenberg */
        $gotenberg = $this->getDi()->get('pdfGenerator');
        $gotenberg->store($request, $tmpfile);
        $result = file_get_contents($tmpfile);
        unlink($tmpfile);
        return $result;
    }

    /**
     * Output help text and exit.
     *
     * @return never-return
     */
    private function outputHelp(): void
    {
        echo <<<HELP
NAME
    {$this->getToolFilename()} - export NCCD pages content

SYNOPSIS
    {$this->getToolFilename()} --outfile OUTFILE [--limit LIMIT] [--start START] [--end END] [-v]

DESCRIPTION
    Outputs NCCD page content to OUTFILE in ZIP format.

    NCCD pages are distinguishable by:
    * their importation of one of the NCCD courses
    * the page's name contains a student's external ID in curly brackets, e.g. "Student Name {12345}"

    NCCD page content consists of:
    * files uploaded directly to the page
    * files submitted by a teacher to one of the due works within an NCCD course, as well as any comments which have been given in feedback
    * files from due work submissions which are not published will be excluded

    Comments and HTML content (including the content of the Online Submission box) will be converted to PDF before outputting to the ZIP.

OPTIONS
    -h, --help
        Output this help text and exit.

    --outfile OUTFILE
        The ZIP file to output NCCD page content to.

    --limit LIMIT
        If provided: only output NCCD information for the first LIMIT students found.

    --start START
        If provided: only include files modified or submitted on or after this date.  If supplied, this date must be supplied in "YYYY-MM-DD" format.

        The following dates are used for comparisons:
        * for files uploaded directly to a page, the file's last modified time is used
        * for files submitted by a teacher to a due work, the file's submission time is used

    --end END
        If provided: only include files uploaded or submitted on or before this date.

    -v
        Increase verbosity of output (repeat up to 3 times for increased verbosity)


HELP;
        exit;
    }
}
