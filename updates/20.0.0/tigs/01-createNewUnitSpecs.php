#!/usr/bin/php
<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Statement\Query\IQuery;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {

    private const UNIT_NAME = 'Report Grades';

    private const WORK_TYPE_IDS = [5, 4, 7, 1];


    /**
     * Query for finding course specs which need a "Report Grades" unit spec.
     *
     * @var IQuery
     */
    private $findSpecAssessmentsQuery;


    /**
     * Query for finding the default permissions for course builder units.
     *
     * @var IQuery
     */
    private $findCourseBuilderConfigQuery;


    /**
     * Query for finding out if a course spec has a "Report Grades" unit spec.
     *
     * @var IQuery
     */
    private $findUnitSpecQuery;


    /**
     * Query for creating the folder for the new unit spec.
     *
     * @var IQuery
     */
    private $createFolderQuery;


    /**
     * Query for creating the homepage for the new unit spec's folder.
     *
     * @var IQuery
     */
    private $createHomepageQuery;


    /**
     * Query for creating the permissions for the new unit spec's folder.
     *
     * @var IQuery
     */
    private $createFolderPermissionsQuery;


    /**
     * Query for creating the new unit spec.
     *
     * @var IQuery
     */
    private $createUnitSpecQuery;


    /**
     * Query for moving a spec assessment.
     *
     * @var IQuery
     */
    private $moveSpecAssessmentQuery;


    /**
     * Query for finding implementation assessments.
     *
     * @var IQuery
     */
    private $findImplAssessmentsQuery;


    /**
     * Query for finding unit implementations for a given spec assessment.
     *
     * @var IQuery
     */
    private $findUnitImplQuery;


    /**
     * Query for finding the earliest start and latest end date for all unit
     * implementations within a course implementation.
     *
     * These dates are used as the start/end for the new unit implementation.
     *
     * @var IQuery
     */
    private $findUnitImplDatesQuery;


    /**
     * Query for creating a unit implementation for the new course
     * implementation.
     *
     * @var IQuery
     */
    private $createUnitImplQuery;


    /**
     * Query for moving assessments from their existing unit implementation into
     * the new one.
     *
     * @var IQuery
     */
    private $moveImplAssessmentQuery;


    /**
     * @inheritDoc
     */
    public function run(): void
    {
        $this->prepareQueries();


        $this->findCourseBuilderConfigQuery->execute();
        $courseBuilderConfigs = $this->findCourseBuilderConfigQuery->fetchAll();


        $this->findSpecAssessmentsQuery->execute(
            Builder::getArrayParameters(self::WORK_TYPE_IDS, 'workTypeId')
        );


        $db = $this->getDatabaseInternal();
        $db->beginTransaction();

        $unitSpecIds = [];
        $unitImplIds = [];
        foreach ($this->findSpecAssessmentsQuery->fetchAll() as $specRow) {
            $courseSpecId     = (int)$specRow['course_specification_id'];
            $courseFolderId   = (int)$specRow['folder_id'];
            $specAssessmentId = (int)$specRow['assessment_id'];

            if (!isset($unitSpecIds[$courseSpecId])) {
                $unitSpecIds[$courseSpecId] = $this->createUnitSpecification(
                    $courseSpecId,
                    $courseFolderId,
                    $courseBuilderConfigs
                );
            }

            // Move spec assessments from one place to the other
            $this->getLogger()->info(sprintf('Moving spec assessment #%d to new unit spec', $specAssessmentId));
            $this->moveSpecAssessmentQuery->execute([
                'newUnitSpecId' => $unitSpecIds[$courseSpecId],
                'assessmentId'  => $specAssessmentId,
            ]);


            // Create unit implementations
            // Move impl assessments from one place to the other
            $this->findImplAssessmentsQuery->execute([
                'specAssessmentId' => $specAssessmentId,
            ]);
            foreach ($this->findImplAssessmentsQuery->fetchAll() as $implRow) {
                $courseImplId     = (int)$implRow['course_implementation_id'];
                $implAssessmentId = (int)$implRow['assessment_id'];

                if (!isset($unitImplIds[$courseImplId])) {
                    $unitImplIds[$courseImplId] = $this->createUnitImplementation(
                        $courseImplId,
                        $unitSpecIds[$courseSpecId]
                    );
                }

                // And move the assessment
                $this->getLogger()->info(sprintf('Moving impl assessment #%d to new unit impl', $implAssessmentId));
                $this->moveImplAssessmentQuery->execute([
                    'newUnitImplId' => $unitImplIds[$courseImplId],
                    'assessmentId'  => $implAssessmentId,
                ]);
            }
        }




        $db->commit();
    }


    /**
     * Prepare queries that this script requires.
     */
    private function prepareQueries(): void
    {
        $db = $this->getDatabaseInternal();


        // Queries for creating a new unit spec.
        $placeholders = Builder::getArrayPlaceholders(self::WORK_TYPE_IDS, 'workTypeId');
        $this->findSpecAssessmentsQuery = $db->prepareQuery("
            SELECT DISTINCT
                var.assessment_id,
                var.course_specification_id,
                cs.folder_id
            FROM v_assessment_relation var
            INNER JOIN course_specification cs  ON var.course_specification_id = cs.id
            INNER JOIN assessment           a   ON var.assessment_id           = a.id
            INNER JOIN lu_work_type         lwt ON a.work_type_id              = lwt.id
            WHERE lwt.id IN {$placeholders}
            AND   var.course_specification_id  IS NOT NULL
            AND   var.course_implementation_id IS NULL
            AND   var.project_assessment_id    IS NULL
        ");

        $this->findCourseBuilderConfigQuery = $db->prepareQuery("
            SELECT role_id, permission
            FROM course_builder_config
            WHERE page = 'unit'
        ");

        $this->findUnitSpecQuery = $db->prepareQuery("
            SELECT cus.id
            FROM       course_specification      cs
            INNER JOIN course_unit_specification cus ON cs.id         = cus.course_specification_id
            INNER JOIN folder                    f   ON cus.folder_id = f.id
            WHERE cs.id  = :courseSpecId
            AND   f.name = :folderName
        ");

        $this->createFolderQuery = $db->prepareQuery("
            INSERT INTO folder
                (name, parent, inherit)
            VALUES
                (:folderName, :parent, :inherit)
        ");

        $this->createHomepageQuery = $db->prepareQuery("
            INSERT INTO homepage_config
                (cid, template)
            VALUES
                (:folderId, :template)
        ");

        $this->createFolderPermissionsQuery = $db->prepareQuery("
            INSERT INTO perms
                (cid, gid, type)
            VALUES
                (:folderId, :roleId, :permission)
        ");

        $this->createUnitSpecQuery = $db->prepareQuery("
            INSERT INTO course_unit_specification
                (folder_id, course_specification_id, created_at, updated_at)
            VALUES
                (:folderId, :courseSpecId, :createdAt, :updatedAt)
        ");


        // Queries for moving assessments from one spec to another.
        $this->moveSpecAssessmentQuery = $db->prepareQuery("
            UPDATE assessment_course_unit_specification acus
            SET    acus.course_unit_specification_id = :newUnitSpecId
            WHERE  acus.assessment_id                = :assessmentId
        ");


        // Queries for finding unit implementations for a course spec.

        // Find all impl assessments for the spec assessment
        $this->findImplAssessmentsQuery = $db->prepareQuery("
            SELECT DISTINCT
                var.assessment_id,
                var.course_implementation_id
            FROM v_assessment_relation var
            INNER JOIN assessment           a   ON var.assessment_id           = a.id
            INNER JOIN lu_work_type         lwt ON a.work_type_id              = lwt.id
            INNER JOIN assessment_specification_implementation asi ON var.assessment_id = asi.implementation_assessment_id
            WHERE asi.specification_assessment_id = :specAssessmentId
            AND   var.course_specification_id     IS NULL
            AND   var.course_implementation_id    IS NOT NULL
            AND   var.project_assessment_id       IS NULL
        ");

        // Does the unit implementation already exist?
        $this->findUnitImplQuery = $db->prepareQuery("
            SELECT
                cui.id
            FROM       course_implementation      ci
            INNER JOIN course_unit_implementation cui ON ci.id = cui.course_implementation_id
            WHERE ci.id                            = :courseImplId
            AND   cui.course_unit_specification_id = :newUnitSpecId
        ");

        // When should the new impl start and end?
        $this->findUnitImplDatesQuery = $db->prepareQuery("
            SELECT
                MIN(cui.open_date) AS open_date,
                MAX(cui.due_date)  AS due_date
            FROM       course_implementation      ci
            INNER JOIN course_unit_implementation cui ON ci.id = cui.course_implementation_id
            WHERE ci.id = :courseImplId
        ");

        // Create the unit implementation if it doesn't
        $this->createUnitImplQuery = $db->prepareQuery("
            INSERT INTO course_unit_implementation (
                course_implementation_id,
                course_unit_specification_id,
                title,
                open_date,
                due_date,
                created_at,
                updated_at
            ) VALUES (
                :courseImplId,
                :newUnitSpecId,
                :unitName,
                :openDate,
                :dueDate,
                :createdAt,
                :updatedAt
            )
        ");

        // Move existing unit implementations
        $this->moveImplAssessmentQuery = $db->prepareQuery("
            UPDATE assessment_course_unit_implementation acui
            SET    acui.course_unit_implementation_id = :newUnitImplId
            WHERE  acui.assessment_id                 = :assessmentId
        ");
    }


    /**
     * Creates the unit specification for the given spec and folder.
     *
     * @param int   $courseSpecId
     * @param int   $courseFolderId
     * @param array $courseBuilderConfigs
     * @return int the unit specification's ID, whether it exists or not
     */
    private function createUnitSpecification(int $courseSpecId, int $courseFolderId, array $courseBuilderConfigs): int
    {
        $this->findUnitSpecQuery->execute([
            'courseSpecId' => $courseSpecId,
            'folderName'   => self::UNIT_NAME,
        ]);
        if ($this->findUnitSpecQuery->rowCount() === 0) {
            $this->getLogger()->info(sprintf('Creating new unit spec for course spec #%d', $courseSpecId));
            $this->createFolderQuery->execute([
                'folderName' => self::UNIT_NAME,
                'parent'     => $courseFolderId,
                'inherit'    => $this->getConfig()->get('course_builder_default_unit_page_inherit'),
            ]);

            $newFolderId = $this->getDatabaseInternal()->lastInsertId();

            $this->createHomepageQuery->execute([
                'folderId' => $newFolderId,
                'template' => $this->getConfig()->get('course_builder_default_unit_page_template'),
            ]);

            foreach ($courseBuilderConfigs as $courseBuilderConfig) {
                $this->createFolderPermissionsQuery->execute([
                    'folderId'   => $newFolderId,
                    'roleId'     => $courseBuilderConfig['role_id'],
                    'permission' => $courseBuilderConfig['permission'],
                ]);
            }

            $now = Alaress_Date_Format::now();
            $this->createUnitSpecQuery->execute([
                'courseSpecId' => $courseSpecId,
                'folderId'     => $newFolderId,
                'createdAt'    => $now->getDatabase(),
                'updatedAt'    => $now->getDatabase(),
            ]);

            return (int)$this->getDatabaseInternal()->lastInsertId();

        }

        // otherwise the unit spec already exists
        $this->getLogger()->info(sprintf('Unit spec for course spec #%d already exists: skipping', $courseSpecId));
        return (int)$this->findUnitSpecQuery->fetchColumn();
    }


    /**
     * Creates the unit implementation for the given course impl and unit spec,
     * if it doesn't already exist.
     *
     * @param int $courseImplId
     * @param int $unitSpecId
     * @return int the unit implementation's ID, whether it exists or not
     */
    private function createUnitImplementation(int $courseImplId, int $unitSpecId): int
    {
        $this->findUnitImplQuery->execute([
            'courseImplId'  => $courseImplId,
            'newUnitSpecId' => $unitSpecId,
        ]);

        if ($this->findUnitImplQuery->rowCount() === 0) {
            // create new impl
            $this->getLogger()->info(sprintf('Creating new unit impl for course impl #%d', $courseImplId));
            $this->findUnitImplDatesQuery->execute([
                'courseImplId'  => $courseImplId,
            ]);
            $dates = $this->findUnitImplDatesQuery->fetch();
            $now   = Alaress_Date_Format::now();
            $this->createUnitImplQuery->execute([
                'courseImplId'  => $courseImplId,
                'newUnitSpecId' => $unitSpecId,
                'unitName'      => self::UNIT_NAME,
                'openDate'      => $dates['open_date'],
                'dueDate'       => $dates['due_date'],
                'createdAt'     => $now->getDatabase(),
                'updatedAt'     => $now->getDatabase(),
            ]);
            return (int)$this->getDatabaseInternal()->lastInsertId();
        }

        // return existing impl
        $this->getLogger()->info(sprintf('Unit impl for course impl #%d already exists: skipping', $courseImplId));
        return (int)$this->findUnitImplQuery->fetchColumn();
    }
};


$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];
(new CliTool\Kernel($app, $options))->runApp();
