<?php
/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Acgs\NccdDownload;

use Alaress\Database\Predicate\Builder;
use Laminas\Uri\Uri;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\File\Model as FileModel;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\User;
use Schoolbox\UserMapper;
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
     * Outfile option
     */
    private const OPT_LONG_LIMIT = 'limit:';

    /**
     * The ID(s) of the NCCD information courses.
     */
    private const COURSE_IDS = [468];

    /**
     * Pattern for extracting an external ID out of the folder name.
     *
     * A folder name should look something like: "Student Name {$externalId}".
     */
    private const FOLDER_PATTERN = '/\{(?P<externalId>\\d+)\}/';

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_OUTFILE, self::OPT_LONG_LIMIT];
    }

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        $logger  = $this->getLogger();
        $outfile = $this->getOpt(self::OPT_LONG_OUTFILE);
        if (!$outfile) {
            $logger->error('An outfile (--outfile) must be supplied.');
            exit(1);
        }

        /** @var MapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');

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
        $limit = $this->getOptAsInt(self::OPT_LONG_LIMIT);
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
                    'file'     => $file,
                    'filename' => $file->getTitle(),
                ];
            }

            // Collate assessment feedback files, but only for assessments which
            // are within one of the NCCD information courses.
            $assessments = $assessmentDataSource->findAllByFolderIdForReview($row['id']);
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
        /** @var array<int, array{file:IStorage, filename:string, path:string}> $files */
        $files = [];
        foreach ($folderFiles as $folderFilesTuple) {
            /** @var User $student */
            $student = $folderFilesTuple['student'];
            ksort($folderFilesTuple['files']);
            foreach (array_values($folderFilesTuple['files']) as $i => $file) {
                $yearLevel  = $student->getYear()->getName();
                $externalId = $student->getExternalId();
                $files[]    = [
                    'file'     => $file['file'],
                    'filename' => $file['filename'] . '.' . $file['file']->getExtension(),
                    'path'     => sprintf(
                        '%s/%s_%s_%d.%s',
                        $yearLevel,
                        $externalId,
                        $file['filename'],
                        $i + 1,
                        $file['file']->getExtension(),
                    ),
                ];
            }
        }
        foreach ($assessmentFiles as $assessmentFilesTuple) {
            /** @var User $student */
            $student = $assessmentFilesTuple['student'];
            ksort($assessmentFilesTuple['files']);
            foreach (array_values($assessmentFilesTuple['files']) as $j => $file) {
                /** @var IStorage $file */
                $yearLevel  = $student->getYear()->getName();
                $externalId = $student->getExternalId();
                $files[]    = [
                    'file'     => $file['file'],
                    'filename' => $file['filename'] . '.' . $file['file']->getExtension(),
                    'path'     => sprintf(
                        '%s/%s_Evidence_%s_%d.%s',
                        $yearLevel,
                        $externalId,
                        $file['filename'],
                        $j + 1,
                        $file['file']->getExtension(),
                    ),
                ];
            }
        }

        $zip       = new ZipArchive();
        $zipStatus = $zip->open($outfile, ZipArchive::CREATE);
        if ($zipStatus !== true) {
            $logger->error(sprintf('Could not create ZIP file (%s)', $zip->getStatusString()));
            exit(1);
        }
        foreach ($files as $fileTuple) {
            /** @var IStorage $file */
            $file = $fileTuple['file'];
            $logger->info(sprintf('Adding file "%s" to "%s"', $fileTuple['filename'], $fileTuple['path']));

            // Read file content: raise a warning if it couldn't be added.
            // (If debug is on: give a hint where it might be found on prod)
            $fileContent = $file->read();
            /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */
            /** @phpstan-ignore-next-line: read() may return false */
            if ($fileContent === false) {
                $logger->warning(sprintf('Could not read file "%s": skipping', $file->getBasename()));
                $logger->debug(
                    sprintf(
                        '(Try downloading from %s, and uploading to staging)',
                        (new Uri('https://schoolbox.churchie.com.au/'))
                            ->setPath('/storage/fetch.php')
                            ->setQuery(['hash' => $file->getHash()])
                    )
                );
                continue;
            }

            // Add file: exit if it couldn't be added
            if (!$zip->addFromString($fileTuple['path'], $fileContent)) {
                $logger->error(
                    sprintf('Could not add file "%s" to ZIP (%s)', $file->getBasename(), $zip->getStatusString()),
                );
                $zip->close();
                exit(1);
            }
        }
        $zip->close();
    }

    /**
     * Collect the files which have been uploaded to this assessment as part of
     * responses by teachers.
     *
     * This only fetches the files from the most recent response.
     *
     * @param IAssessable $assessment
     * @return array<int, array{file: IStorage, filename: string}>
     */
    private function collectFilesFromAssessment(IAssessable $assessment): array
    {
        // Get each participant who's uploaded something, and return the files
        // from their most recent response.
        $files = [];
        foreach ($assessment->getAllParticipants() as $participant) {
            $response = $participant->findMostRecentResponse();
            if ($response instanceof DueWorkResponse) {
                // Is there a file here?
                if ($response->getAllFiles()->count()) {
                    foreach ($response->getAllFiles() as $file) {
                        $files[$file->getId()] = [
                            'file'     => $file,
                            'filename' => $file->getFilename(),
                        ];
                    }
                }
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
}
