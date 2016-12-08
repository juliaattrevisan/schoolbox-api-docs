<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;

/**
 * Mapper for the data model for Course Specifications.
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 */
class Mapper extends BaseMapper
{
    /**
     * The base faculty folder.
     *
     * A course specification's folder should be a child of this folder.
     *
     * @var FolderModel
     */
    protected $facultyRootFolder;

    /**
     * Setup data source, model and data mappings.
     */
    protected function mapping()
    {
        $this->hasTable('course_specification');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('folder_id', FolderMapper::class, 'id', null, null, null, 'folder');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        // SoftDelete/Restore UnitSpecifications in CourseSpecification
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteUnits'));
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restoreUnits'));

        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restoreFolder'));
    }

    /**
     * Soft deletes all Units for the specified CourseSpecification
     *
     * @param Model $courseSpec
     * @return bool
     */
    public function softDeleteUnits(Model $courseSpec)
    {
        $unitMapper = UnitSpecificationMapper::getInstance();
        $units = $unitMapper->findByColumn('course_specification_id', $courseSpec->getId(), ['deleted' => true]);
        $unitMapper->delete($units);
        return true;
    }

    /**
     * Restores all soft-deleted Units for the specified CourseSpecification
     *
     * @param Model $courseSpec
     * @return bool
     */
    public function restoreUnits(Model $courseSpec)
    {
        $unitMapper = UnitSpecificationMapper::getInstance();
        $units = $unitMapper->findByColumn('course_specification_id', $courseSpec->getId(), ['deleted' => true]);
        $unitMapper->restoreSoftDeleted($units);
        return true;
    }

    /**
     * Ensures that if we restore a CourseSpec, the folder it is linked to is restored also
     *
     * Intentionally no corresponding post-delete callback. Deleting a CourseSpec should not automatically
     * cascade to the folder. The Controller should explicitly do that if desired.
     *
     * @param Model $courseSpec
     * @return bool
     */
    public function restoreFolder(Model $courseSpec)
    {
        /** @var FolderMapper $folderMapper */
        $folderMapper = $this->getJoinMapper('folder_id');
        $folder = $folderMapper->findByColumn('id', $courseSpec->getFolder()->getId(), ['deleted' => true]);
        $folderMapper->restoreSoftDeleted($folder);
        return true;
    }

    /**
     * Load the faculty root folder - this is used for validating whether a
     * folder can be made into a course.
     */
    protected function loadFacultyRootFolder()
    {
        /** @var FolderMapper $folderMapper */
        $folderMapper        = $this->getJoinMapper('folder');
        $facultyRootFolderId = preg_replace('/[^0-9]/', '', $this->getConfigLoader()->get('course_homepage'));
        $this->setFacultyRootFolder($folderMapper->findCheaplyById($facultyRootFolderId));
    }

    /**
     * Folders are selected by default.
     *
     * @return string
     */
    public function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('folder', 'f')},
                {$this->getJoinColumnString('folder.homepages', 'fh')}
            FROM {$this->database->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('folder', '', 'f', true)}
            LEFT  JOIN {$this->getJoinSql('folder.homepages', 'f', 'fh')}
        ";
    }

    /**
     * Hydrate joined folders
     *
     * @param PDOStatement $statement
     * @return Collection|Model[]
     */
    public function hydrateSelect(PDOStatement $statement)
    {
        $modelList = $this->createCollection();
        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var FolderModel $folder */
            $folder = $this->hydrateJoin($row, 'folder', 'f');
            $folder->setHomepage($this->hydrateJoin($row, 'folder.homepages', 'fh'));
            $model->setFolder($folder);

            // Add
            $modelList->add($model);
        }

        // TODO: consider whether units should be populated here
        return $modelList;
    }

    /**
     * Finds the course for this folder ID.
     *
     * @param int   $folderId
     * @param array $options
     * @return Model
     * @throws BaseMapper\Exception if no instance of model is found
     * @throws BaseMapper\Exception\Deleted if the model was found but has been deleted
     */
    public function findByFolderId($folderId, $options = [])
    {
        return $this->findOneByColumn('folder_id', $folderId, $options);
    }

    /**
     * List the courses. Convenience method for findByColumn('name', LIKE($name));
     *
     * @param string $searchParam
     * @param array  $options
     * @return Collection|Model[]
     */
    public function findAllByName($searchParam, $options = [])
    {
        if (strlen($searchParam) === 0) {
            return $this->findAll($options);
        }
        return $this->findByColumn('f.name', "%{$this->database->escapeForLike($searchParam)}%", $options);
    }

    /**
     * Validator for if a folder is a valid folder for a course.
     *
     * It is valid if it's a child of the faculty node.
     *
     * @param FolderModel $folder
     * @return bool
     */
    public function isFolderValid(FolderModel $folder)
    {
//        // If the folder hasn't changed, it's fine for now.
//        // (This permits courses to be created outside of the faculty node.)
//        if ($previous instanceof Model
//            && $previous->getFolder()->getId()
//            && $previous->getFolder()->getId() == $model->getFolder()->getId()
//        ) {
//            return true;
//        }

        // The folder must be in the faculty node.
        /** @var FolderMapper $folderMapper */
        $folderMapper = $this->getJoinMapper('folder');
        if (!$folderMapper->isDescendant($folder, $this->getFacultyRootFolder())) {
            return false;
//            throw new \UnexpectedValueException('The course\'s folder must be in the faculty root');
        }
        return true;
    }

    /* Getters/setters =(^_^)= */

    /**
     * @return FolderModel
     */
    public function getFacultyRootFolder()
    {
        // Lazy-load
        if (!($this->facultyRootFolder instanceof FolderModel)) {
            $this->loadFacultyRootFolder();
        }
        return $this->facultyRootFolder;
    }

    /**
     * @param FolderModel $facultyRootFolder
     * @return static
     */
    public function setFacultyRootFolder($facultyRootFolder)
    {
        $this->facultyRootFolder = $facultyRootFolder;
        return $this;
    }
}
