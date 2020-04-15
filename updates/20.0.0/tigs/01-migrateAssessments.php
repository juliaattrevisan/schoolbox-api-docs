#!/usr/bin/php
<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Statement\Query\IQuery;
use Custom\Tigs\Summative\Config as TigsConfig;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {

    /**
     * The name of the new unit to be created.
     */
    private const UNIT_NAME = 'Report Grades';


    /**
     * Unit specs that have been created by this script.
     *
     * @var array
     */
    private $createdUnitSpecs = [];


    /**
     * Spec assessments that have been moved by this script.
     *
     * @var array
     */
    private $movedSpecAssessments = [];


    /**
     * Unit implementations that have been created by this script.
     *
     * @var array
     */
    private $createdUnitImpls = [];


    /**
     * Implementation assessments that have been moved by this script.
     *
     * @var array
     */
    private $movedImplAssessments = [];


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
        // This script is TIGS only
        if ($this->getConfig()->get('site_location') !== 'tigs') {
            $this->getLogger()->notice('This update is not intended for this environment');
            return;
        }


        // Figure out which work types this applies to, and build queries
        $workTypeIds = $this->retrieveWorkTypeIds();
        if (empty($workTypeIds)) {
            $this->getLogger()->warning('No work types have been configured: no assessments can be moved');
            return;
        }
        $this->prepareQueries($workTypeIds);


        // Load course builder configuration
        $this->findCourseBuilderConfigQuery->execute();
        $courseBuilderConfigs = $this->findCourseBuilderConfigQuery->fetchAll();


        // Load spec assessments to migrate
        $params = Builder::getArrayParameters($workTypeIds, 'workTypeId');
        $params['unitName'] = self::UNIT_NAME;
        $this->findSpecAssessmentsQuery->execute($params);
        $this->getLogger()->notice(
            sprintf(
                '%d spec assessments in specified work types found',
                $this->findSpecAssessmentsQuery->rowCount()
            )
        );


        $db = $this->getDatabaseInternal();
        $db->beginTransaction();

        // Start migrating
        $unitSpecIds = [];
        $unitImplIds = [];
        foreach ($this->findSpecAssessmentsQuery->fetchAll() as $specRow) {
            $courseSpecId     = (int)$specRow['course_specification_id'];
            $courseFolderId   = (int)$specRow['folder_id'];
            $specAssessmentId = (int)$specRow['assessment_id'];


            // Create unit specs if required
            $this->getLogger()->info(sprintf('Moving spec assessment #%d to new unit spec', $specAssessmentId));
            if (!isset($unitSpecIds[$courseSpecId])) {
                $unitSpecIds[$courseSpecId] = $this->createUnitSpecification(
                    $courseSpecId,
                    $courseFolderId,
                    $courseBuilderConfigs
                );
            }


            // Move spec assessments from one place to the other
            $this->moveSpecAssessmentQuery->execute([
                'newUnitSpecId' => $unitSpecIds[$courseSpecId],
                'assessmentId'  => $specAssessmentId,
            ]);
            if ($this->moveSpecAssessmentQuery->rowCount() > 0) {
                $this->movedSpecAssessments[$specAssessmentId] = $specAssessmentId;
            }


            // Find all implementations of the spec assessment
            $this->findImplAssessmentsQuery->execute([
                'specAssessmentId' => $specAssessmentId,
            ]);
            $this->getLogger()->info(
                sprintf(
                    '.. %d impl assessments for this spec found',
                    $this->findImplAssessmentsQuery->rowCount()
                )
            );
            foreach ($this->findImplAssessmentsQuery->fetchAll() as $implRow) {
                $courseImplId     = (int)$implRow['course_implementation_id'];
                $implAssessmentId = (int)$implRow['assessment_id'];


                // Create unit implementations for the new unit spec if required
                if (!isset($unitImplIds[$courseImplId])) {
                    $unitImplIds[$courseImplId] = $this->createUnitImplementation(
                        $courseImplId,
                        $unitSpecIds[$courseSpecId]
                    );
                }


                // And move the implementation assessment into the new unit implementation
                $this->getLogger()->info(sprintf('.... Moving impl assessment #%d to new unit impl', $implAssessmentId));
                $this->moveImplAssessmentQuery->execute([
                    'newUnitImplId' => $unitImplIds[$courseImplId],
                    'assessmentId'  => $implAssessmentId,
                ]);
                if ($this->moveImplAssessmentQuery->rowCount() > 0) {
                    $this->movedImplAssessments[$implAssessmentId] = $implAssessmentId;
                }
            }
        }


        // Some sort of summary of what happened
        $this->getLogger()->notice(sprintf('%d unit specs created', count($this->createdUnitSpecs)));
        $this->getLogger()->notice(sprintf('%d spec assessments moved', count($this->movedSpecAssessments)));
        $this->getLogger()->notice(sprintf('%d unit impls created', count($this->createdUnitImpls)));
        $this->getLogger()->notice(sprintf('%d impl assessments moved', count($this->movedImplAssessments)));

        $db->commit();
    }


    /**
     * Prepare queries that this script requires.
     *
     * @param array $workTypeIds The work types whose assessments will be moved
     */
    private function prepareQueries(array $workTypeIds): void
    {
        $db = $this->getDatabaseInternal();


        // Queries for creating a new unit spec.
        $placeholders                   = Builder::getArrayPlaceholders($workTypeIds, 'workTypeId');
        $this->findSpecAssessmentsQuery = $db->prepareQuery("
            SELECT DISTINCT
                var.assessment_id,
                var.course_specification_id,
                cs.folder_id
            FROM v_assessment_relation var
            INNER JOIN course_specification      cs  ON var.course_specification_id      = cs.id
            INNER JOIN course_unit_specification cus ON var.course_unit_specification_id = cus.id
            INNER JOIN folder                    f   ON cus.folder_id                    = f.id
            INNER JOIN assessment                a   ON var.assessment_id                = a.id
            INNER JOIN lu_work_type              lwt ON a.work_type_id                   = lwt.id
            WHERE lwt.id IN {$placeholders}
            AND   var.course_specification_id  IS NOT NULL
            AND   var.course_implementation_id IS NULL
            AND   var.project_assessment_id    IS NULL
            AND   f.name                       != :unitName
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
            AND   f.name = :unitName
        ");

        $this->createFolderQuery = $db->prepareQuery("
            INSERT INTO folder
                (name, parent, inherit)
            VALUES
                (:unitName, :parent, :inherit)
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
     * Retrieve the list of work types whose assessments are to be moved to the
     * new unit.
     *
     * @return array
     */
    private function retrieveWorkTypeIds(): array
    {
        return array_filter([
            (int)$this->getConfig()->get(TigsConfig::_TIGS_WORK_TYPE_MYP_FINAL_ACHIEVEMENT_LEVELS),
            (int)$this->getConfig()->get(TigsConfig::_TIGS_WORK_TYPE_DIPLOMA_PROGRAMME_REPORT_GRADE),
            (int)$this->getConfig()->get(TigsConfig::_TIGS_WORK_TYPE_HSC_ASSESSMENT_RANK),
            (int)$this->getConfig()->get(TigsConfig::_TIGS_WORK_TYPE_NESA_SCORE),
        ]);
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
            'unitName'     => self::UNIT_NAME,
        ]);
        if ($this->findUnitSpecQuery->rowCount() === 0) {
            $this->getLogger()->info(sprintf('.. Creating new unit spec for course spec #%d', $courseSpecId));
            $this->createFolderQuery->execute([
                'unitName' => self::UNIT_NAME,
                'parent'   => $courseFolderId,
                'inherit'  => $this->getConfig()->get('course_builder_default_unit_page_inherit'),
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

            $newUnitSpecId = (int)$this->getDatabaseInternal()->lastInsertId();
            $this->createdUnitSpecs[$newUnitSpecId] = $newUnitSpecId;
            return $newUnitSpecId;
        }

        // otherwise the unit spec already exists
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
            $this->getLogger()->info(sprintf('.... Creating new unit impl for course impl #%d', $courseImplId));
            $this->findUnitImplDatesQuery->execute([
                'courseImplId' => $courseImplId,
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

            $newUnitImplId = (int)$this->getDatabaseInternal()->lastInsertId();
            $this->createdUnitImpls[$newUnitImplId] = $newUnitImplId;
            return $newUnitImplId;
        }

        // return existing impl
        return (int)$this->findUnitImplQuery->fetchColumn();
    }
};


$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::NOTICE, CliTool\Kernel::OPTION_ERRORS => E_ALL,];
(new CliTool\Kernel($app, $options))->runApp();
