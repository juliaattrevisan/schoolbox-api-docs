<?php
/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Acgs\NccdDownload;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DueWorkResponse;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\User;
use Schoolbox\YearLevel\Model;

/**
 * Command-line tool for downloading files from NCCD courses.
 */
class CommandLineTool extends App
{
    /**
     * The ID(s) of the NCCD information courses.
     */
    private const COURSE_IDS = [468];

    /**
     * @inheritDoc
     */
    public function run(): void
    {
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

        $logger = $this->getLogger();
        $logger->info(sprintf('Found %d folders which have imported the NCCD courses:', $statement->rowCount()));

        // Collate assessment files
        $assessmentFiles = [];
        foreach ($statement->getIterator() as $row) {
            $logger->info(sprintf('... folder #%d (%s)', $row['id'], $row['name']));

            // Get all assessments:
            // filter out anything not in one of the courses
            $assessments = $assessmentDataSource->findAllByFolderIdForReview($row['id']);
            $student     = $this->extractStudentFromFolderName($row['name']);
            if (!$student) {
                $logger->info('    folder does not contain a student ID: skipping');
                continue;
            }
            foreach ($assessments as $assessment) {
                $assessmentFiles[$student->getExternalId()] ??= [
                    'student' => $student,
                    'files'   => [],
                ];
                $assessmentFiles[$student->getExternalId()]['files'] += $this->collectFilesFromAssessment($assessment);
            }
        }

        // Collate folder files

        // Collate the assessment files into filenames
        $filenames = [];
        foreach ($assessmentFiles as $assessmentFilesTuple) {
            /** @var User $student */
            $student = $assessmentFilesTuple['student'];
            ksort($assessmentFilesTuple['files']);
            foreach (array_values($assessmentFilesTuple['files']) as $i => $file) {
                $yearLevel   = $student->getYear()->getName();
                $externalId  = $student->getExternalId();
                $filenames[] = sprintf(
                    '%s/%s_Evidence_%s_%d.%s',
                    $yearLevel,
                    $externalId,
                    $file->getFilename(),
                    $i + 1,
                    $file->getExtension(),
                );
            }
        }

        foreach ($filenames as $filename) {
            $logger->notice($filename);
        }
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
        // TODO extract student external ID from folder name
        return (new User())
            ->setYear((new Model())->setName('Year 9'))
            ->setFirstname('Test')
            ->setLastname('User')
            ->setExternalId('12345');
    }
}
