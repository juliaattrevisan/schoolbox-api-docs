<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification;

use PDOStatement;
use Ramsey\Uuid\Uuid;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Mapper as NodeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\DataModel\AbstractCourseMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Assessment\Course\Template\Mapper as CourseTemplateMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Resource\HomepageConfig;

/**
 * Mapper for the data model for Course Specifications.
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends AbstractCourseMapper
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
        $this->uses('subject_code', self::TYPE_STRING);
        $this->uses('uuid', self::TYPE_STRING);
        $this->joins('template_id', CourseTemplateMapper::class, 'id', null, null, null, 'template');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        // SoftDelete/Restore UnitSpecifications in CourseSpecification
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteUnits'));
        $this->registerCallback(self::CALLBACK_POSTRESTORE, array($this, 'restoreFolder'));

        /* Assign a uuid if it doesn't already exist
         */
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'assignUuid']);

        $this->registerPersistSearchIndexCallbacks();
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
                {$this->getJoinColumnString('folder.homepages', 'fh')},
                {$this->getJoinColumnString('template', 'tmpl')}
            FROM {$this->database->getQuoter()->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('folder', '', 'f', true)}
            LEFT  JOIN {$this->getJoinSql('template', '', 'tmpl', true)}
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
        // record of folder ids to hydrate curriculum codes from
        $folderIds = [];

        /** @var FolderMapper $folderMapper */
        $folderMapper = $this->getJoinMapper('folder');

        /** @var NodeMapper $curriculumNodeMapper */
        $curriculumNodeMapper = $folderMapper->getJoinMapper('curriculum_node_for_category');

        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var FolderModel $folder */
            $folder = $this->hydrateJoin($row, 'folder', 'f');
            $folderIds[] = $row['f.id'];
            /** @var HomepageConfig $homepage */
            $homepage = $this->hydrateJoin($row, 'folder.homepages', 'fh');
            $folder->setHomepage($homepage);
            /** @var null|\Schoolbox\Assessment\Course\Template\Model $template */
            $template = $this->hydrateJoin($row, 'template', 'tmpl');
            $model->setTemplate($template);
            $model->setFolder($folder);

            // Add
            $modelList->add($model);
        }

        // fetch curric for joined folders
        $folderCurricNodes = $curriculumNodeMapper->findForOtherMapper(
            $folderMapper,
            'curriculum_node_for_category',
            $folderIds
        );

        foreach ($modelList as $model) {
            if (isset($folderCurricNodes[$model->getFolder()->getId()])) {
                // if joined folder has codes, then inject them back onto the folder
                $model->getFolder()->setCurriculumNodes($folderCurricNodes[$model->getFolder()->getId()]);
            } else {
                // folder has no curric codes, set to empty collection
                $model->getFolder()->setCurriculumNodes($curriculumNodeMapper->createCollection());
            }
        }
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
        /** @var Model $model */
        $model = $this->findOneByColumn('folder_id', $folderId, $options);
        /** @var Model $modelWithUnits */
        $modelWithUnits = $this->loadUnits($model);
        return $modelWithUnits;
    }

    /**
     * List the courses. Convenience method for findByColumn('name', LIKE($name));
     *
     * @param string $searchParam
     * @param array  $options
     * @return GenericCollection
     */
    public function findAllByName(string $searchParam, array $options = []): GenericCollection
    {
        if (strlen($searchParam) === 0) {
            return $this->findAll($options);
        }
        $pb = $this->getPredicateBuilder();
        $pb->like('f.name', "%{$this->database->getQuoter()->escapeForLike($searchParam)}%");
        return $this->findByPredicates($pb, $options);
    }

    public function countAllByName(string $searchParam, array $options = []): int
    {
        if (strlen($searchParam) === 0) {
            return $this->countAll($options);
        }

        $pb = $this->getPredicateBuilder();
        $pb->like('f.name', "%{$this->database->getQuoter()->escapeForLike($searchParam)}%");
        $pb->isNull('f.deleted_at');
        $where = $pb->getWhereIntersect();

        $query = "
            SELECT COUNT(DISTINCT s.{$this->getIdentifierColumn()})
            FROM {$this->table} s
            INNER JOIN folder f ON f.id = s.folder_id
            WHERE $where
        ";

        return $this->database->executeQuery($query, $pb->getParams())->fetchColumn();
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

    /**
     * @return UnitSpecificationMapper
     */
    protected function getUnitMapper()
    {
        return UnitSpecificationMapper::getInstance();
    }

    /**
     * Get an ORDER BY clause for sorting units
     * @return string[]
     */
    protected function getUnitOrderByClause()
    {
        return ['f.name' => 'asc'];
    }


    /**
     * Get the ID of the configured faculty root folder
     *
     * Will return NULL if not configured or no numeric ID found
     *
     * @return null|int
     */
    private function getConfiguredFacultyRootFolderId()
    {
        $configuredId = preg_replace('/[\D]/', '', $this->getConfigLoader()->get('course_homepage'));
        if (null === $configuredId || '' === $configuredId) {
            return null;
        }
        return (int)$configuredId;
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
     * Ensures that if we restore a CourseSpec, the folder it is linked to is restored also
     *
     * Intentionally no corresponding post-delete callback. Deleting a CourseSpec should not automatically
     * cascade to the folder. The Controller should explicitly do that if desired.
     *
     * @param Model $courseSpec
     * @return bool
     * @throws BaseMapper\DefinitionException If join $name isn't on this mapper
     */
    public function restoreFolder(Model $courseSpec)
    {
        /** @var FolderMapper $folderMapper */
        $folderMapper = $this->getJoinMapper('folder');
        $folder = $folderMapper->findByColumn('id', $courseSpec->getFolder()->getId(), ['deleted' => true]);
        $folderMapper->restoreSoftDeleted($folder);
        return true;
    }


    /**
     * Return whether the configured faculty root is valid.
     *
     * This will be false if syntax is invalid, or because the folder is missing, or soft-deleted
     *
     * @return bool
     */
    public function isConfiguredFacultyRootValid()
    {
        return (int)$this->getFacultyRootFolder()->getId() === $this->getConfiguredFacultyRootFolderId();
    }


    /**
     * Load the faculty root folder - this is used for validating whether a
     * folder can be made into a course.
     */
    protected function loadFacultyRootFolder()
    {
        /** @var FolderMapper $folderMapper */
        $folderMapper        = $this->getJoinMapper('folder');
        $facultyRootFolderId = $this->getConfiguredFacultyRootFolderId();
        try {
            $facultyRoot = $folderMapper->findCheaplyById($facultyRootFolderId);
        } catch (BaseMapper\Exception $e) {
            $facultyRoot = $folderMapper->findCheaplyById(0);
        }
        $this->setFacultyRootFolder($facultyRoot);
    }


    /**
     * Asserts that every $courseSpec has a uuid prior to saving
     *
     * If it didn't already have one, it will be assigned one
     * Otherwise it'll maintain what it previously knew
     *
     * @param Model $courseSpec
     * @return bool
     */
    public function assignUuid(Model $courseSpec): bool
    {
        if ('' === trim((string)$courseSpec->getUuid())) {
            $courseSpec->setUuid((string)Uuid::uuid4());
        }

        return true;
    }
}
