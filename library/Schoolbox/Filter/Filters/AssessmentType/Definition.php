<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\AssessmentType;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option\Model as FilterOption;
use Schoolbox\GenericCollection;
use Schoolbox\User;

class Definition extends AbstractFilterDefinition
{
    /**
     * @return string
     */
    protected function getTemplateFile()
    {
        return 'assessmentType.twig';
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
        return '';
    }


    /**
     * @return string
     */
    public function getDefaultsTemplate()
    {
        // TODO: Implement getRoleDefaultsTemplate() method.
        return '';
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
        return '';
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
        return [];
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
        // TODO: Implement getOptions() method.
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
        // TODO: Implement getOptionDefaults() method.
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



}
