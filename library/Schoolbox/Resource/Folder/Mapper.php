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
use Schoolbox\Mapper\Exception as MapperException;
use External_UserInterface;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\AbstractFolderMapper;
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
 * @method Model createModel()
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
     * Mapper constructor.
     */
    public function __construct() {
        $this->setGroupMapper(GroupMapper::getInstance());
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

        // Joins:
        $this->hasJoin(
            'codes', array(
                'multiple'  => true,
                'mapper'    => '\\Schoolbox\\Resource\\Folder\\Code\\Mapper',
                'this_key'  => 'id',
                'other_key' => 'folder_id',
            )
        );

        $this->hasJoin(
            'homepages', array(
                'multiple'  => false,
                'mapper'    => '\\Schoolbox\\Resource\\HomepageConfigMapper',
                'this_key'  => 'id',
                'other_key' => 'cid',
            )
        );

        $this->hasJoin(
            'files', array(
                'multiple'  => true,
                'mapper'    => '\\Schoolbox\\Resource\\File\\Mapper',
                'this_key'  => 'id',
                'other_key' => 'category',
            )
        );

        $this->hasJoin(
            'groups', array(
                'multiple'  => true,
                'mapper'    => '\\Schoolbox\\Resource\\GroupMapper',
                'this_key'  => 'id',
                'other_key' => 'cid',
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

        $this->registerCallback(self::CALLBACK_PRESAVE, array($this, self::CALLBACK_PRESAVE));
        $this->registerCallback(self::CALLBACK_POSTSAVE, array($this, self::CALLBACK_POSTSAVE));
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'saveHomepageConfig']);
        $this->registerCallback(self::CALLBACK_POSTDELETE, array($this, 'hardDeleteCategoryCodes'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteCategoryCodes'));

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
     * @param Model $category
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
     * @param Model $model
     * @return bool
     */
    public function preSave($model)
    {
        // iterate codes given; if any already exist, error 136
        $codeList = $model->getCodes()->getColumnValues('code');

        $collisions = Code\Mapper::getInstance()->findByColumn('code', $codeList);

        // retain any codes that do NOT match the given model
        $id = $model->getId();
        $collisions = $collisions->filterByCallback(
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
     * @param Model $model
     * @param Model $previous
     * @return bool
     */
    public function postSave($model, $previous = null)
    {
        // If we are inheriting permissions delete any previously created group perms
        if ($model->getInherit()) {
            $this->getGroupMapper()->deleteByColumn("cid", $model->getId());
        }

        // if no codes populated, no need to do anything
        if (!$model->getCodes()->count()) {
            return true;
        }

        // first drop the previous code(s) connected to this category
        try {
            Code\Mapper::getInstance()->delete(
                Code\Mapper::getInstance()->findByColumn('folder_id', $model->getId())
            );
        } catch (\Exception $e) {
        }

        // bind the given codes to this category
        foreach ($model->getCodes() as $code) {
            $code->setFolder($model);
        }

        // persist the Code objects
        Code\Mapper::getInstance()->save($model->getCodes());

        // remove any mutes that might exist on this subject (classes are not allowed to be muted)
        RerouteOverrideMapper::getInstance()->deleteByColumnList([
            'delivery'      => RoutePermission::MUTE,
            'msg_route_key' => "service.1.{$model->getId()}"
        ]);

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
     * TODO: This probably shouldn't get tasks and homepage configs
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

        // Please forgive me, I added a subquery for getting if a category
        // contains tasks
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
            WHERE c.deleted_at IS NULL AND c.parent=?
            ORDER BY c.name
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(array($id));

        // Get task count as separate result set
        $taskCountQuery = "
            SELECT
                c.id,
                COUNT(t.id) AS taskCount
            FROM {$this->getTable()} c
            LEFT JOIN {$this->getTable()} subc ON subc.parent = c.id AND subc.deleted_at IS NULL
            JOIN `task` t ON t.category_id = subc.id AND t.deleted_at IS NULL
            WHERE c.deleted_at IS NULL AND c.parent=?
            GROUP BY c.id
        ";
        $taskCountStatement = $this->database->prepare($taskCountQuery);
        $taskCountStatement->execute([$id]);
        $taskCounts = [];
        foreach ($taskCountStatement as $row) {
            $taskCounts[$row['id']] = $row['taskCount'];
        }

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
                if (isset($taskCounts[$row['c.id']])) {
                    $category->setTaskCount($taskCounts[$row['c.id']]);
                }

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

        return "
            SELECT
                {$this->getColumnString()},
                {$code_mapper->getColumnString('cc')},
                {$homepageConfig_mapper->getColumnString('hp')},
                {$group_mapper->getColumnString('ug')},
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
     * @param $keyword
     * @param bool $userGroupsOnly
     * @return Collection|Model[]
     */
    public function searchByName($keyword, $userGroupsOnly = false)
    {
        $code_mapper  = $this->getJoinMapper('codes');
        $group_mapper = $this->getJoinMapper('groups');

        $groupSelect  = $userGroupsOnly ? ", {$group_mapper->getColumnString('ug')}" : '';

        /* Filter result set further by only returning records that are also
         * in the userGroups table
         */
        $groupJoin    = $userGroupsOnly ? "RIGHT JOIN {$this->getJoinSql('groups', 'c', 'ug')}" : '';

        $query = "
            SELECT
                {$this->getColumnString('c')},
                {$code_mapper->getColumnString('cc')}
                $groupSelect
            FROM {$this->getTable()} c
            LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}
            $groupJoin
			WHERE (
			    c.name LIKE :nameLike
			    OR cc.code LIKE :codeLike
            ) AND c.deleted_at IS NULL
        ";

        /* Don't know why codeLike contained / and not a trailing %
         */
        $params = array(
            'nameLike' => "%{$this->database->quoteForLike($keyword)}%",
            'codeLike' => "%{$this->database->quoteForLike($keyword)}%",
        );

        $statement = $this->database->prepare($query);
        $statement->execute($params);

        $hashtable = array();

        /* Only need to return category records
         */
        foreach ($statement as $row) {
            $category = $this->hydrate($row, 'c');
            $category->setCodes(new Collection([ $code_mapper->hydrate($row, 'cc')->setFolder($category) ]));

            $hashtable[$category->getId()] = $category;
        }

        return new Collection(array_values($hashtable));
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

                if (isset($row['taskCount'])) {
                    $categories[$testId]->setTaskCount($row['taskCount']);
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
     * @throws MapperException if statement returned no category
     * @return Model
     */
    public function findByCode($code)
    {
        // Retrieve all codes for category, not just $code
        $query = "
            {$this->getSelectString()}
            {$this->getFromString()}
            WHERE folder.deleted_at IS NULL
            AND (cc.code = :code OR cc.code LIKE :codeLike)
        ";
        $statement = $this->database->prepare($query);
        $statement->execute(
            array(
                'code'     => $code,
                'codeLike' => '%/' . $this->database->quoteForLike($code),
            )
        );
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
                'code' => '%[%' . $this->database->quoteForLike($code) . '%]',
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
                        ->setName('Root')
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
     *
     * @return \Schoolbox\Collection|Model[]
     */
    public function findForUser(User $user, $get_subjects = true, $access_level = UserAccess::ACCESS_READ)
    {
        $query = "
			SELECT
				{$this->getColumnString('c')},
				{$this->getJoinColumnString('homepages', 'h')},
				{$this->getJoinColumnString('codes', 'cc')}
			FROM {$this->getTable()} c
			LEFT JOIN userAccess a ON c.id=a.sid AND a.service=:service
			LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}
			LEFT JOIN {$this->getJoinSql('homepages', 'c', 'h')}
			WHERE c.deleted_at IS NULL AND a.uid = :user AND a.type >= :access
		";
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
                $subject_list = $external_user->getSubjectList($user->getExternalId(), $user->getId());
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
     * This only gives you the id and name of the folder
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
                ->setName('Root')
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
     * @return Collection
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
     * @var GroupMapper
     */
    protected $groupMapper;

    /**
     * @return GroupMapper
     */
    public function getGroupMapper() {
        return $this->groupMapper;
    }

    /**
     * @param $groupMapper
     * @return $this
     */
    public function setGroupMapper($groupMapper) {
        $this->groupMapper = $groupMapper;
        return $this;
    }
}
