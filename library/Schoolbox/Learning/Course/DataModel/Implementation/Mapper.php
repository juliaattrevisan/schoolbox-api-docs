<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Implementation;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\DataModel\AbstractCourseMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Resource\HomepageConfig;

/**
 * Mapper for the data model for Course Implementations.
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method GenericCollection findAllByIds($ids, $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method GenericCollection|Model[] findByColumn($columnName, $columnValue, $options = [])
 */
class Mapper extends AbstractCourseMapper
{
    /**
     * Setup data source, model and data mappings.
     */
    protected function mapping()
    {
        $this->hasTable('course_implementation');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('course_specification_id', CourseSpecificationMapper::class, 'id', null, null, 'specification', 'specification');
        $this->joins('folder_id', FolderMapper::class, 'id', null, null, 'folder', 'folder');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        // SoftDelete/Restore weak entities
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteUnitImpls']);
    }

    /**
     * Soft deletes all UnitImpls for the specified CourseImpl
     *
     * @param Model $courseImpl
     */
    public function softDeleteUnitImpls(Model $courseImpl)
    {
        $unitImplMapper = UnitImplementationMapper::getInstance();
        $unitImpls = $unitImplMapper->findByColumn('course_implementation_id', $courseImpl->getId(), ['deleted' => true]);
        $unitImplMapper->delete($unitImpls);
    }


    /**
     * Finds the Courses Implementations for this folder.
     *
     * @param int|int[] $folderId
     * @param array     $options
     * @return Model[]|GenericCollection
     */
    public function findByFolderId($folderId, $options = [])
    {
        $models = $this->findByColumn('folder_id', $folderId, $options);
        return $this->loadUnitsForCollection($models);
    }

    /**
     * @inheritdoc
     */
    public function getSelectString()
    {
        // Ensure folder + homepage, specification + its folder are selected.
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('folder', 'f')},
                {$this->getJoinColumnString('folder.homepages', 'fh')},
                {$this->getJoinColumnString('specification', 's')},
                {$this->getJoinColumnString('specification.folder', 'sf')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('folder', '', 'f', true)}
            LEFT  JOIN {$this->getJoinSql('folder.homepages', 'f', 'fh')}
            INNER JOIN {$this->getJoinSql('specification', '', 's', true)}
            INNER JOIN {$this->getJoinSql('specification.folder', 's', 'sf', true)}
        ";
    }

    /**
     * @inheritdoc
     */
    public function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $modelList = $this->createCollection();
        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            /** @var Folder $folder */
            $folder = $this->hydrateJoin($row, 'folder', 'f');
            /** @var HomepageConfig $homepageConfig */
            $homepageConfig = $this->hydrateJoin($row, 'folder.homepages', 'fh');
            $folder->setHomepage($homepageConfig);
            $model->setFolder($folder);

            /** @var CourseSpecification $specification */
            $specification = $this->hydrateJoin($row, 'specification', 's');
            $specification->getCourseImplementations()[$model->getId()] = $model;
            /** @var Folder $folder */
            $folder = $this->hydrateJoin($row, 'specification.folder', 'sf');
            $specification->setFolder($folder);
            $folder->setCourseSpecification($specification);
            $model->setSpecification($specification);

            // Add
            $modelList[$model->getId()] = $model;
        }
        return $modelList;
    }

    /**
     * Finds all course impls for this course spec.
     *
     * @param int|array $courseSpecId
     * @param array     $options
     * @param bool      $loadUnits
     * @return Collection|Model[]
     */
    public function findAllByCourseSpecificationId($courseSpecId, $options = [], $loadUnits = false)
    {
        $courseImpls = $this->findByColumn('course_specification_id', $courseSpecId, $options);

        // Hydrate course implementation folders so that class codes will be available.
        $folderIds = $courseImpls->map(fn($courseImpl) => $courseImpl->getFolder()->getId());
        $folders = $this->getJoinMapper('folder')->findByColumn('id', $folderIds);
        foreach ($courseImpls as $courseImpl) {
            $courseImpl->setFolder($folders->getBy('id', $courseImpl->getFolder()->getId()));
        }

        if ($loadUnits) {
            $courseImpls = $this->loadUnitsForCollection($courseImpls);
        }

        return $courseImpls;
    }

    /**
     * Return a data structure like the following:
     *
     * Example return value: [
     *     23 => // courseImplId
     *     [
     *         80 => // folderId
     *         [
     *             4883, // componentInstanceIds referring to Course Outline components
     *             4884, //
     *         ]
     *     ]
     * ]
     *
     * The multi-level data structure is used because each course implementation can be implemented in multiple folders,
     * each of which can have multiple course outline components linking to that course implementation.
     *
     * Note that in practice a folder should never have multiple course outline components linking to the same course
     * implementation, but since the database schema allows it, the data structure returned by this function must also
     * allow it.
     *
     * @param array $courseImplIds
     * @return array<int, array<int, array<int>>> See more detailed description above.
     */
    public function getCourseOutlineComponentIdsStruct(array $courseImplIds): array
    {
        $rows = $this->getDatabase()->executeQuery(
            'SELECT cci_importedCourse.value AS courseImplId, ci.id AS componentInstanceId, f.id AS folderId
            FROM component_definition cd
            JOIN component_instance ci ON (ci.component_definition_id=cd.id)
            JOIN folder f ON (
                f.id=ci.identifier AND
                f.deleted_at IS NULL
            )
            JOIN component_config_definition ccd_importedCourse ON (
                ccd_importedCourse.component_definition_id = cd.id AND
                ccd_importedCourse.key = "importedCourse"
            )
            LEFT JOIN component_config_instance cci_importedCourse ON (
                cci_importedCourse.component_config_definition_id=ccd_importedCourse.id AND
                cci_importedCourse.component_instance_id=ci.id
            )
            WHERE cd.`class` = "Schoolbox\\\Learning\\\Assessment\\\Component\\\Homepage\\\Outline\\\Controller"
            AND ci.module="homepage"
            AND ' . (
                count($courseImplIds) > 0
                ? 'cci_importedCourse.value IN (' . implode(',', array_map('intval', $courseImplIds)) . ')'
                : 'FALSE'
            )
        )->fetchAll();

        $componentIdsStruct = [];
        foreach ($rows as $row) {
            $courseImplId = $row['courseImplId'];
            $folderId = $row['folderId'];
            if (!array_key_exists($courseImplId, $componentIdsStruct)) {
                $componentIdsStruct[$courseImplId] = [];
            }
            if (!array_key_exists($folderId, $componentIdsStruct[$courseImplId])) {
                $componentIdsStruct[$courseImplId][$folderId] = [];
            }
            $componentIdsStruct[$courseImplId][$folderId][] = $row['componentInstanceId'];
        }

        return $componentIdsStruct;
    }

    /**
     * @return UnitImplementationMapper
     */
    protected function getUnitMapper()
    {
        return UnitImplementationMapper::getInstance();
    }

    /**
     * Get an ORDER BY clause for sorting units
     * @return string[]
     */
    protected function getUnitOrderByClause()
    {
        return ['due_date' => 'asc', 'title' => 'asc'];
    }
}
