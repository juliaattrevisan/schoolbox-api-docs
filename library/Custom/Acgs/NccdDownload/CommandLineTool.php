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
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;

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
        foreach ($statement->getIterator() as $row) {
            $logger->info(sprintf('... folder #%d (%s)', $row['id'], $row['name']));

            // Get all assessments:
            // filter out anything not in one of the courses
            $assessments = $assessmentDataSource->findAllByFolderIdForReview($row['id']);
            foreach ($assessments as $assessment) {
                $context = $assessment->getContext();
                if (
                    $context instanceof UnitImplementation
                    && in_array($context->getCourse()->getSpecification()->getId(), self::COURSE_IDS)
                ) {
                    $logger->info(sprintf('  ... assessment #%d (%s)', $assessment->getId(), $assessment->getTitle()));

                    // Now: get each participant who's uploaded something
                    foreach ($assessment->getAllParticipants() as $participant) {
                        $response = $participant->findMostRecentResponse();
                        if ($response instanceof DueWorkResponse) {
                            // Say something about the participant
                            $logger->info(
                                sprintf(
                                    '    ... teacher #%d (%s)',
                                    $response->getSubmittedBy()->getId(),
                                    $response->getSubmittedBy()->getFullName(),
                                )
                            );

                            // Is there a file here?
                            if ($response->getAllFiles()->count()) {
                                foreach ($response->getAllFiles() as $file) {
                                    $logger->info(
                                        sprintf(
                                            '      ... file %s',
                                            $file->getBasename(),
                                        )
                                    );
                                }
                            } else {
                                $logger->info('      ... no files!');
                            }
                        }
                    }
                }
            }
        }
    }
}
