<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Statement\Query\IQuery;
use Alaress_Date_Format;
use Custom\Tigs\Summative\Config as TigsConfig;
use Schoolbox\Application\CliTool\App;

/**
 * Migrator for TIGS assessments.
 *
 * Any spec assessments that are of these work types are moved to a new
 * "Report Grades" unit spec, and any implementations of those assessments are
 * moved to a corresponding unit implementation:
 * * Work Type - MYP Final Achievement Levels
 * * Work Type - DP Report Grade
 * * Work Type - HSC Assessment Rank
 * * Work Type - NESA Score
 */
class CliAssessmentMigrator extends App
{
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
        if (!$this->perform()) {
            $this->getLogger()->notice('Executing in dry run mode. Provide --perform or -p to migrate assessments.');
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
        $params             = Builder::getArrayParameters($workTypeIds, 'workTypeId');
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
            $courseFolderId   = (int)$specRow['course_folder_id'];
            $specAssessmentId = (int)$specRow['assessment_id'];


            $this->getLogger()->info(
                sprintf(
                    'Moving course #%d / unit #%d / spec assessment #%d (%s/%s/%s)',
                    $courseSpecId,
                    $specRow['course_unit_specification_id'],
                    $specAssessmentId,
                    $specRow['course_name'],
                    $specRow['unit_name'],
                    $specRow['assessment_name']
                )
            );
            if (!isset($unitSpecIds[$courseSpecId])) {
                $unitSpecIds[$courseSpecId] = $this->createUnitSpecification(
                    $courseSpecId,
                    $courseFolderId,
                    $courseBuilderConfigs
                );
            }
            $this->moveAssessmentSpecification($unitSpecIds[$courseSpecId], $specAssessmentId);


            $implAssessments = $this->findAssessmentImplementations($specAssessmentId);
            $this->getLogger()->info(sprintf('.. %d impl assessments for this spec found', count($implAssessments)));

            foreach ($implAssessments as $implRow) {
                $courseImplId     = (int)$implRow['course_implementation_id'];
                $implAssessmentId = (int)$implRow['assessment_id'];
                $folderCodes      = $implRow['folder_codes'];

                if ($folderCodes === '') {
                    $this->getLogger()->debug(
                        sprintf(
                            '.... Impl assessment #%d is in non-class or archived class folder #%d (%s): skipping',
                            $implAssessmentId,
                            $implRow['class_folder_id'],
                            $implRow['class_folder_name']
                        )
                    );
                    continue;
                }

                $this->getLogger()->info(
                    sprintf(
                        '.... Moving impl assessment #%d in class #%d (%s (%s)) to new unit impl',
                        $implAssessmentId,
                        $implRow['class_folder_id'],
                        $implRow['class_folder_name'],
                        $folderCodes
                    )
                );
                if (!isset($unitImplIds[$courseImplId])) {
                    $unitImplIds[$courseImplId] = $this->createUnitImplementation(
                        $courseImplId,
                        $unitSpecIds[$courseSpecId]
                    );
                }
                $this->moveAssessmentImplementation($unitImplIds[$courseImplId], $implAssessmentId);
            }
        }


        // Some sort of summary of what happened
        $this->getLogger()->notice(sprintf('%d unit specs created', count($this->createdUnitSpecs)));
        $this->getLogger()->notice(sprintf('%d spec assessments moved', count($this->movedSpecAssessments)));
        $this->getLogger()->notice(sprintf('%d unit impls created', count($this->createdUnitImpls)));
        $this->getLogger()->notice(sprintf('%d impl assessments moved', count($this->movedImplAssessments)));

