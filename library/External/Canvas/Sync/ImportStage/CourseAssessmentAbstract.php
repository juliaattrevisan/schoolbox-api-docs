<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\ImportStage;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress_Date_Format;
use External\Canvas\Importer\CanvasDb;
use External\Canvas\Sync\Bridge;
use External\Canvas\Sync\Bridge\Course;
use External\Canvas\Sync\ImportStage\Traits\ModelSaveTrait;
use HTMLPurifier;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\DataModel\Mapper;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use PDO;

/**
 * Common implementation to import Canvas Course Assignments+Quizzes.
 *
 * The is a large degree of similarity between these entities, particularly around the overrides,
 * which map logically to Schoolbox plans + participation.
 */
abstract class CourseAssessmentAbstract
{
    use ModelSaveTrait;

    /** @var HTMLPurifier */
    protected HTMLPurifier $htmlPurifier;

    /**
     * @param CanvasDb        $canvasDb
     * @param IConnector      $schoolboxDb
     * @param Mapper          $assessmentMapper
     * @param LoggerInterface $log
     * @param IConfigLoader   $config
     * @param Course          $courseBridge
     * @param string          $schoolboxTimezone
     */
    public function __construct(
        protected CanvasDb $canvasDb,
        protected IConnector $schoolboxDb,
        protected Assessment\Mapper $assessmentMapper,
        protected LoggerInterface $log,
        protected IConfigLoader $config,
        protected Bridge\Course $courseBridge,
        protected string $schoolboxTimezone
    ) {
        $htmlPurifierConfig = \HTMLPurifier_Config::createDefault();
        // See Scrub.php
        $htmlPurifierConfig->set('Cache.SerializerPath', $config->get('purifier_cache_dir'));
        $htmlPurifierConfig->set('URI.Base', $config->get(ConfigMapper::_CANVAS_URL));
        $htmlPurifierConfig->set('URI.MakeAbsolute', true);
        $this->htmlPurifier = new HTMLPurifier($htmlPurifierConfig);
    }

    /**
     * Create an assessment model, linked to the specified UnitSpec
     *
     * @param UnitSpec\Model      $unitSpec
     * @param string              $title
     * @param string|null         $description
     * @param WorkType\Model|null $workType
     * @param float               $weight
     * @param string              $createdAt
     * @param string              $updatedAt
     * @param int                 $position
     * @return Assessment\Model
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function createAssessment(
        UnitSpec\Model $unitSpec,
        string $title,
        ?string $description,
        ?WorkType\Model $workType,
        float $weight,
        string $createdAt,
        string $updatedAt,
        int $position
    ): Assessment\Model {
        $assessment = (new Assessment\Model())
            ->setContextUnitSpecification($unitSpec)
            ->setTitle($title)
            ->setDescription($description)
            ->setWorkType($workType)
            ->setWeight($weight)
            ->setCreatedAt(Alaress_Date_Format::newDatabaseDate($createdAt))
            ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate($updatedAt))
            ->setSequence($position);

        /* Cannot use mappers here to save because:
         * 1. We want to preserve Canvas create/update timestamps (alt. method would be to save via mapper, then raw query to override)
         * 2. Too many inappropriate pre/post save callbacks on mappers which cannot be altered in a master feature.
         */
        $this->saveModel($this->assessmentMapper, $assessment, false, false);

        $sql = "INSERT INTO assessment_course_unit_specification (assessment_id, course_unit_specification_id) VALUES (:assessmentId, :unitSpecId)";
        $this->schoolboxDb->executeQuery($sql, [
            'assessmentId' => $assessment->getId(),
            'unitSpecId'   => $unitSpec->getId(),
        ]);

        return $assessment;
    }

    /**
     * Check if an assessment has changed, and update it in the DB if it has.
     *
     * Returns whether any changes were made.
     *
     * @param Assessment\Model    $assessment
     * @param string              $title
     * @param string|null         $description
     * @param int                 $position
     * @param WorkType\Model|null $workType
     * @param float               $weight
     * @param string              $createdAt
     * @param string              $updatedAt
     * @return bool
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function checkAssessmentForChanges(
        Assessment\Model $assessment,
        string $title,
        ?string $description,
        int $position,
        ?WorkType\Model $workType,
        float $weight,
        string $createdAt,
        string $updatedAt
    ): bool {
        // Do all attributes align?
        if (
            $assessment->getTitle() === $title &&
            $assessment->getDescription() === $description &&
            $assessment->getSequence() === $position &&
            $assessment->getWorkType()?->getId() === $workType?->getId() &&
            (float)$assessment->getWeight() === $weight &&
            $assessment->getCreatedAt()->getDatabase() === $createdAt &&
            $assessment->getUpdatedAt()->getDatabase() === $updatedAt
        ) {
            return false;
        }
        $assessment->setTitle($title)
                   ->setDescription($description)
                   ->setSequence($position)
                   ->setWorkType($workType)
                   ->setWeight($weight)
                   ->setCreatedAt(Alaress_Date_Format::newDatabaseDate($createdAt))
                   ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate($updatedAt));

        $this->saveModel($this->assessmentMapper, $assessment, false, false);

        return true;
    }

    /**
     * Returns the canvas ids of the Canvas courses to import
     *
     * Course must:
     *   * Have workflow_state 'available' or 'completed'
     *   * Have 1+ CourseSections where the Sections:
     *    * Has a SisSourceId LIKE 'Y2019C%NOT', ... where year is from 2019 to current calendar year + 1
     *
     * @return int[]
     * @throws Database\Exception
     * @throws InvalidColumnException
     */
    protected function getCanvasCourseIdsToImport(): array
    {
        $pb          = $this->courseBridge->getCanvasCoursesToImport();
        $whereClause = $pb->getWhere()[0];
        $sql         = "
            SELECT DISTINCT c.canvas_id
            FROM course_dim c
            JOIN course_section_dim cs ON cs.course_id = c.id
            JOIN enrollment_term_dim term ON term.id = c.enrollment_term_id
             WHERE {$whereClause}
        ";
        $canvasIds   = $this->canvasDb->getConnector()->executeQuery($sql, $pb->getParams())->fetchAll(PDO::FETCH_COLUMN);
        return array_map(static function (string $canvasId) {
            return (int)$canvasId;
        }, $canvasIds);
    }
}
