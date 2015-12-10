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

    /**
     * Interface with external user database.
     *
     * @var External_UserInterface
     */
    protected $external_user;

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
        $code_mapper = $this->getJoinMapper('codes');
        $homepageConfig_mapper = $this->getJoinMapper('homepages');
        $group_mapper = $this->getJoinMapper('groups');

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

        $params = array(
            'nameLike' => "%{$this->database->quoteForLike($keyword)}%",
            'codeLike' => "%/{$this->database->quoteForLike($keyword)}",
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
     * Gets a collection of parents for the given category, starting from the
     * topmost parent first.
     *
     * @param  Model $category
     * @param  Model $stop_category If supplied, the parent list is truncated
     *                                 above this category.
     * @return Collection
     */
    public function findParents(Model $category, Model $stop_category = null)
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
            if (!$parent_id || (!empty($stop_id) && $stop_id == $parent_id)) {
                break;
            }

            // This category is its own parent somehow. That shouldn't happen.
            if (array_key_exists($parent_id, $ancestry)) {
                throw new \OverflowException(
                    'Folder "'.$category->getId().'" is its own parent somehow'
                );
            }
            $ancestry[$parent_id] = true;
            array_unshift($stack, $this->findById($parent_id));
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
     * @return \Schoolbox\Resource\Category
     */
    public function findCheaplyById($folderId) {
        $query = "
            SELECT id, name, parent
            FROM `{$this->getTable()}` c
            WHERE id = :folderId
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(array(
            'folderId' => $folderId
        ));

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        $folder = $this->hydrate($row);

        $parent = $this->createModel();
        $parent->setId($folder->getParent()->getId());

        $folder->setParent($parent);

        return $folder;
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
}
