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
    private $findCourseSpecsQuery;


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
    private $hasUnitSpecQuery;


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
     * @inheritDoc
     */
    public function run(): void
    {
        $this->prepareQueries();


        $this->findCourseBuilderConfigQuery->execute();
        $courseBuilderConfigs = $this->findCourseBuilderConfigQuery->fetchAll();


        $this->findCourseSpecsQuery->execute(
            Builder::getArrayParameters(self::WORK_TYPE_IDS, 'workTypeId')
        );


        $db = $this->getDatabaseInternal();
        $db->beginTransaction();
        foreach ($this->findCourseSpecsQuery->getIterator() as $row) {
            $courseSpecId   = (int)$row['course_specification_id'];
            $courseFolderId = (int)$row['folder_id'];
            $this->createUnitSpecification($courseSpecId, $courseFolderId, $courseBuilderConfigs);

            // Move spec assessments from one place to the other
            // Create unit implementations
            // Move impl assessments from one place to the other
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
        $this->findCourseSpecsQuery = $db->prepareQuery("
            SELECT DISTINCT
                var.course_specification_id,
                cs.folder_id
            FROM v_assessment_relation var
            INNER JOIN course_specification cs  ON var.course_specification_id = cs.id
            INNER JOIN assessment           a   ON var.assessment_id           = a.id
            INNER JOIN lu_work_type         lwt ON a.work_type_id              = lwt.id
            WHERE lwt.id IN {$placeholders}
            AND   var.course_specification_id  IS NOT NULL
            AND   var.course_implementation_id IS NULL
            AND   var.project_id               IS NULL
        ");

        $this->findCourseBuilderConfigQuery = $db->prepareQuery("
            SELECT role_id, permission
            FROM course_builder_config
            WHERE page = 'unit'
        ");

        $this->hasUnitSpecQuery = $db->prepareQuery("
            SELECT f.id
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
    }


    /**
     * Creates the unit specification for the given spec and folder.
     *
     * @param int   $courseSpecId
     * @param int   $courseFolderId
     * @param array $courseBuilderConfigs
     */
    private function createUnitSpecification(int $courseSpecId, int $courseFolderId, array $courseBuilderConfigs): void
    {
        $db = $this->getDatabaseInternal();
        $this->hasUnitSpecQuery->execute([
            'courseSpecId' => $courseSpecId,
            'folderName'   => self::UNIT_NAME,
        ]);
        if ($this->hasUnitSpecQuery->rowCount() === 0) {
            $this->getLogger()->info(sprintf('Creating folder for spec #%d', $courseSpecId));
            $this->createFolderQuery->execute([
                'folderName' => self::UNIT_NAME,
                'parent'     => $courseFolderId,
                'inherit'    => $this->getConfig()->get('course_builder_default_unit_page_inherit'),
            ]);

            $newFolderId = $db->lastInsertId();

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
        } else {
            // already exists
            $this->getLogger()->info(sprintf('Folder already exists for spec #%d: skipping', $courseSpecId));
        }
    }
};


$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];
(new CliTool\Kernel($app, $options))->runApp();
