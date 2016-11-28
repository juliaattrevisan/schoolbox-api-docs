<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Implementation;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;

/**
 * Mapper for the data model for Course Implementations.
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 */
class Mapper extends BaseMapper
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
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreUnitImpls']);
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
     * Soft deletes all UnitImpls for the specified CourseImpl
     *
     * @param Model $courseImpl
     */
    public function restoreUnitImpls(Model $courseImpl)
    {
        $unitImplMapper = UnitImplementationMapper::getInstance();
        $unitImpls = $unitImplMapper->findByColumn('course_implementation_id', $courseImpl->getId(), ['deleted' => true]);
        $unitImplMapper->restoreSoftDeleted($unitImpls);
    }

    /**
     * Finds the unit spec for this folder.
     *
     * @param int   $folderId
     * @param array $options
     * @return Model[]|Collection
     */
    public function findByFolderId($folderId, $options = [])
    {
        return $this->findByColumn('folder_id', $folderId, $options);
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
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            LEFT  JOIN {$this->getJoinSql('folder', '', 'f')}
            LEFT  JOIN {$this->getJoinSql('folder.homepages', 'f', 'fh')}
            INNER JOIN {$this->getJoinSql('specification', '', 's', true)}
            LEFT  JOIN {$this->getJoinSql('specification.folder', 's', 'sf', true)}
        ";
    }

    /**
     * @inheritdoc
     */
    public function hydrateSelect(PDOStatement $statement)
    {
        $modelList = $this->createCollection();
        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var Folder $folder */
            $folder = $this->hydrateJoin($row, 'folder', 'f');
            $folder->setHomepage($this->hydrateJoin($row, 'folder.homepages', 'fh'));
            $model->setFolder($folder);

            /** @var CourseSpecification $specification */
            $specification = $this->hydrateJoin($row, 'specification', 's');
            $specification->setFolder($this->hydrateJoin($row, 'specification.folder', 'sf'));
            $model->setSpecification($specification);

            // Add
            $modelList->add($model);
        }

        // TODO: consider whether units should be populated here
        return $modelList;
    }
}
