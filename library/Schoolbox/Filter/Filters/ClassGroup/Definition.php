<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\ClassGroup;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option\Model as FilterOption;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Resource\Folder\Model as Folder;

class Definition extends AbstractFilterDefinition
{

    /**
     * @var GenericCollection|Folder[]
     */
    private $classes;

    /**
     * @var GenericCollection|Folder[]
     */
    private $groups;


    /**
     * @param GenericCollection|Folder[] $classes
     * @return static
     */
    public function setClasses(GenericCollection $classes)
    {
        $this->classes = $classes;
        return $this;
    }

    /**
     * @param GenericCollection|Folder[] $groups
     * @return static
     */
    public function setGroups(GenericCollection $groups)
    {
        $this->groups = $groups;
        return $this;
    }

    public function getTemplateFile()
    {
        return 'classGroup.twig';
    }


    /**
     * Returns the twig template to render this filters options
     *
     * Intended to be called in Controller
     *
     * @return string
     */
    public function getOptionsTemplate()
    {
        // TODO: Implement getOptionsTemplate() method.
    }


    public function getDefaultsTemplate()
    {
        // TODO: Implement getRoleDefaultsTemplate() method.
    }


    /**
     * Returns the twig template to render into the filter panel
     *
     * Intended to be called in twig includes
     *
     * @return string
     */
    public function getPanelTemplate()
    {
        // TODO: Implement getPanelTemplate() method.
    }


    /**
     * Returns a set of data to inject into this filter's template
     *
     * Intended to be called in twig includes
     *
     * @param string $page
     * @param User   $user
     * @return mixed[]
     */
    public function toContext($page, User $user)
    {
        // TODO: Implement toContext() method.
    }


    /**
     * Get all available filter options, grouped by option.key, then indexed by option.page
     *
     * Options may be pulled from:
     * * DB,
     * * Hard-coded, or
     * * Both
     *
     * Must ensure inner collections are sorted the same as getPages()
     *
     * @return Collection|GenericCollection[]|FilterOption[][]
     */
    public function getOptions()
    {
        // TODO consider all/none ?
        return new Collection();
    }


    /**
     * Returns sets of options, grouped by option.page, then indexed by option.key.
     * Each Option contains a set of Defaults, indexed by roleType
     *
     * @return Collection|GenericCollection[]|FilterOption[][]
     */
    public function getOptionDefaults()
    {
        // TODO consider all/none ?
        return new Collection();
    }


    /**
     * Return the set of pages where this filter is available
     *
     * @see FilterOption::PAGE_* constants
     * @return string[]
     */
    public function getPages()
    {
        // TODO: Implement getPages() method.
        return [];
    }


    /**
     * Is the roleType valid for this option? Used for sanitising input
     *
     * @param string       $roleType
     * @param FilterOption $option
     * @return bool
     */
    public function isValidRoleType($roleType, FilterOption $option)
    {
        // TODO: Implement isValidRoleType() method.
        return true;
    }


    /**
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getKeys()
    {
        // TODO: Implement getKeys() method.
        return [];
    }


    /**
     * Returns a set of data to inject into this filter's template
     *
     * Intended to be called in twig includes
     *
     * @param string $page
     * @param User   $user
     * @return mixed[]
     */
    public function toPanelContext($page, User $user)
    {
        // TODO: Implement toPanelContext() method.
    }
}
