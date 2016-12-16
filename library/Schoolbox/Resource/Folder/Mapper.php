<?php
/**
 * Mapper
 *
 * Eventually migrate CategoryMapper into here
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Folder;

use PDO;
use PDOStatement;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use External_UserInterface;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\AbstractFolderMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Realtime\Message\RerouteOverrideMapper;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Resource\File;
use Schoolbox\Resource\Folder\Search\Result;
use Schoolbox\Resource\GroupMapper;
use Schoolbox\Resource\Group;
use Schoolbox\Resource\HomepageConfig;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\User;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Error\Mapper as ErrorMapper;

/**
 * Class Mapper
 *
 * SBFS Folder Mapper. Maps category table to SBFS folder objects.
 *
 * @package Schoolbox\Resource\Folder
 * @method Model createModel($idValue = null)
 * @method Collection|Model[] findByColumnList($columnList, $options = []);
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends AbstractFolderMapper implements ILocator, Searchable
{
    const INHERIT_ON = 1;
    const INHERIT_OFF = 0;
    const FOLDER_ROOT = 0;

    /**
     * Maximum number of recursion iterations to execute before dying
     *
     * @var int
     */
    protected $maxRecursionDepth = 100;

    /**
     * Interface with external user database.
     *
     * @var External_UserInterface
     */
    protected $external_user;

    /**
     * Array of cached NNIAN nodes
     *
     * @var array
     */
    protected $cacheNnian = array();

    /**
     * Interim nodes.
     *
     * @var array
     */
    protected $interimNodes = array();

    /**
     * A cache of ancestral Folder instances, to save
     * on re-querying the same Folder from the db over
     * and over again
     *
     * @var Ancestry
     */
    protected $ancestry;

    /**
     * Mapper constructor.
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * @inheritdoc
     *
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function mapping()
    {
        parent::mapping();

        $this->hasModel(Model::class);
        $this->hasTable('folder');

        $this->uses('inherit', self::TYPE_BOOLEAN);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->uses('colour', self::TYPE_COLOR);

        // Joins:
        $this->joins('image_hash', \Schoolbox\Resource\Storage\Mapper::class, 'hash', null, null, 'coverImage', 'coverImage');

        $this->hasJoin(
            'codes', array(
                'multiple'  => true,
                'mapper'    => Code\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'folder_id',
            )
        );

        $this->hasJoin(
            'homepages', array(
                'multiple'  => false,
                'mapper'    => HomepageConfigMapper::class,
                'this_key'  => 'id',
                'other_key' => 'cid',
            )
        );

        $this->hasJoin(
            'files', array(
                'multiple'  => true,
                'mapper'    => File\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'category',
            )
        );

        $this->hasJoin(
            'groups', array(
                'multiple'  => true,
                'mapper'    => GroupMapper::class,
                'this_key'  => 'id',
                'other_key' => 'cid',
            )
        );

        $this->hasJoin(
            'course_specification', array(
                'multiple'  => false,
                'mapper'    => CourseSpecificationMapper::class,
                'this_key'  => 'id',
                'other_key' => 'folder_id',
            )
        );

        $this->hasJoin(
            'unit_specification', array(
                'multiple'  => false,
                'mapper'    => UnitSpecificationMapper::class,
                'this_key'  => 'id',
                'other_key' => 'folder_id',
            )
        );

        $this->joinsVia(
            'curriculum_node_for_category',
            Node\Mapper::class,
            'id',
            'curriculum_node_id',
            'category_id',
            null,
            null,
            null,
            'curriculumNodes'
        );

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'validateFolderCodes']);

        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'updateInheritedPermissions']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'updateFolderCodes']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'unMuteClassNotifications']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'saveHomepageConfig']);

        $this->registerCallback(self::CALLBACK_POSTDELETE, [$this, 'hardDeleteCategoryCodes']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteCategoryCodes']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteCourseSpecification']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteUnitSpecification']);

        $this->ancestry = new Ancestry($this);
    }

    /**
     * Post soft-delete callback to soft-delete the CourseSpec linked to this Folder if one exists
     *
     * Intentionally no corresponding 'postRestore' callback.
     *
     * @param Model $folder
     * @return bool
     */
    public function softDeleteCourseSpecification(Model $folder)
    {
        /** @var CourseSpecificationMapper $courseSpecMapper */
        $courseSpecMapper = $this->getJoinMapper('course_specification');
        $courseSpec = $courseSpecMapper->findByColumn('folder_id', $folder->getId(), ['deleted' => true]);
        $courseSpecMapper->delete($courseSpec);
        return true;
    }

    /**
     * Post soft-delete callback to soft-delete the UnitSpec linked to this Folder if one exists
     *
     * Intentionally no corresponding 'postRestore' callback.
     *
     * @param Model $folder
     * @return bool
     */
    public function softDeleteUnitSpecification(Model $folder)
    {
        /** @var UnitSpecificationMapper $unitSpecMapper */
        $unitSpecMapper = $this->getJoinMapper('unit_specification');
        $unitSpec = $unitSpecMapper->findByColumn('folder_id', $folder->getId(), ['deleted' => true]);
        $unitSpecMapper->delete($unitSpec);
        return true;
    }

    /**
     * Log a page view
     *
     * @param Model $category
     */
    public function logPageView(Model $category) {

        // new mechanism for storing pageviews
        // the use of NOW() generates a truncation warning (non-fatal and non-critical) since a datetime is being
        // shoved into a date field, but it can be safely ignored: the data inserts correctly
        $query = "
            INSERT INTO homepage_visits (`date`, `user_group_id`, `category_id`)
            VALUES (NOW(), :gid, :cid)
            ON DUPLICATE KEY
            UPDATE `hitcount` = `hitcount` + 1
        ";

        $params = array(
            'cid' => $category->getId(),
            'gid' => $_SESSION['gid']
        );

        $statement = $this->database->prepare($query);
        $statement->execute($params);
    }

    /**
     * Find all images for a category
     *
     * @deprecated use File\Mapper::findForFolders() directly
     * @param Model $folder
     * @param int $limit
     * @param bool $excludeWork
     * @return File\Model[]|Collection
     */
    public function findImages(Model $folder, $limit = 0, $excludeWork = false)
    {
        /** @var File\Mapper $fileMapper */
        $fileMapper = $this->getJoinMapper('files');

        $limit = (int)$limit;

        $opts = [ 'filter' => 'image' ];

        if ($limit) {
            $opts['size'] = $limit;
        }

        return $fileMapper->findForFolders([ $folder->getId() ], $opts);
    }

    /**
     * Pre-save callback to ensure a folder's codes are valid
     * @param Model $model
     * @return bool
     */
    public function validateFolderCodes($model)
    {
        // iterate codes given; if any already exist, error 136
        $codeList = $model->getCodes()->getColumnValues('code');

        $collisions = $this->getJoinMapper('codes')->findByColumn('code', $codeList);

        // retain any codes that do NOT match the given model
        $id = $model->getId();
        $collisions = $collisions->filter(
            function ($code) use ($id) {
                /** @var Code\Model $code */
                return $code->getFolder()->getId() != $id;
            }
        );

        if ($collisions->count()) {
            error(136);
        }

        // persist Model object
        return true;
    }

    /**
     * Pre-save callback: ensures that folders can't be their own parents
     *
     * @param Model $model
     *
     * @return bool
     */
    public function checkParentage($model)
    {
        // Ensure $model isn't its own parent
        try {
            $this->findParents($model);
        } catch (\OverflowException $e) {
            return false;
        }
        return true;
    }

    /**
     * Is $possibleDescendant a descendant of $possibleAncestor?
     *
     * @param Model $possibleDescendant The folder that may be a descendant of $possibleAncestor
     * @param Model $possibleAncestor
     * @return bool
     */
    public function isDescendant(Model $possibleDescendant, Model $possibleAncestor)
    {
        foreach ($this->findParents($possibleDescendant, null, true) as $ancestor) {
            if ($ancestor->getId() == $possibleAncestor->getId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is $possibleAncestor an ancestor of $possibleDescendant?
     *
     * @param Model $possibleAncestor The folder that may be an ancestor of $possibleDescendant
     * @param Model $possibleDescendant
     * @return bool
     */
    public function isAncestor(Model $possibleAncestor, Model $possibleDescendant)
    {
        return $this->isDescendant($possibleDescendant, $possibleAncestor);
    }

    /**
     * PostSave callback to clear permissions if they are now being inherited
     *
     * @param Model $model
     * @param Model $previous
     * @return bool
     */
    public function updateInheritedPermissions($model, $previous = null)
    {
        // If we are inheriting permissions delete any previously created group perms
        if ($model->getInherit()) {
            $this->getJoinMapper('groups')->deleteByColumn('cid', $model->getId());
        }
        return true;
    }


    /**
     * PostSave callback to save the folder's folderCodes
     *
     * @param Model $model
     * @param Model $previous
     * @return bool
     */
    public function updateFolderCodes($model, $previous = null)
    {
        $folderCodeMapper = $this->getJoinMapper('codes');
        // first drop the previous code(s) connected to this folder
        $folderCodes = $folderCodeMapper->findByColumn('folder_id', $model->getId());
        if($folderCodes->count()){
            $folderCodeMapper->delete($folderCodes);
        }

        // only execute if folder has codes and not inheriting permissions
        if ($model->getCodes()->count() && !$model->getInherit()) {
            // bind the given codes to this category
            foreach ($model->getCodes() as $code) {
                $code->setFolder($model);
            }

            // persist the Code objects
            $folderCodeMapper->save($model->getCodes());
        }
        return true;
    }

    /**
     * Post-save callback to ensure that if this folder is a class, that its notifications are not muted
     *
     * @param Model $model
     * @param Model $previous
     * @return bool
     */
    public function unMuteClassNotifications($model, $previous = null)
    {
        // only execute if folder has codes and not inheriting permissions
        if ($model->getCodes()->count() && !$model->getInherit()) {

            // remove any mutes that might exist on this subject (classes are not allowed to be muted)
            RerouteOverrideMapper::getInstance()->deleteByColumnList([
                'delivery'      => RoutePermission::MUTE,
                'msg_route_key' => "service.1.{$model->getId()}"
            ]);
        }

        return true;
    }

    /**
     * Postsave callback - saves a homepage config for newly created folders.
     *
     * @param Model $model
     * @param Model $previous
     * @return bool
     */
    public function saveHomepageConfig($model, $previous = null)
    {
        if (!isset($previous)) {
            $homepage = $model->getHomepage();
            if ($homepage instanceof HomepageConfig && $homepage->getTemplate()->getId()) {
                $model->getHomepage()->setCategory($model);
                $homepageConfigMapper = $this->getJoinMapper('homepages');
                $homepageConfigMapper->save($model->getHomepage());
            }
        }
    }

    /**
     * Hard deletes all category codes that belong to this object.
     *
     * Called as a post delete callback.
     *
     * @param Model $object
     */
    public function hardDeleteCategoryCodes(Model $object)
    {
        $codeMapper = $this->getJoinMapper('codes');
        $codeMapper->hardDelete($codeMapper->findByColumn('folder_id', $object->getId()));
    }

    /**
     * Soft deletes all category codes that belong to this object.
     *
     * Called as a post soft-delete callback.
     *
     * @param Model $object
     */
    public function softDeleteCategoryCodes(Model $object)
    {
        $codeMapper = $this->getJoinMapper('codes');
        $codeMapper->softDelete($codeMapper->findByColumn('folder_id', $object->getId()));
    }

    /**
     * Get a list of categorys which belong to a particular parent
     *
     * It should only get the children of $id and probably should be
     * established in Ancestry
     *
     * @param int $id
     * @return Collection|Model[]
     * @throws MapperException
     */
    public function findByParent($id)
    {
        $code_mapper = $this->getJoinMapper('codes');
        $homepageConfig_mapper = $this->getJoinMapper('homepages');
        $group_mapper = $this->getJoinMapper('groups');
        $courseSpecMapper = $this->getJoinMapper('course_specification');
        $unitSpecMapper = $this->getJoinMapper('unit_specification');



        $query = "
            SELECT
                {$this->getColumnString('c')},
                {$code_mapper->getColumnString('cc')},
                {$homepageConfig_mapper->getColumnString('hp')},
                {$group_mapper->getColumnString('ug')},
                {$courseSpecMapper->getColumnString('cs')},
                {$unitSpecMapper->getColumnString('us')}
            FROM {$this->getTable()} c
            LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}
            LEFT JOIN {$this->getJoinSql('homepages', 'c', 'hp')}
            LEFT JOIN {$this->getJoinSql('groups', 'c', 'ug')}
            LEFT JOIN {$this->getJoinSql('course_specification', 'c', 'cs')}
            LEFT JOIN {$this->getJoinSql('unit_specification', 'c', 'us')}
            WHERE c.deleted_at IS NULL AND c.parent=?
            ORDER BY c.name
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(array($id));

        $collection = new Collection();
        $category = null;

        foreach ($statement as $row) {
            $existingCat = $collection->filterBy('id', $row['c.id']);
            if ($existingCat->count()) {
                //Folder already exists in collection
                $code = $code_mapper->hydrate($row, 'cc');
                if ($code->getFolder()->getId()) {
                    $existingCat->first()->addCode($code);
                }
            } else {
                $category = $this->hydrate($row, 'c');

                /** @var HomepageConfig $homepageConfig */
                $homepageConfig = $homepageConfig_mapper->hydrate($row, 'hp');
                $category->setHomepage($homepageConfig);

                /** @var \Schoolbox\Resource\Group $group */
                $group = $group_mapper->hydrate($row, 'ug');
                $category->setGroup($group);

                /** @var \Schoolbox\Resource\Folder\Code\Model $code */
                $code = $code_mapper->hydrate($row, 'cc');
                if ($code->getFolder()->getId()) {
                    $category->addCode($code);
                }
                $collection->add($category);

                $courseSpec = $courseSpecMapper->hydrate($row, 'cs');
                $category->setCourseSpecification($courseSpec);

                $unitSpec = $unitSpecMapper->hydrate($row, 'us');
                $category->setUnitSpecification($unitSpec);
            }
        }

        return $collection;
    }

    /**
     * Returns a collection of all folders that are children of the specified ID (with optional recursion)
     *
     * @param int $id
     * @param bool $recurse
     * @return Collection|\Schoolbox\Resource\Folder\Model[]
     */
    public function findAllChildren($id, $recurse = false)
    {
        $collection = new Collection();

        // Find all children of this node

        $children = $this->findByColumn("parent", $id);

        foreach ($children as $child) {
            $collection->add($child);

            // And optionally all of its children

            if ($recurse) {
                $kids = $this->findAllChildren($child->getId(), true);
                foreach ($kids as $kid)
                    $collection->add($kid);
            }
        }

        // Return what we got

        return $collection;
    }

    /**
     * Get a hydrated category
     *
     * @param $column
     * @param $value
     * @return Collection
     */
    public function findByColumnWithCodes($column, $value)
    {
        $categories = $this->findByColumn($column, $value);

        foreach ($categories as $category) {
            $category->setCodes($this->getJoinMapper('codes')->findByColumn('folder_id', $category->getId()));
        }

        return $categories;
    }


    /**
     * Search for folders with the given curriculum code(s)
     *
     * @param string|array $code
     * @return Collection|Model[]
     * @throws MapperException
     */
    public function findByCurriculumCode($code)
    {
        if (!$code) {
            // no code? no matches
            return $this->createCollection();
        }

        // cast singular to plural
        $code = (array)$code;

        // generate prepare-param string
        $qmarks = implode(', ', array_pad([], count($code), '?'));

        // search on curriculum node code
        $q = $this->getSelectString() . $this->getFromString() . "WHERE cn.code in ($qmarks)";

        // search, hydrate, return
        return $this->hydrateSelect($this->getDatabase()->query($q, $code));
    }

    /**
     * Helper method for creating a search query with codes, homepage config and
     * group all built in.
     *
     * You can add a WHERE statement at the end if you want.
     *
     * @return string
     */
    protected function getSelectString()
    {
        $code_mapper = $this->getJoinMapper('codes');
        $homepageConfig_mapper = $this->getJoinMapper('homepages');
        $group_mapper = $this->getJoinMapper('groups');
        $courseSpecMapper = $this->getJoinMapper('course_specification');
        $unitSpecMapper = $this->getJoinMapper('unit_specification');


        return "
            SELECT
                {$this->getColumnString()},
                {$code_mapper->getColumnString('cc')},
                {$homepageConfig_mapper->getColumnString('hp')},
                {$group_mapper->getColumnString('ug')},
                {$courseSpecMapper->getColumnString('cs')},
                {$unitSpecMapper->getColumnString('us')},
                {$this->getJoinColumnString('curriculum_node_for_category', 'cn')},
                {$this->getJoinColumnString('curriculum_node_for_category.level', 'cnl')},
                {$this->getJoinColumnString('curriculum_node_for_category.level.source', 'cnls')},
                {$this->getJoinColumnString('curriculum_node_for_category.area', 'cna')},
                {$this->getJoinColumnString('curriculum_node_for_category.area.source', 'cnas')}
        ";
    }


    /**
     * It seems we're going down this road...
     *
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('codes', '', 'cc')}
            LEFT JOIN {$this->getJoinSql('homepages', '', 'hp')}
            LEFT JOIN {$this->getJoinSql('groups', '', 'ug')}
            LEFT JOIN {$this->getJoinSql('course_specification', '', 'cs')}
            LEFT JOIN {$this->getJoinSql('unit_specification', '', 'us')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_category', '', 'cn')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_category.level', 'cn', 'cnl')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_category.level.source', 'cnl', 'cnls')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_category.area', 'cn', 'cna')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_category.area.source', 'cna', 'cnas')}
        ";
    }


    /**
     * Search by name
     *
     * @param string $keyword
     * @param bool $userGroupsOnly
     * @param bool $getCourseSpec   Whether to hydrate and attach any associated CourseSpec
     * @param bool $getUnitSpec     Whether to hydrate and attach any associated UnitSpec
     * @return Collection|Model[]
     */
    public function searchByName($keyword, $userGroupsOnly = false, $getCourseSpec = false, $getUnitSpec = false)
    {
        $code_mapper      = $this->getJoinMapper('codes');
        $group_mapper     = $this->getJoinMapper('groups');
        $courseSpecMapper = $this->getJoinMapper('course_specification');
        $unitSpecMapper   = $this->getJoinMapper('unit_specification');

        $selectClause = "
            {$this->getColumnString('c')},
            {$code_mapper->getColumnString('cc')}";
        $joinClause = "
            LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}";

        /* Filter result set further by only returning records that are also
         * in the userGroups table
         */
        if($userGroupsOnly){
            $selectClause .= ",\n{$group_mapper->getColumnString('ug')} ";
            $joinClause .= "\nRIGHT JOIN {$this->getJoinSql('groups', 'c', 'ug')} ";
        }
        if($getCourseSpec){
            $selectClause .= ",\n{$courseSpecMapper->getColumnString('cs')} ";
            $joinClause .= "\nLEFT JOIN {$this->getJoinSql('course_specification', 'c', 'cs')} ";
        }
        if($getUnitSpec){
            $selectClause .= ",\n{$unitSpecMapper->getColumnString('us')} ";
            $joinClause .= "\nLEFT JOIN {$this->getJoinSql('unit_specification', 'c', 'us')} ";
        }

        $query = "
            SELECT $selectClause
            FROM {$this->getTable()} c
            $joinClause
			WHERE (
			    c.name LIKE :nameLike
			    OR cc.code LIKE :codeLike
            ) AND c.deleted_at IS NULL
        ";

        /* Don't know why codeLike contained / and not a trailing %
         */
        $params = array(
            'nameLike' => "%{$this->database->escapeForLike($keyword)}%",
            'codeLike' => "%{$this->database->escapeForLike($keyword)}%",
        );

        $statement = $this->database->prepare($query);
        $statement->execute($params);

        /** @var Model[] $folderCache */
        $folderCache = $this->createCollection();

        // No need to hydrate groups
        foreach ($statement as $row) {
            $folderId = $row['c.id'];
            if(!$folderCache[$folderId] instanceof Model){
                $folder = $this->hydrate($row, 'c');

                if($getCourseSpec){
                    $courseSpec = $courseSpecMapper->hydrate($row, 'cs');
                    if($courseSpec->getId()){
                        $folder->setCourseSpecification($courseSpec);
                    }
                }

                if($getUnitSpec){
                    $unitSpec = $unitSpecMapper->hydrate($row, 'us');
                    if($unitSpec->getId()){
                        $folder->setUnitSpecification($unitSpec);
                    }
                }
                $folderCache[$folderId] = $folder;
            }
            $folder = $folderCache[$folderId];

            $folderCode = $code_mapper->hydrate($row, 'cc');
            $folderCode->setFolder($folder);
            $folder->getCodes()->add($folderCode);
        }

        return $folderCache->sortBy('name', 'ASC');
    }

    /**
     * Helper method for hydrating a result from a query created by getFindQuery().
     *
     * @param  PDOStatement $statement
     * @throws MapperException if statement returned no category
     * @return Collection|Model[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $code_mapper = $this->getJoinMapper('codes');

        /** @var HomepageConfigMapper $homepageConfig_mapper */
        $homepageConfig_mapper = $this->getJoinMapper('homepages');
        $group_mapper = $this->getJoinMapper('groups');
        $courseSpecMapper = $this->getJoinMapper('course_specification');
        $unitSpecMapper = $this->getJoinMapper('unit_specification');


        /** @var Model[] $categories */
        $categories = [];

        $addedClassCodes      = [];
        $addedCurriculumNodes = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {

            $testId = $row['id'];

            // create if not exists
            if (!array_key_exists($testId, $categories)) {
                $category = $categories[$testId] = $this->hydrate($row);

                /** @var HomepageConfig $homepageConfig */
                $homepageConfig = $homepageConfig_mapper->hydrate($row, 'hp');

                /** @var Group $group */
                $group = $group_mapper->hydrate($row, 'ug');

                // set 1:1 objects
                $category->setHomepage($homepageConfig);
                $category->setGroup($group);

                $courseSpec = $courseSpecMapper->hydrate($row, 'cs');
                if($courseSpec->getId()){
                    $category->setCourseSpecification($courseSpec);
                }

                $unitSpec = $unitSpecMapper->hydrate($row, 'us');
                if($unitSpec->getId()){
                    $category->setUnitSpecification($unitSpec);
                }
            }

            // Hydrate class codes (avoiding duplicates) and cache
            $code = $code_mapper->hydrate($row, 'cc');

            if ($code->getFolder()->getId()) {
                $addedClassCodes[$testId][$code->getCode()] = $code;
            }

            // Hydrate curriculum node codes (avoiding duplicates) and cache
            // BTW fetching multiple 1-M relations in a single query
            // is how you get ants!
            /** @var Node\Model $curriculumNode */
            /** @var Node\Mapper $joinMapper */
            $joinMapper = $this->getJoinMapper('curriculum_node_for_category');
            $curriculumNode = $joinMapper->hydrateWithRelatedJoins($row, 'cn', 'cna', 'cnas', 'cnl', 'cnls');
            if ($curriculumNode->getId()) {
                $addedCurriculumNodes[$testId][$curriculumNode->getId()] = $curriculumNode;
            }
        }

        // set n:1 and n:n objects
        foreach ($categories as $catId => $category) {
            if (!empty($addedClassCodes[$catId])) {
                foreach ($addedClassCodes[$catId] as $code) {
                    $category->addCode($code);
                }
            }
            if (!empty($addedCurriculumNodes[$catId])) {
                foreach ($addedCurriculumNodes[$catId] as $curriculumNode) {
                    $category->addCurriculumNode($curriculumNode);
                }
            }
        }

        return $this->createCollection($categories);
    }

    /**
     * Finds a single category based on the category code passed in.
     * Does not find codes that have been deleted
     *
     * @param  string $code
     * @param  string $campusCode If provided, use this campus code in the query.
     * @throws MapperException if statement returned no category
     * @return Model
     */
    public function findByCode($code, $campusCode = '')
    {
        // Retrieve all codes for category, not just $code
        $query = "
            {$this->getSelectString()}
            {$this->getFromString()}
            WHERE folder.deleted_at IS NULL
        ";
        $params = [];
        if (strlen($campusCode) === 0) {
            $query .= ' AND (cc.code = :code OR cc.code LIKE :codeLike)';
            $params['code']     = $code;
            $params['codeLike'] = '%/' . $this->database->escapeForLike($code);
        } else {
            $query .= ' AND (cc.code = :code OR cc.code = :fullCode)';
            $params['code']     = $code;
            $params['fullCode'] = $campusCode . '/' . $code;
        }
        $statement = $this->database->prepare($query);
        $statement->execute($params);
        $model = $this->hydrateSelect($statement)->first();

        if (!$model instanceof Model) {
            throw new MapperException("Instance of object not found");
        }

        // Re-load codes into the model,
        // because the above query won't fetch codes for this category that
        // don't match the above where part.
        $model->setCodes(
            $this->getJoinMapper('codes')->findByColumn('folder_id', $model->getId())
        );
        return $model;
    }

    /**
     * Finds all folders for the given subject list.
     *
     * Designed to work with the output from `\External_User::getSubjectList`.
     *
     * @param array $subjectList Contains "code" and "campusCode" keys
     * @return Collection|Model[]
     */
    public function findAllBySubjectList($subjectList)
    {
        $whereParts = [];
        $params     = [];
        $i          = 0;

        // Build the set of where parts for this subject list
        foreach (array_values($subjectList) as $subject) {
            $code       = $subject['code'];
            $campusCode = $subject['campusCode'];

            $whereParts[]        = "cc.code = :code_{$i}";
            $params["code_{$i}"] = $code;
            if (strlen($campusCode) === 0) {
                $whereParts[]             = "cc.code LIKE :fullCode_{$i}";
                $params[":fullCode_{$i}"] = '%/' . $this->database->escapeForLike($code);
            } else {
                $whereParts[]             = "cc.code = :fullCode_{$i}";
                $params[":fullCode_{$i}"] = $campusCode . '/' . $code;
            }

            $i++;
        }

        // Abort if no subjects
        if (empty($whereParts)) {
            return $this->createCollection();
        }

        // Query, hydrate and return
        $where = implode(' OR ', $whereParts);
        $query = "
            {$this->getSelectString()}
            {$this->getFromString()}
            WHERE folder.deleted_at IS NULL
            AND ($where)
        ";

        $statement = $this->database->prepare($query);
        $statement->execute($params);

        $result = $this->hydrateSelect($statement);

        // Reload codes for retrieved folders.
        // The above query will not have fetched any codes that weren't in $subjectList.
        $folderCodes = $this->getJoinMapper('codes')
                            ->findByColumn('folder_id', $result->getColumnValues('id'))
                            ->groupByCallback(function (Code\Model $code) {
                                return $code->getFolder()->getId();
                            });
        foreach ($result as $folder) {
            if (isset($folderCodes[$folder->getId()])) {
                $folder->setCodes($folderCodes[$folder->getId()]);
            }
        }

        return $result;
    }

    /**
     * Finds a list of category codes based on an archived category code.
     *
     * Expects the name of the category contains $code between [] at the end.
     *
     * @param  string $code
     * @throws MapperException if statement returned no category
     * @return Collection|Model[]
     */
    public function findAllByArchivedCode($code)
    {
        // Don't need no category codes here
        $query = "
            {$this->getSelectString()}
            {$this->getFromString()}
            WHERE {$this->getTable()}.name LIKE :code
        ";
        $statement = $this->database->prepare($query);
        $statement->execute(
            array(
                'code' => '%[%' . $this->database->escapeForLike($code) . '%]',
            )
        );
        return $this->hydrateList($statement);
    }


    /**
     * @param int $id
     * @return Model
     */
    public function findById($id)
    {
        // special case: 0 == root, but not in db
        // TODO localise setName()
        if ($id == 0) {
            return $this->createModel()
                        ->setId(0)
                        ->setName('Top Level')
                        ->setParent($this->createModel()->setId('#'));
        }

        return parent::findById($id);
    }


    /**
     * Port of \Group->involvedInCategoryList() method.
     *
     * @param User $user The user to search for.
     * @param bool $get_subjects If false, subjects aren't retrieved.
     * @param int $access_level Defaults to UserAccess::ACCESS_READ, but may be
     *                           any access level.
     * @param bool $exactAccess If true, $access_level must be exact
     *
     * @return \Schoolbox\Collection|Model[]
     */
    public function findForUser(
        User $user,
        $get_subjects = true,
        $access_level = UserAccess::ACCESS_READ,
        $exactAccess = false
    ) {
        $query = "
			SELECT
				{$this->getColumnString('c')},
				{$this->getJoinColumnString('homepages', 'h')},
				{$this->getJoinColumnString('codes', 'cc')}
			FROM {$this->getTable()} c
			LEFT JOIN userAccess a ON c.id=a.sid AND a.service=:service
			LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}
			LEFT JOIN {$this->getJoinSql('homepages', 'c', 'h')}
			WHERE c.deleted_at IS NULL AND a.uid = :user
		";
        if ($exactAccess) {
            $query .= ' AND a.type = :access';
        } else {
            $query .= ' AND a.type >= :access';
        }
        $params = array(
            'user'    => $user->getId(),
            'service' => UserAccess::SERVICE_HOMEPAGE,
            'access'  => $access_level,
        );

        //
        // If getting subjects - get the external user mapper and query that.
        if ($get_subjects) {
            $external_user = $this->getExternalUser();
            if ($external_user instanceof External_UserInterface) {
                $subject_list = $external_user->getSubjectList($user);
                if (is_array($subject_list) && !empty($subject_list)) {
                    $category_codes = array_map(
                        function ($row) {
                            return trim($row['code']);
                        }, $subject_list
                    );
                    if (!empty($category_codes)) {
                        $query .= ' OR cc.code IN ' . $this->getArrayPlaceholders($category_codes, 'code');
                        $params = array_merge($params, $this->getArrayParameters($category_codes, 'code'));
                        //
                        // Allow search of codes with a campus prefix.
                        $category_like_codes = array_map(
                            function ($code) {
                                return '%/' . $code;
                            },
                            $category_codes
                        );
                        foreach ($category_like_codes as $i => $like_code) {
                            $query .= ' OR cc.code LIKE :like_code_' . $i;
                            $params['like_code_' . $i] = $like_code;
                        }
                    }
                }
            }
        }

        $statement = $this->database->prepare($query);
        $statement->execute($params);

        /** @var Collection|Model[] $collection */
        $collection = new Collection();
        foreach ($statement as $row) {
            $id = $row['c.id'];
            if (!isset($collection[$id])) {
                $collection[$id] = $this->hydrate(
                    $row,
                    'c',
                    function ($mapper, $model, $row) {
                        /**
                         * @var self $mapper
                         * @var Model $model
                         * @var array $row
                         */
                        $model->setHomepage($mapper->hydrateJoin($row, 'homepages', 'h'));
                        return $model;
                    }
                );
            }
            $code           = $row['cc.code'];
            $codeCollection = $collection[$id]->getCodes();
            if (!isset($codeCollection[$code])) {
                $codeCollection[$code] = $this->hydrateJoin($row, 'codes', 'cc');
            }
        }
        $collection->reindex();
        return $collection;
    }

    /**
     * @param  \External_UserInterface $external_user
     *
     * @todo Have this be set up automatically if at all possible
     *
     * @return $this
     */
    public function setExternalUser($external_user)
    {
        $this->external_user = $external_user;
        return $this;
    }

    /**
     * @return \External_UserInterface
     */
    public function getExternalUser()
    {
        // JIT-instantiation (is that really the way it should be?)
        if (!$this->external_user instanceof External_UserInterface) {
            $this->external_user = new \External_User();
        }
        return $this->external_user;
    }

    /**
     * Find ancestry cheaply
     *
     * @param Model $folder
     * @param Model $stopFolder
     * @return Collection
     */
    public function findParentsCheaply(Model $folder, Model $stopFolder)
    {
        return $this->findParents($folder, $stopFolder, true);
    }

    /**
     * Gets a collection of parents for the given category, starting from the
     * topmost parent first.
     *
     * @param  Model $category
     * @param  Model $stop_category If supplied, the parent list is truncated
     *                                 above this category.
     * @param bool $cheaply If true, use the findCheaplyById() for speed
     * @return Collection
     */
    public function findParents(Model $category, Model $stop_category = null, $cheaply = false)
    {
        /** @var Model[] $stack */
        $stack = array($category);
        $count = 0;

        $stop_id = null;
        if ($stop_category instanceof Model) {
            $stop_id = $stop_category->getId();
        }

        // Keep a record of ancestry
        $ancestry = array();
        if ($category->getId()) {
            $ancestry[$category->getId()] = true;
        }

        // Get the parent of the first item in the stack, and add it to the top
        // of the stack.
        while ($count++ < 50) {
            $parent = $stack[0]->getParent();
            if (!($parent instanceof Model)) {
                break;
            }
            $parent_id = $parent->getId();
            if (!$parent_id || $parent_id == '#' || (!empty($stop_id) && $stop_id == $parent_id)) {
                break;
            }

            // This category is its own parent somehow. That shouldn't happen.
            if (array_key_exists($parent_id, $ancestry)) {
                throw new \OverflowException(
                    'Folder "'.$category->getId().'" is its own parent somehow'
                );
            }
            $ancestry[$parent_id] = true;

            /* Cheaply or not?
             */
            if ($cheaply) {
                $node = $this->findCheaplyById($parent_id);
            }
            else {
                $node = $this->findById($parent_id);
            }

            array_unshift($stack, $node);
        }

        // Remove the current category from the parent list first.
        array_pop($stack);
        return new Collection($stack);
    }

    /**
     * Recursive delete of a category and its contents
     *
     * @param Model $category
     */
    public function recursiveDelete(Model $category)
    {
        $groupHandler = new \Group();

        if ($groupHandler->getPermission($category->getId()) < WRITE_ACCESS) {
            error(6);
        }

        $allChildren = $this->findByColumn('parent', $category->getId());

        /* Recurse into child folders
         */
        foreach ($allChildren as $currentChild) {
            $this->recursiveDelete($currentChild);
        }

        /* Soft delete all files
         */
        $fileMapper = new File\Mapper();
        $allFiles = $fileMapper->findByColumn('category', $category->getId());

        foreach ($allFiles as $currentFile) {
            $fileMapper->delete($currentFile);
        }

        /* Soft delete all links
         */
        $linkMapper = new LinkMapper();
        $allLinks = $linkMapper->findByColumn('category', $category->getId());

        foreach ($allLinks as $currentLink) {
            $linkMapper->delete($currentLink);
        }

        /* Soft delete all category codes
         */
        $codeMapper = new Code\Mapper();
        $allCodes = $codeMapper->findForCategory($category->getId());

        foreach ($allCodes as $currentCode) {
            $codeMapper->delete($currentCode);
        }

        /* Delete folder
         * This used to be optional, but it was always used in this context
         *
         * This also just marks the category as deleted, and leaves homepage
         * configs, components, groups etc. untouched
         */
        $this->delete($category);
    }


    /**
     * Return the folders contained within the folder with the given ID
     *
     * @see findByParent()
     * @param int $nodeId
     * @return Collection|IFolder[]
     */
    public function findByParentId($nodeId)
    {
        return $this->findByParent($nodeId);
    }

    /**
     * Creates a breadcrumb of a category's hierarchical navigation
     * Optionally include the $category as the final crumb
     *
     * @param Model $category
     * @param bool $includeCategory
     * @return Collection
     */
    public function breadcrumb(Model $category, $includeCategory = false)
    {
        $breadcrumb = new Collection();

        if ($includeCategory) {
            $breadcrumb->add($category);
        }

        $parentId = $category->getParent()->getId();

        /* get the breadcrumb back up to the root directory
         */
        $parentFolders = [];
        while ($parentId != 0) {
            try {
                $nextCategory = $this->findCheaplyById($category->getParent()->getId());
                $breadcrumb->prepend($nextCategory);

                $category = $nextCategory;

                $parentId = $nextCategory->getParent()->getId();
                if (isset($parentFolders[$parentId])) {
                    // I'm my own parent - that's just crazy
                    break;
                }
                $parentFolders[$parentId] = true;
            } catch (\Exception $e) {
                // I don't have any parents - abort
                break;
            }
        }

        return $breadcrumb;
    }

    /**
     * A budget version of findById
     *
     * This only gives you the id, name and permission-inherit of the folder
     * This was used to cheaply manufacture breadcrumb trails
     *
     * @param $folderId
     * @param array $options
     * @return \Schoolbox\Resource\Folder\Model
     * @throws MapperException
     */
    public function findCheaplyById($folderId, $options = array()) {

        // special case: 0 == root, but not in db
        if ($folderId == 0) {
            return $this->createModel()
                ->setId(0)
                ->setName('Top Level')
                ->setParent($this->createModel()->setId('#'));
        }

        $query = "
            SELECT id, name, parent, inherit
            FROM `{$this->getTable()}` c
            WHERE id = :folderId
        ";

        if(empty($options['deleted']) || !$options['deleted']){
            $query .= "
                AND deleted_at IS NULL
            ";
        }

        $statement = $this->database->prepare($query);
        $statement->execute(array(
            'folderId' => $folderId
        ));

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            $folder = $this->hydrate($row);

            $parent = $this->createModel();
            $parent->setId($folder->getParent()->getId());

            $folder->setParent($parent);

            return $folder;
        } else {
            throw new MapperException("Instance of object not found");
        }
    }

    /**
     * Performs a search with the given parameters, and returns a Collection of the resultant set.
     *
     * This also returns a count of all the items found in the search, not limited
     * by params
     *
     * @param User $user
     * @param string $keyword
     * @param int $offset
     * @param int $limit
     * @param string $sortProperty
     * @param string $sortDirection
     * @return Result
     */
    public function search($user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT, $sortProperty = '', $sortDirection = 'DESC')
    {
        if ($limit == 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $searcher = new Result();
        $searcher->search($user, $this, $keyword, $sortProperty, $sortDirection, $offset, $limit);

        return $searcher;
    }

    /**
     * Finds the nearest non-inheriting ancestral node (NNIAN)
     *
     * -_-_-_-_-_-_-_,------,
     * _-_-_-_-_-_-_-|   /\_/\
     * -_-_-_-_-_-_-~|__( ^ .^)
     * // _-_-_-_-_-_-_-""  ""
     *
     * @param Model $folder
     * @param int $iteration
     * @return Model
     * @throws MapperException
     */
    public function findNearestNonInheritingAncestralNode(Model $folder, $iteration = 0)
    {
        // Check cache first

        if (isset($this->cacheNnian[$folder->getId()])) {
            return $this->cacheNnian[$folder->getId()];
        }

        // If we've recursed too much error out

        if ($iteration > $this->getMaxRecursionDepth()) {
            throw new MapperException("Recursion limit reached");
        }

        // Check if the node inherits permissions

        if ($folder->getInherit())
        {
            // Just another interim node, store its ID so we can cache it for later calls

            $this->interimNodes[] = $folder->getId();

            // Make sure we aren't hitting the root
            /** @var Model $parent */
            $parent = $folder->getParent();
            if ($parent->isRootNode())
            {
                // Got to the top- somethings wrong with the data in the database as this should not be possible
                error(2);
            }

            // Round we go again

            $parent = $this->findCheaplyById($folder->getParent()->getId(), ['deleted' => true]);
            $result = $this->findNearestNonInheritingAncestralNode($parent, $iteration++);
        }
        else
        {
            // The node doesn't inherit, we found what we were looking for! Cache it

            $this->cacheNnian[$folder->getId()] = $folder;

            // Cache any interim nodes we traversed as well

            foreach ($this->interimNodes as $id) {
                $this->cacheNnian[$id] = $folder;
            }

            // Clear out the array

            $this->interimNodes = array();

            return $folder;
        }

        return $result;
    }

    /**
     * Decide whether inheritance value we provided activates
     * Folder Inheritance
     *
     * @param $value
     * @return int
     */
    public function decideInheritance($value)
    {
        if ($value == 1) {
            return self::INHERIT_ON;
        }

        return self::INHERIT_OFF;
    }

    /* Getters/Setters
     */

    /**
     * Get max recursion depth
     *
     * @return int
     */
    protected function getMaxRecursionDepth()
    {
        return $this->maxRecursionDepth;
    }

    /**
     * Set max recursion depth
     *
     * @param int $maxRecursionDepth
     */
    protected function setMaxRecursionDepth($maxRecursionDepth)
    {
        $this->maxRecursionDepth = $maxRecursionDepth;
    }

    /**
     * Delete folder and children
     *
     * @param int $id
     * @param \Schoolbox\User $deletor
     * @throws SchoolboxException
     */
    public function deleteFolder($id, $deletor)
    {
        // Grab handle to mapper as we'll reuse it

        $folderMapper = FolderMapper::getInstance();

        try
        {
            // Find the folder object

            $folder = $folderMapper->findById($id);

            // Make sure user has write permission to the folder

            if (Access::getInstance()->getFolderAccess($deletor, [$id]) >= WRITE_ACCESS) {

                // If the folder has children, then we will only allow superusers to delete it

                if ($folderMapper->countByColumn('parent', $id))
                {
                    if (!$deletor->isSuperuser())
                    {
                        throw new SchoolboxException('Access Denied');
                    }
                }

                // Deletes all contents of this folder and all of its children.

                $folderMapper->recursiveDelete($folder);
            }
        }
        catch (\Exception $e)
        {
            // Permission denied or something wasn't found, do nothing and just return. The folder won't have
            // been deleted.
        }
    }

    /**
     * Move folder to new parent with permission check
     *
     * @param int $source
     * @param int $destination
     * @param \Schoolbox\User $mover
     * @throws SchoolboxException
     * @throws MapperException
     */
    public function moveFolder($source, $destination, $mover)
    {
        $errorMapper = ErrorMapper::getInstance();

        // Check if there is nothing to do

        // I AM REASONABLY SURE THIS SHOULD NOT JUST EXIT OUT
        if ($source == $destination) {
            return;
        }

        // Grab folder object

        $folderMapper = FolderMapper::getInstance();

        $movedFolder  = $folderMapper->findById($source);
        $parentFolder = $folderMapper->findById($destination);

        // Check folder access

        if (Access::getInstance()->getFolderAccess($mover, [$source]) < WRITE_ACCESS ||
            Access::getInstance()->getFolderAccess($mover, [$destination]) < WRITE_ACCESS ||
            Access::getInstance()->getFolderAccess($mover, [$movedFolder->getParent()->getId()]) < WRITE_ACCESS
        ) {
            throw $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE);
        }

        // Make sure the moved folder is not going to be its own ancestor

        foreach ($folderMapper->findParents($parentFolder) as $ancestor) {
            if ($ancestor->getId() == $movedFolder->getId()) {
                /** @var SchoolboxException $e */
                throw $errorMapper->generateException(ErrorMapper::ERROR_DATABASE, $e);
            }
        }

        $folderMapper->save($movedFolder->setParent($parentFolder));
    }

    /**
     * Return a list of folders that the student has submitted work to in a given term
     * @param User $student
     * @param Term $term
     * @return Collection|Model[]
     */
    public function findFoldersWithSubmissions(User $student, Term $term)
    {
        $code_mapper = $this->getJoinMapper('codes');
        $homepageConfig_mapper = $this->getJoinMapper('homepages');
        $group_mapper = $this->getJoinMapper('groups');

        $query = "
            SELECT
                {$this->getColumnString('c')},
                {$code_mapper->getColumnString('cc')},
                {$homepageConfig_mapper->getColumnString('hp')},
                {$group_mapper->getColumnString('ug')}
            FROM {$this->getTable()} c
            LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}
            LEFT JOIN {$this->getJoinSql('homepages', 'c', 'hp')}
            LEFT JOIN {$this->getJoinSql('groups', 'c', 'ug')}
            WHERE c.deleted_at IS NULL AND c.id IN 
            ( 
              SELECT DISTINCT COALESCE(`af`.`folder_id`, `ci`.`folder_id`) AS `folder_id`
              FROM `assessment` AS `a`
              LEFT JOIN `assessment_folder` AS `af` ON `af`.`assessment_id` = `a`.`id`
              LEFT JOIN `assessment_course_unit_implementation` AS `acui` ON `a`.`id` = `acui`.`assessment_id`
              LEFT JOIN `course_unit_implementation` AS `cui` ON `acui`.`course_unit_implementation_id` = `cui`.`id`
              LEFT JOIN `course_implementation` AS `ci` ON `cui`.`course_implementation_id` = `ci`.`id`
              LEFT JOIN `task` AS `t` ON `a`.`id` = `t`.`assessment_id`
              LEFT JOIN `assess` AS `q` ON `q`.`assessment_id` = `a`.`id`              
              LEFT JOIN `submission_box` AS `d` ON `a`.`id` = `d`.`assessment_id`
              LEFT JOIN `task_completion` AS `tc` ON `t`.`id` = `tc`.`task_id`
              LEFT JOIN `assess_attempt` AS `qa` ON `q`.`assess_id` = `qa`.`assess_id`
              LEFT JOIN `submission_return` AS `sr` ON `sr`.`box_id` = `d`.id
              WHERE 
                (`tc`.`student_id` = :studentId OR `sr`.`owner` = :studentId OR `qa`.`user_id` = :studentId) AND
                (`a`.`due_date` BETWEEN :startDate AND :endDate)
            )
            ORDER BY c.name
        ";

        $statement = $this->database->prepare($query);

        $statement->execute([
            ':studentId' => $student->getId(),
            ':startDate' => $term->getStart(),
            ':endDate' => $term->getActualFinish()
        ]);

        $collection = new Collection();
        $category = null;

        foreach ($statement as $row) {
            $category = $this->hydrate($row, 'c');

            /** @var HomepageConfig $homepageConfig */
            $homepageConfig = $homepageConfig_mapper->hydrate($row, 'hp');
            $category->setHomepage($homepageConfig);

            /** @var \Schoolbox\Resource\Group $group */
            $group = $group_mapper->hydrate($row, 'ug');
            $category->setGroup($group);

            /** @var \Schoolbox\Resource\Folder\Code\Model $code */
            $code = $code_mapper->hydrate($row, 'cc');
            if ($code->getFolder()->getId()) {
                $category->addCode($code);
            }
            $collection->add($category);

            $category->loadTeachers();
        }

        return $collection;
    }

    /**
     * Apply inherited permissions checkbox DOWN the tree
     *
     * @param Model $model
     * @throws \Exception
     */
    public function recurseInheritance(Model $model)
    {
        $folders = $this->findAllChildren($model->getId(), true);
        foreach ($folders as $folder)

        {
            $folder->setInherit($model->getInherit());
            $this->save($folder);
            $this->recurseInheritance($folder);
        }
    }

    /**
     * @return Ancestry
     */
    public function getAncestry()
    {
        return $this->ancestry;
    }
}
