#!/usr/bin/php
<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Predicate\Builder;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {

    private const UNIT_NAME = 'Report Grades';

    private const WORK_TYPE_IDS = [5, 4, 7, 1];

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        $params       = Builder::getArrayParameters(self::WORK_TYPE_IDS, 'workTypeId');
        $placeholders = Builder::getArrayPlaceholders(self::WORK_TYPE_IDS, 'workTypeId');
        $db           = $this->getDatabaseInternal();


        $findCourseSpecsQuery = $db->prepareQuery("
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
        ");


        $hasUnitSpecQuery = $db->prepareQuery("
            SELECT f.id
            FROM       course_specification      cs
            INNER JOIN course_unit_specification cus ON cs.id         = cus.course_specification_id
            INNER JOIN folder                    f   ON cus.folder_id = f.id
            WHERE cs.id  = :courseSpecId
            AND   f.name = :folderName
        ");


        $createFolderQuery = $db->prepareQuery("
            INSERT INTO folder
                (name, parent, inherit)
            VALUES
                (:folderName, :parent, :inherit)
        ");


        $createHomepageQuery = $db->prepareQuery("
            INSERT INTO homepage_config
                (cid, template)
            VALUES
                (:folderId, :template)
        ");


        $createFolderPermissionsQuery = $db->prepareQuery("
            INSERT INTO perms
                (cid, gid, type)
            VALUES
                (:folderId, :roleId, :permission)
        ");


        $findCourseBuilderConfigQuery = $db->prepareQuery("
            SELECT role_id, permission
            FROM course_builder_config
            WHERE page = 'unit'
        ");
        $findCourseBuilderConfigQuery->execute();
        $courseBuilderConfigs = $findCourseBuilderConfigQuery->fetchAll();


        $createUnitSpecQuery = $db->prepareQuery("
            INSERT INTO course_unit_specification
                (folder_id, course_specification_id, created_at, updated_at)
            VALUES
                (:folderId, :courseSpecId, :createdAt, :updatedAt)
        ");

        $findCourseSpecsQuery->execute($params);


        $db->beginTransaction();
        foreach ($findCourseSpecsQuery->getIterator() as $row) {
            $courseSpecId   = $row['course_specification_id'];
            $courseFolderId = $row['folder_id'];
            $hasUnitSpecQuery->execute([
                'courseSpecId' => $courseSpecId,
                'folderName'   => self::UNIT_NAME,
            ]);
            if ($hasUnitSpecQuery->rowCount() === 0) {
                $this->getLogger()->info(sprintf('Creating folder for spec #%d', $courseSpecId));
                $createFolderQuery->execute([
                    'folderName' => self::UNIT_NAME,
                    'parent'     => $courseFolderId,
                    'inherit'    => $this->getConfig()->get('course_builder_default_unit_page_inherit'),
                ]);

                $newFolderId = $db->lastInsertId();

                $createHomepageQuery->execute([
                    'folderId' => $newFolderId,
                    'template' => $this->getConfig()->get('course_builder_default_unit_page_template'),
                ]);

                foreach ($courseBuilderConfigs as $courseBuilderConfig) {
                    $createFolderPermissionsQuery->execute([
                        'folderId'   => $newFolderId,
                        'roleId'     => $courseBuilderConfig['role_id'],
                        'permission' => $courseBuilderConfig['permission'],
                    ]);
                }

                $now = Alaress_Date_Format::now();
                $createUnitSpecQuery->execute([
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
        $db->commit();
    }
};


$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];
(new CliTool\Kernel($app, $options))->runApp();
