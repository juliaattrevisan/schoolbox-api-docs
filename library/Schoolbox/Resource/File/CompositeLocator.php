<?php
/**
 * Composite locator - joins several locators together
 *
 * @author      Amri Sannang <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\File;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Mapper\Searchable;
use Schoolbox\Resource;
use Schoolbox\User;

class CompositeLocator implements ILocator
{
    /**
     * @var ILocator[]
     */
    protected $locators = [];

    /**
     * @param  ILocator ...             An unlimited number of ILocator objects.
     * @throws InvalidArgumentException if any param is not a ILocator
     */
    public function __construct() {
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
     */
    public function findById($nodeId)
    {
        // findById doesn't really make sense for the composite locator.
        foreach ($this->locators as $locator) {
            try {
                return $locator->findById($nodeId);
            } catch (\Exception $e) {
                continue;
            }
        }
        // Throw something
    }


    /**
     * Return the files contained within the folder with the given ID
     *
     * @param int $nodeId
     *
     * @return Collection|IFile[]
     */
    public function findByFolderId($nodeId)
    {
        $collection = new Collection();
        foreach ($this->locators as $locator) {
            $collection->merge($locator->findByFolderId($nodeId));
        }
        return $collection;
    }
}
