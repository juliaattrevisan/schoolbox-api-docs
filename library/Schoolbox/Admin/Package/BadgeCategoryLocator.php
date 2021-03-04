<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Package;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Badge\Category;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;

/**
 * Locator for badge categories.
 *
 * Uses the existing category with a given name if it exists; otherwise, creates
 * a new one.
 */
class BadgeCategoryLocator
{
    /**
     * Mapper for locating categories
     */
    private Category\Mapper $mapper;

    /**
     * Collection of existing categories: not initialised until `locate` is
     * called.
     *
     * @var GenericCollection|Category\Model[]
     */
    private GenericCollection $existingCategories;

    /**
     * Constructor
     *
     * @param Category\Mapper $mapper
     */
    /**
     * Constructor
     *
     * @param Category\Mapper $mapper
     */
    public function __construct(Category\Mapper $mapper)
    {
        $this->mapper             = $mapper;
        $this->existingCategories = $mapper->createCollection();
    }

    /**
     * Locate a category by name, creating it if it doesn't already exist.
     *
     * @param string $name
     * @param User   $creator If it doesn't exist: this user is the creator
     * @return Category\Model
     * @throws MapperException if somehow creating a new category fails
     */
    public function locate(string $name, User $creator): Category\Model
    {
        $lookupName = trim(strtolower($name));
        $category   = $this->existingCategories[$lookupName] ?? null;
        if ($category) {
            return $category;
        }

        try {
            $category = $this->mapper->findOneByColumn('name', $name);
        } catch (MapperException $e) {
            $category = $this->mapper->createModel()
                                     ->setName($name)
                                     ->setCreatedBy($creator);
            $this->mapper->save($category);
        }
        $this->existingCategories[$lookupName] = $category;
        return $category;
    }
}