        if ($this->perform()) {
            $db->commit();
        } else {
            $db->rollBack();
        }
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
                var.course_unit_specification_id,
                cf.name      AS course_name,
                cuf.name     AS unit_name,
                a.title      AS assessment_name,
                cs.folder_id AS course_folder_id
            FROM v_assessment_relation var
            INNER JOIN course_specification      cs  ON var.course_specification_id      = cs.id
            INNER JOIN folder                    cf  ON cs.folder_id                     = cf.id
            INNER JOIN course_unit_specification cus ON var.course_unit_specification_id = cus.id
            INNER JOIN folder                    cuf ON cus.folder_id                    = cuf.id
            INNER JOIN assessment                a   ON var.assessment_id                = a.id
            INNER JOIN lu_work_type              lwt ON a.work_type_id                   = lwt.id
            WHERE lwt.id IN {$placeholders}
            AND   var.course_specification_id  IS NOT NULL
            AND   var.course_implementation_id IS NULL
            AND   var.project_assessment_id    IS NULL
            AND   cuf.name                     != :unitName
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
                var.course_implementation_id,
                f.id   AS class_folder_id,
                f.name AS class_folder_name,
                COALESCE(GROUP_CONCAT(DISTINCT fc.code ORDER BY fc.code SEPARATOR ','), '') AS folder_codes
            FROM v_assessment_relation var
            INNER JOIN folder                                  f   ON var.folder_id     = f.id
            LEFT  JOIN folder_code                             fc  ON f.id              = fc.folder_id AND fc.deleted_at IS NULL
            INNER JOIN assessment                              a   ON var.assessment_id = a.id
            INNER JOIN lu_work_type                            lwt ON a.work_type_id    = lwt.id
            INNER JOIN assessment_specification_implementation asi ON var.assessment_id = asi.implementation_assessment_id
            WHERE asi.specification_assessment_id = :specAssessmentId
            AND   var.course_specification_id     IS NULL
            AND   var.course_implementation_id    IS NOT NULL
            AND   var.project_assessment_id       IS NULL
            GROUP BY var.assessment_id, var.course_implementation_id, f.id, f.name
            ORDER BY var.assessment_id ASC
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

            $newUnitSpecId                          = (int)$this->getDatabaseInternal()->lastInsertId();
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

            $newUnitImplId                          = (int)$this->getDatabaseInternal()->lastInsertId();
            $this->createdUnitImpls[$newUnitImplId] = $newUnitImplId;
            return $newUnitImplId;
        }

        // return existing impl
        return (int)$this->findUnitImplQuery->fetchColumn();
    }


    /**
     * Move the spec assessment into the newly created unit spec.
     *
     * @param int $unitSpecId
     * @param int $specAssessmentId
     */
    private function moveAssessmentSpecification(int $unitSpecId, int $specAssessmentId): void
    {
        $this->moveSpecAssessmentQuery->execute([
            'newUnitSpecId' => $unitSpecId,
            'assessmentId'  => $specAssessmentId,
        ]);
        if ($this->moveSpecAssessmentQuery->rowCount() > 0) {
            $this->movedSpecAssessments[$specAssessmentId] = $specAssessmentId;
        }
    }


    /**
     * Find all implementations of the given spec assessment.
     *
     * These will be moved to a new unit implementation.
     *
     * @param int $specAssessmentId
     * @return array
     */
    private function findAssessmentImplementations(int $specAssessmentId): array
    {
        $this->findImplAssessmentsQuery->execute([
            'specAssessmentId' => $specAssessmentId,
        ]);
        return $this->findImplAssessmentsQuery->fetchAll();
    }


    /**
     * Move the implementation assessment into the newly created unit
     * implementation.
     *
     * @param int $unitImplId
     * @param int $implAssessmentId
     */
    private function moveAssessmentImplementation($unitImplId, int $implAssessmentId): void
    {
        $this->moveImplAssessmentQuery->execute([
            'newUnitImplId' => $unitImplId,
            'assessmentId'  => $implAssessmentId,
        ]);
        if ($this->moveImplAssessmentQuery->rowCount() > 0) {
            $this->movedImplAssessments[$implAssessmentId] = $implAssessmentId;
        }
    }
}
