<?php

/**
 * @author      Amri Sannang <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\File;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception\NotFound;

/**
 * Class CompositeLocator
 *
 * Composite locator - joins several locators together
 *
 * @package Schoolbox\Resource\File
 */
class CompositeLocator implements ILocator
{

    /**
     * @var ILocator[]
     */
    protected $locators = [];


    /**
     * CompositeLocator constructor.
     *
     * Takes an unlimited number of ILocator objects.
     *
     * @throws InvalidArgumentException if any param is not a ILocator
     */
    public function __construct()
    {

        /** @var ILocator $subLocator */
        foreach (func_get_args() as $subLocator) {
            if (!($subLocator instanceof ILocator)) {
                throw new InvalidArgumentException(
                    'All locators must implement \\Schoolbox\\Resource\\File\\ILocator'
                );
            }
            $this->locators[] = $subLocator;
        }
    }


    /**
     * Return the folder by the given ID
     *
     * @param int $nodeId
     * @return IFile
     * @throws NotFound
     */
    public function findById($nodeId)
    {
        // findById doesn't really make sense for the composite locator.
        foreach ($this->locators as $locator) {
            try {
                return $locator->findById($nodeId);
            } catch (\Schoolbox\Mapper\Exception $e) {
                continue;
            }
        }

        // Throw something
        throw new NotFound();
    }


    /**
     * Return the files contained within the folder with the given ID
     *
     * @param int $nodeId
     * @return Collection|IFile[]
     */
    public function findByFolderId($nodeId)
    {
        $collection = new Collection();
        foreach ($this->locators as $locator) {
            $collection = $collection->merge($locator->findByFolderId($nodeId));
        }
        return $collection;
    }
}
