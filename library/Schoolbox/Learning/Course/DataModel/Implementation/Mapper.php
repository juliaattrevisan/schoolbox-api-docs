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
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Resource\HomepageConfig;

/**
 * Mapper for the data model for Course Implementations.
 *
 * @method Model createModel($idValue = null)
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
     * @param int $identifierValue
     * @return Model
     */
    public function findById($identifierValue)
    {
        /** @var Model $model */
        $model = parent::findById($identifierValue);
        return $this->loadUnits($model);
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
        return $this->loadUnitsForCollection(
            $this->findByColumn('folder_id', $folderId, $options)
        );
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
            /** @var HomepageConfig $homepageConfig */
            $homepageConfig = $this->hydrateJoin($row, 'folder.homepages', 'fh');
            $folder->setHomepage($homepageConfig);
            $model->setFolder($folder);

            /** @var CourseSpecification $specification */
            $specification = $this->hydrateJoin($row, 'specification', 's');
            /** @var Folder $folder */
            $folder = $this->hydrateJoin($row, 'specification.folder', 'sf');
            $specification->setFolder($folder);
            $model->setSpecification($specification);

            // Add
            $modelList->add($model);
        }

        // TODO: consider whether units should be populated here
        return $modelList;
    }

    /**
     * Finds all course impls for this course spec.
     *
     * @param int|array $courseSpecId
     * @param array     $options
     * @return Collection|Model[]
     */
    public function findAllByCourseSpecificationId($courseSpecId, $options = [])
    {
        return $this->findByColumn('course_specification_id', $courseSpecId, $options);
    }

    /**
     * Loads all units implementations into a course implementation.
     *
     * @param Model $implementation
     * @return Model
     */
    protected function loadUnits(Model $implementation)
    {
        $collection = new Collection();
        $collection->add($implementation);
        $this->loadUnitsForCollection($collection);
        return $collection->first();
    }

    /**
     * Loads all units implementations into all the course implementations in a collection.
     *
     * @param Collection|Model[] $courseImpls
     * @return Collection|Model[]
     */
    protected function loadUnitsForCollection(Collection $courseImpls)
    {
        $unitImplMapper = UnitImplementationMapper::getInstance();

        // Load all the unit impls for all the supplied course impls
        $ids = [];
        foreach ($courseImpls as $courseImpl) {
            $ids[] = $courseImpl->getId();
        }
        $unitImpls = $unitImplMapper->findAllByCourseId($ids, ['order' => ['open_date' => 'desc']])
            ->groupByCallback(function ($v) {
                /** @var UnitImplementation $v */
                return $v->getCourse()->getId();
            });

        // Match unitImpls to their courseImpl
        foreach ($courseImpls as $courseImpl) {
            if (isset($unitImpls[$courseImpl->getId()])) {
                $courseImpl->setUnits($unitImpls[$courseImpl->getId()]);
            }
        }
        return $courseImpls;
    }
}
