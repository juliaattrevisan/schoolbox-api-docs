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
     * The ID(s) of the NCCD information courses.
     */
    private const COURSE_IDS = [468];

    /**
     * Pattern for extracting an external ID out of the folder.
     */
    private const FOLDER_PATTERN = '/\{(?P<externalId>\\d+)\}/';

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_OUTFILE];
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
            LIMIT 10
        ";
        $statement = $this->getDatabaseInternal()->executeQuery($query, Builder::getArrayParameters(self::COURSE_IDS));

        $logger->info(sprintf('Found %d folders which have imported the NCCD courses:', $statement->rowCount()));

        // Collate assessment and folder files
        $assessmentFiles = [];
        $folderFiles     = [];
        foreach ($statement->getIterator() as $row) {
            $logger->info(sprintf('... folder #%d (%s)', $row['id'], $row['name']));


            // Get all assessments:
            // filter out anything not in one of the courses
            $student     = $this->extractStudentFromFolderName($row['name']);
            if (!$student) {
                $logger->info('    folder does not contain a student ID: skipping');
                continue;
            }

            // Collate folder files
            $files = FileMapper::getInstance()->findByFolderId($row['id']);
            foreach ($files as $file) {
                $folderFiles[$student->getExternalId()] ??= [
                    'student' => $student,
                    'files'   => [],
                ];
                $folderFiles[$student->getExternalId()]['files'][$file->getId()] = $file;
            }

            $assessments = $assessmentDataSource->findAllByFolderIdForReview($row['id']);
            foreach ($assessments as $assessment) {
                $assessmentFiles[$student->getExternalId()] ??= [
                    'student' => $student,
                    'files'   => [],
                ];
                $assessmentFiles[$student->getExternalId()]['files'] += $this->collectFilesFromAssessment($assessment);
            }
        }

        // Collate the assessment files into filenames
        /** @var array<int, array{file:IStorage, path:string}> $files */
        $files = [];
        foreach ($folderFiles as $folderFilesTuple) {
            /** @var User $student */
            $student = $folderFilesTuple['student'];
            ksort($folderFilesTuple['files']);
            foreach (array_values($folderFilesTuple['files']) as $i => $file) {
                $yearLevel  = $student->getYear()->getName();
                $externalId = $student->getExternalId();
                $files[]    = [
                    'file' => $file,
                    'path' => sprintf(
                        '%s/%s_%s_%d.%s',
                        $yearLevel,
                        $externalId,
                        $file->getFilename(),
                        $i + 1,
                        $file->getExtension(),
                    ),
                ];
            }
        }
        foreach ($assessmentFiles as $assessmentFilesTuple) {
            /** @var User $student */
            $student = $assessmentFilesTuple['student'];
            ksort($assessmentFilesTuple['files']);
            foreach (array_values($assessmentFilesTuple['files']) as $i => $file) {
                /** @var IStorage $file */
                $yearLevel  = $student->getYear()->getName();
                $externalId = $student->getExternalId();
                $files[]    = [
                    'file' => $file,
                    'path' => sprintf(
                        '%s/%s_Evidence_%s_%d.%s',
                        $yearLevel,
                        $externalId,
                        $file->getFilename(),
                        $i + 1,
                        $file->getExtension(),
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
            $logger->notice($fileTuple['path']);

            /** @var IStorage $file */
            $file        = $fileTuple['file'];
            $fileContent = $file->read();
            /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection */
            /** @phpstan-ignore-next-line: read() may return false */
            if ($fileContent === false) {
                $logger->warning(sprintf('Could not read file "%s": skipping', $file->getFilename()));
                // Give a hint where it might be found on production?
                $logger->debug(
                    sprintf(
                        'Try downloading from: %s',
                        (new Uri('https://schoolbox.churchie.com.au/'))
                            ->setPath('/storage/fetch.php')
                            ->setQuery(['hash' => $file->getHash()])
                    )
                );
                continue;
            }
            $zip->addFromString($fileTuple['path'], $fileContent);
        }
        $zip->close();
    }

    /**
     * @param IAssessable $assessment
     * @return array<int, IStorage>
     */
    private function collectFilesFromAssessment(IAssessable $assessment): array
    {
        $logger  = $this->getLogger();
        $context = $assessment->getContext();
        if (
            $context instanceof UnitImplementation
            && in_array($context->getCourse()->getSpecification()->getId(), self::COURSE_IDS)
        ) {
            $logger->info(sprintf('  ... assessment #%d (%s)', $assessment->getId(), $assessment->getTitle()));
        } else {
            // This assessment isn't part of NCCD: return
            return [];
        }

        // Now: get each participant who's uploaded something
        $files = [];
        foreach ($assessment->getAllParticipants() as $participant) {
            $response = $participant->findMostRecentResponse();
            if ($response instanceof DueWorkResponse) {
                // Say something about the participant
                $teacher = $response->getSubmittedBy();
                $logger->info(sprintf('    ... teacher #%d (%s)', $teacher->getId(), $teacher->getFullName()));

                // Is there a file here?
                if ($response->getAllFiles()->count()) {
                    foreach ($response->getAllFiles() as $file) {
                        $logger->info(sprintf('      ... file %s', $file->getBasename()));
                        $files[$file->getId()] = $file;
                    }
                } else {
                    $logger->info('      ... no files!');
                }
            }
        }
        return $files;
    }

    /**
     * Extracts a student record from the folder name.
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
