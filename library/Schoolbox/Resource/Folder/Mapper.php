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

use External_UserInterface;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\File;
use Schoolbox\Resource\Folder\Search\Result;
use Schoolbox\Resource\HomepageConfig;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\User;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Error\Mapper as ErrorMapper;
use Zend\Json\Server\Error;

/**
 * Class Mapper
 *
 * SBFS Folder Mapper. Maps category table to SBFS folder objects.
 *
 * @package Schoolbox\Resource\Folder
 * @method Model createModel()
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends \Schoolbox\Mapper implements ILocator, Searchable
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

    /** Array of cached NNIAN nodes
     *
     * @var array
     */
    protected $cacheNnian = array();
    protected $interimNodes = array();

    protected function mapping()
    {
        $this->hasModel('\\Schoolbox\\Resource\\Folder\\Model');
        $this->hasTable('category');
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'name',
                'property' => 'name',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'inherit',
                'property' => 'inherit',
                'type'     => self::TYPE_BOOLEAN,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'parent',
                'property' => 'parent',
                'type'     => self::TYPE_MODEL,
                'join'     => 'parent',
            )
        );

        //
        // Joins:
        $this->hasJoin(
            'codes', array(
                'multiple'  => true,
                'mapper'    => '\\Schoolbox\\Resource\\Folder\\Code\\Mapper',
                'this_key'  => 'id',
                'other_key' => 'categoryID',
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

        //
        // Parent object: has reference to itself as a mapper.
        $this->hasJoin(
            'parent', array(
                'multiple'  => true,
                'mapper'    => $this,
                'this_key'  => 'category',
                'other_key' => 'id',
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

        $this->hasMap(
            array(
                'column'   => 'deleted_at',
                'property' => 'deletedAt',
                'type'     => self::TYPE_DATETIME,
            )
        );


        $this->registerCallback(self::CALLBACK_PRESAVE, array($this, self::CALLBACK_PRESAVE));
        $this->registerCallback(self::CALLBACK_POSTSAVE, array($this, self::CALLBACK_POSTSAVE));
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'saveHomepageConfig']);
        $this->registerCallback(self::CALLBACK_POSTDELETE, array($this, 'hardDeleteCategoryCodes'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteCategoryCodes'));

        $this->hasSoftDeleteDateField('deleted_at');
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
    public function findImages(Model $category, $limit = 0, $excludeWork = false)
    {
        $fileMapper = $this->getJoinMapper('files');

        $limit = (int)$limit;
        
        $opts = [ 'filter' => 'image' ];
        
        if ($limit) {
            $opts['size'] = $limit;
        }

        return $fileMapper->findForFolders([ $category->getId() ], $opts);
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
                return $code->getCategory()->getId() != $id;
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

        // if no codes populated, no need to do anything
        if (!$model->getCodes()->count()) {
            return true;
        }

        // first drop the previous code(s) connected to this category
        try {
            Code\Mapper::getInstance()->delete(
                Code\Mapper::getInstance()->findByColumn('categoryID', $model->getId())
            );
        } catch (\Exception $e) {
        }

        // bind the given codes to this category
        foreach ($model->getCodes() as $code) {
            $code->setCategory($model);
        }

        // persist the Code objects

        Code\Mapper::getInstance()->save($model->getCodes());

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
        $codeMapper->hardDelete($codeMapper->findByColumn('categoryID', $object->getId()));
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
        $codeMapper->softDelete($codeMapper->findByColumn('categoryID', $object->getId()));
    }

    /**
     * Get a list of categorys which belong to a particular parent
     *
     * TODO: This probably shouldn't get tasks and homepage configs
     * It should only get the children of $id and probably should be
     * established in Ancestry
     *
     * @param $id
     * @return Collection|Model[]
     * @throws \Schoolbox\Mapper\Exception
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

        $collection = new \Schoolbox\Collection();
        $category = null;

        foreach ($statement as $row) {
            $existingCat = $collection->filterBy('id', $row['c.id']);
            if ($existingCat->count()) {
                //Folder already exists in collection
                $code = $code_mapper->hydrate($row, 'cc');
                if ($code->getCategory()->getId()) {
                    $existingCat->first()->addCode($code);
                }
            } else {
                $category = $this->hydrate($row, 'c');
                if (isset($taskCounts[$row['c.id']])) {
                    $category->setTaskCount($taskCounts[$row['c.id']]);
                }
                $homepageConfig = $homepageConfig_mapper->hydrate($row, 'hp');
                $category->setHomepage($homepageConfig);

                $group = $group_mapper->hydrate($row, 'ug');
                $category->setGroup($group);

                $code = $code_mapper->hydrate($row, 'cc');
                if ($code->getCategory()->getId()) {
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

        $children = FolderMapper::findByColumn("parent", $id);

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
            $category->setCodes($this->getJoinMapper('codes')->findByColumn('categoryID', $category->getId()));
        }

        return $categories;
    }

    /**
     * Helper method for creating a search query with codes, homepage config and
     * group all built in.
     *
     * You can add a WHERE statement at the end if you want.
     *
     * @return string
     */
    protected function getFindQuery()
    {
        $code_mapper = $this->getJoinMapper('codes');
        $homepageConfig_mapper = $this->getJoinMapper('homepages');
        $group_mapper = $this->getJoinMapper('groups');
        return "
            SELECT
                {$this->getColumnString('c')},
                {$code_mapper->getColumnString('cc')},
                {$homepageConfig_mapper->getColumnString('hp')},
                {$group_mapper->getColumnString('ug')},
                {$this->getJoinColumnString('curriculum_node_for_category', 'cn')},
                {$this->getJoinColumnString('curriculum_node_for_category.level', 'cnl')},
                {$this->getJoinColumnString('curriculum_node_for_category.level.source', 'cnls')},
                {$this->getJoinColumnString('curriculum_node_for_category.area', 'cna')},
                {$this->getJoinColumnString('curriculum_node_for_category.area.source', 'cnas')}
            FROM {$this->getTable()} c
            LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}
            LEFT JOIN {$this->getJoinSql('homepages', 'c', 'hp')}
            LEFT JOIN {$this->getJoinSql('groups', 'c', 'ug')}
            LEFT JOIN {$this->getJoinSql('curriculum_node_for_category', 'c', 'cn')}
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
     * @return Model
     */
    public function searchByName($keyword, $userGroupsOnly = false) {
        $query = $this->getFindQuery();

        $query .= "
			WHERE (
			    c.name LIKE :nameLike
			    OR cc.code LIKE :codeLike
            )
        ";

        /* Filter result set further by only returning records that are also
		 * in the userGroups table
		 */
        if ($userGroupsOnly) {
            $query .= "
                AND ug.cid IS NOT NULL
            ";
        }

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

            $hashtable[$category->getId()] = $category;
        }

        return new Collection(array_values($hashtable));
    }

    /**
     * Helper method for hydrating a result from a query created by getFindQuery().
     *
     * @param  \PDOStatement $statement
     * @throws \Schoolbox\Mapper\Exception if statement returned no category
     * @return Model
     */
    protected function hydrateFromFindQuery($statement)
    {
        $code_mapper = $this->getJoinMapper('codes');
        $homepageConfig_mapper = $this->getJoinMapper('homepages');
        $group_mapper = $this->getJoinMapper('groups');
        $category = null;

        $addedCodes           = [];
        $addedCurriculumNodes = [];

        foreach ($statement as $row) {
            if (!$category instanceof $this->model) {
                $category = $this->hydrate($row, 'c');
                if (isset($row['c.taskCount'])) {
                    $category->setTaskCount($row['c.taskCount']);
                }
            }

            // Hydrate class codes (avoiding duplicates)
            $code = $code_mapper->hydrate($row, 'cc');
            if ($code->getCategory()->getId()) {
                $addedCodes[$code->getCode()] = $code;
            }

            $homepageConfig = $homepageConfig_mapper->hydrate($row, 'hp');
            $category->setHomepage($homepageConfig);

            $group = $group_mapper->hydrate($row, 'ug');
            $category->setGroup($group);

            // Hydrate curriculum node codes (avoiding duplicates)
            // BTW fetching multiple 1-M relations in a single query
            // is how you get ants!
            /** @var Node\Model $curriculumNode */
            $curriculumNode = $this->getJoinMapper('curriculum_node_for_category')
                ->hydrateWithRelatedJoins($row, 'cn', 'cna', 'cnas', 'cnl', 'cnls');
            if ($curriculumNode->getId()) {
                $addedCurriculumNodes[$curriculumNode->getId()] = $curriculumNode;
            }
        }
        if (!($category instanceof Model)) {
            throw new \Schoolbox\Mapper\Exception('Instance of object not found');
        }
        if (!empty($addedCodes)) {
            foreach ($addedCodes as $code) {
                $category->addCode($code);
            }
        }
        if (!empty($addedCurriculumNodes)) {
            foreach ($addedCurriculumNodes as $curriculumNode) {
                $category->addCurriculumNode($curriculumNode);
            }
        }

        $this->throwIfDeleted($category);
        return $category;
    }

    /**
     * Finds a single category based on the category code passed in.
     * Does not find codes that have been deleted
     *
     * @param  string $code
     * @throws \Schoolbox\Mapper\Exception if statement returned no category
     * @return Model
     */
    public function findByCode($code)
    {
        $query = $this->getFindQuery();
        // Retrieve all codes for category, not just $code
        $query .= "
            WHERE c.deleted_at IS NULL
            AND (cc.code = :code OR cc.code LIKE :codeLike)
        ";
        $statement = $this->database->prepare($query);
        $statement->execute(
            array(
                'code'     => $code,
                'codeLike' => '%/' . $this->database->quoteForLike($code),
            )
        );
        $model = $this->hydrateFromFindQuery($statement);

        // Re-load codes into the model,
        // because the above query won't fetch codes for this category that
        // don't match the above where part.
        $model->setCodes(
            $this->getJoinMapper('codes')->findByColumn('categoryID', $model->getId())
        );
        return $model;
    }

    /**
     * Finds a list of category codes based on an archived category code.
     *
     * Expects the name of the category contains $code between [] at the end.
     *
     * @param  string $code
     * @throws \Schoolbox\Mapper\Exception if statement returned no category
     * @return Collection|Model[]
     */
    public function findAllByArchivedCode($code)
    {
        // Don't need no category codes here
        $query = $this->getFindQuery();
        $query .= 'WHERE c.name LIKE :code';
        $statement = $this->database->prepare($query);
        $statement->execute(
            array(
                'code' => '%[%' . $this->database->quoteForLike($code) . '%]',
            )
        );
        return $this->hydrateList($statement, 'c');
    }

    /**
     * {@inheritdoc}
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

        $query = $this->getFindQuery();
        $query .= 'WHERE c.id=?';
        $statement = $this->database->prepare($query);
        $statement->execute(array($id));
        return $this->hydrateFromFindQuery($statement);
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
				{$this->getJoinColumnString('homepages', 'h')}
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
                            return $row['code'];
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

        $collection = new Collection();
        $addedIds   = array();
        foreach ($statement as $row) {
            if (!array_key_exists($row['c.id'], $addedIds)) {
                $addedIds[$row['c.id']] = true;
                $collection->add(
                    $this->hydrate(
                        $row,
                        'c',
                        function ($mapper, $model, $row) {
                            /**
                             * @var self $mapper
                             * @var Model $model
                             * @var array $row
                             */
                            $model->setHomepage($mapper->getJoinMapper('homepages')->hydrate($row, 'h'));
                            return $model;
                        }
                    )
                );
            }
        }
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
     * @return IFolder[]
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
        while ($parentId != 0) {
            try {
                $nextCategory = $this->findCheaplyById($category->getParent()->getId());
                $breadcrumb->prepend($nextCategory);

                $category = $nextCategory;

                $parentId = $nextCategory->getParent()->getId();
            }
            catch (\Exception $e) {
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
     * @return \Schoolbox\Resource\Folder\Model
     * @throws \Schoolbox\Mapper\Exception
     */
    public function findCheaplyById($folderId) {

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
            throw new \Schoolbox\Mapper\Exception("Instance of object not found");
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
     * @throws \Schoolbox\Mapper\Exception
     */
    public function findNearestNonInheritingAncestralNode(Model $folder, $iteration = 0)
    {
        // Check cache first

        if (isset($this->cacheNnian[$folder->getId()])) {
            return $this->cacheNnian[$folder->getId()];
        }

        // If we've recursed too much error out

        if ($iteration > $this->getMaxRecursionDepth()) {
            throw new \Schoolbox\Mapper\Exception("Recursion limit reached");
        }

        // Check if the node inherits permissions

        if ($folder->getInherit())
        {
            // Just another interim node, store its ID so we can cache it for later calls

            $this->interimNodes[] = $folder->getId();

            // Make sure we aren't hitting the root
            if ($folder->getParent()->isRootNode())
            {
                // Got to the top- somethings wrong with the data in the database as this should not be possible
                error(2);
            }

            // Round we go again

            $parent = $this->findCheaplyById($folder->getParent()->getId());
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
     * @param Int $id
     * @throws \Exception
     */
    public function deleteFolder($id)
    {
        // Grab handle to mapper as we'll reuse it

        $folderMapper = FolderMapper::getInstance();

        try
        {
            // Find the folder object

            $folder = $folderMapper->findById($id);

            // Make sure user has write permission to the folder

            if (Access::getInstance()->getFolderAccess($_SESSION["user"], [$id]) >= WRITE_ACCESS) {

                // If the folder has children, then we will only allow superusers to delete it

                if ($folderMapper->countByColumn('parent', $id))
                {
                    if (!$_SESSION["user"]->isSuperuser())
                    {
                        throw new \Exception('Access Denied');
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
     * @param Int $source
     * @param Int $destination
     * @throws \Exception
     */
    public function moveFolder($source, $destination)
    {
        $errorMapper = ErrorMapper::getInstance();

        // Check if there is nothing to do

        // I AM REASONABLY SURE THIS SHOULD NOT JUST EXIT OUT
        if ($source == $destination) {
            exit;
        }

        // Grab folder object

        $folderMapper = FolderMapper::getInstance();

        $movedFolder  = $folderMapper->findById($source);
        $parentFolder = $folderMapper->findById($destination);

        // Check folder access

        if (Access::getInstance()->getFolderAccess($_SESSION["user"], [$source]) < WRITE_ACCESS ||
            Access::getInstance()->getFolderAccess($_SESSION["user"], [$destination]) < WRITE_ACCESS ||
            Access::getInstance()->getFolderAccess($_SESSION["user"], [$movedFolder->getParent()]) < WRITE_ACCESS
        ) {
            $errorMapper->generateException(ErrorMapper::ERROR_PERM_CATEGORY_WRITE);
        }

        // Make sure the moved folder is not going to be its own ancestor

        foreach ($folderMapper->findParents($parentFolder) as $ancestor) {
            if ($ancestor->getId() == $movedFolder->getId()) {
                $errorMapper->generateException(ErrorMapper::ERROR_DATABASE, $e);
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
}
