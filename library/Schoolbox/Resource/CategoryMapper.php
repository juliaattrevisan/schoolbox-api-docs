<?php
/**
 * Mapper for fetching category in SBFS.  These are also known as folders on the front end.
 * Categories are a heirachy tree that all other elements a linked with; pages, files, links, etc
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */


namespace Schoolbox\Resource;

use Schoolbox\Collection;
use Schoolbox\FolderInterface;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;
use \External_UserInterface;
use Schoolbox\Resource\Category;

class CategoryMapper extends Mapper implements FolderLocatorInterface
{

    /**
     * Interface with external user database.
     *
     * @var External_UserInterface
     */
    protected $external_user;

    protected function mapping()
    {
        $this->hasModel('\\Schoolbox\\Resource\\Category');
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
                'mapper'    => '\\Schoolbox\\Resource\\CategoryCodeMapper',
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
                'mapper'    => '\\Schoolbox\\Resource\\FileMapper',
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

        $this->hasMap(
            array(
                'column'   => 'deleted_at',
                'property' => 'deletedAt',
                'type'     => self::TYPE_DATETIME,
            )
        );


        $this->registerCallback(self::CALLBACK_PRESAVE, array($this, self::CALLBACK_PRESAVE));
        $this->registerCallback(self::CALLBACK_POSTSAVE, array($this, self::CALLBACK_POSTSAVE));
        $this->registerCallback(self::CALLBACK_POSTDELETE, array($this, 'hardDeleteCategoryCodes'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteCategoryCodes'));

        $this->hasSoftDeleteDateField('deleted_at');
    }

    /**
     * Find all images for a category
     *
     * @param $categoryId
     * @param int $limit
     * @param bool $excludeWork
     * @return mixed
     */
    public function findImages($categoryId, $limit = 0, $excludeWork = false)
    {
        $fileMapper = $this->getJoinMapper('files');

        $limit = (int)$limit;

        $query = "SELECT
					{$fileMapper->getColumnString('f')}
				  FROM file f
				  WHERE category = :categoryId
				  AND INSTR(mime_type, 'image/')
				  AND deleted = '0'
				  AND hidden = '0'
				  ORDER BY submitted";

        if ($limit > 0) {
            $query .= " LIMIT 0, {$limit}";
        }

        $params = array(
            'categoryId' => $categoryId,
        );

        $statement = $this->database->prepare($query);
        $statement->execute($params);

        $collection = new \Schoolbox\Collection();

        foreach ($statement as $row) {
            $collection->add($fileMapper->hydrate($row, 'f'));
        }

        return $collection;
    }

    /**
     * @param Category $model
     * @return bool
     */
    public function preSave($model)
    {

        // iterate codes given; if any already exist, error 136
        $codeList = $model->getCodes()->getColumnValues('code');


        $collisions = CategoryCodeMapper::getInstance()->findByColumn('code', $codeList);

        // retain any codes that do NOT match the given model
        $id = $model->getId();
        $collisions = $collisions->filterByCallback(
            function ($code) use ($id) {
                /** @var CategoryCode $code */
                return $code->getCategory()->getId() != $id;
            }
        );

        if ($collisions->count()) {
            error(136);
        }

        // persist Category object
        return true;
    }

    /**
     * @param Category $model
     * @param Category $previous
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
            CategoryCodeMapper::getInstance()->delete(
                CategoryCodeMapper::getInstance()->findByColumn('categoryID', $model->getId())
            );
        } catch (\Exception $e) {
        }

        // bind the given codes to this category
        foreach ($model->getCodes() as $code) {
            $code->setCategory($model);
        }

        // persist the Code objects

        CategoryCodeMapper::getInstance()->save($model->getCodes());

        return true;
    }

    /**
     * Hard deletes all category codes that belong to this object.
     *
     * Called as a post delete callback.
     *
     * @param Category $object
     */
    public function hardDeleteCategoryCodes(Category $object)
    {
        $codeMapper = $this->getJoinMapper('codes');
        $codeMapper->hardDelete($codeMapper->findByColumn('categoryID', $object->getId()));
    }

    /**
     * Soft deletes all category codes that belong to this object.
     *
     * Called as a post soft-delete callback.
     *
     * @param Category $object
     */
    public function softDeleteCategoryCodes(Category $object)
    {
        $codeMapper = $this->getJoinMapper('codes');
        $codeMapper->softDelete($codeMapper->findByColumn('categoryID', $object->getId()));
    }

    /**
     * Get a list of categorys which belong to a particular parent
     *
     * @param $id
     * @return Collection|Category[]
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
                {$group_mapper->getColumnString('ug')},
                (
                    SELECT COUNT(*) FROM `task` `t`
                    WHERE `t`.`category_id` IN (
                        SELECT `subc`.`id`
                        FROM {$this->getTable()} `subc`
                        WHERE `subc`.`parent` = `c`.`id`
                    )
                ) AS `c.taskCount`
            FROM {$this->getTable()} c
            LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}
            LEFT JOIN {$this->getJoinSql('homepages', 'c', 'hp')}
            LEFT JOIN {$this->getJoinSql('groups', 'c', 'ug')}
            WHERE c.deleted_at IS NULL AND c.parent=?
            ORDER BY c.name
        ";

        $statement = $this->database->prepare($query);
        $statement->execute(array($id));

        $collection = new \Schoolbox\Collection();
        $category = null;

        foreach ($statement as $row) {
            $existingCat = $collection->filterBy('id', $row['c.id']);
            if ($existingCat->count()) {
                //Category already exists in collection
                $code = $code_mapper->hydrate($row, 'cc');
                if ($code->getCategory()->getId()) {
                    $existingCat->first()->addCode($code);
                }
            } else {
                $category = $this->hydrate($row, 'c');
                if (isset($row['c.taskCount'])) {
                    $category->setTaskCount($row['c.taskCount']);
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
                {$group_mapper->getColumnString('ug')}
            FROM {$this->getTable()} c
            LEFT JOIN {$this->getJoinSql('codes', 'c', 'cc')}
            LEFT JOIN {$this->getJoinSql('homepages', 'c', 'hp')}
            LEFT JOIN {$this->getJoinSql('groups', 'c', 'ug')}
        ";
    }

    /**
     * Helper method for hydrating a result from a query created by getFindQuery().
     *
     * @param  \PDOStatement $statement
     * @throws \Schoolbox\Mapper\Exception if statement returned no category
     * @return Category
     */
    protected function hydrateFromFindQuery($statement)
    {
        $code_mapper = $this->getJoinMapper('codes');
        $homepageConfig_mapper = $this->getJoinMapper('homepages');
        $group_mapper = $this->getJoinMapper('groups');
        $category = null;
        foreach ($statement as $row) {
            if (!$category instanceof $this->model) {
                $category = $this->hydrate($row, 'c');
                if (isset($row['c.taskCount'])) {
                    $category->setTaskCount($row['c.taskCount']);
                }
            }
            $code = $code_mapper->hydrate($row, 'cc');
            if ($code->getCategory()->getId()) {
                $category->addCode($code);
            }
            $homepageConfig = $homepageConfig_mapper->hydrate($row, 'hp');
            $category->setHomepage($homepageConfig);

            $group = $group_mapper->hydrate($row, 'ug');
            $category->setGroup($group);
        }
        if (!($category instanceof Category)) {
            throw new \Schoolbox\Mapper\Exception('Instance of object not found');
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
     * @return Category
     */
    public function findByCode($code)
    {
        $query = $this->getFindQuery();
        // Retrieve all codes for category, not just $code
        $query .= "
            WHERE c.deleted_at IS NULL
            AND c.id IN (
                SELECT cj.categoryID FROM {$this->getJoinMapper('codes')->getTable()} cj
                WHERE cj.deleted_at IS NULL
                AND (cj.code=:code OR cj.code LIKE :codeLike)
            )
        ";
        $statement = $this->database->prepare($query);
        $statement->execute(
            array(
                'code'     => $code,
                'codeLike' => '%/' . $this->database->quoteForLike($code),
            )
        );
        return $this->hydrateFromFindQuery($statement);
    }

    /**
     * {@inheritdoc}
     */
    public function findById($id)
    {
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
     * @return \Schoolbox\Collection|Category[]
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
                             * @var CategoryMapper $mapper
                             * @var Category $model
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
     * @param  Category $category
     * @param  Category $stop_category If supplied, the parent list is truncated
     *                                 above this category.
     * @return Collection
     */
    public function findParents(Category $category, Category $stop_category = null)
    {
        /** @var Category[] $stack */
        $stack = array($category);
        $count = 0;

        $stop_id = null;
        if ($stop_category instanceof Category) {
            $stop_id = $stop_category->getId();
        }

        // Get the parent of the first item in the stack, and add it to the top
        // of the stack.
        while ($count++ < 50) {
            $parent = $stack[0]->getParent();
            if (!($parent instanceof Category)) {
                break;
            }
            $parent_id = $parent->getId();
            if (!$parent_id || (!empty($stop_id) && $stop_id == $parent_id)) {
                break;
            }
            array_unshift($stack, $this->findById($parent_id));
        }

        // Remove the current category from the parent list first.
        array_pop($stack);
        return new Collection($stack);
    }

    /**
     * Recursive delete of a category and its contents
     *
     * @param Category $category
     */
    public function recursiveDelete(Category $category)
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
        $fileMapper = new FileMapper();
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
        $codeMapper = new CategoryCodeMapper();
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
     * @return FolderInterface[]
     */
    public function findByParentId($nodeId)
    {
        return $this->findByParent($nodeId);
    }


}