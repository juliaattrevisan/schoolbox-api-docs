<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\WorkType;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option\Model as FilterOption;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\WorkType;

class Definition extends AbstractFilterDefinition
{

    /**
     * @var GenericCollection|WorkType\Model[]
     */
    private $workTypes;


    /**
     * @param GenericCollection|WorkType\Model[] $workTypes
     */
    public function setWorkTypes(GenericCollection $workTypes)
    {
        $this->workTypes = $workTypes;
    }

    /**
     * @return string
     */
    protected function getTemplateFile()
    {
        return 'workType.twig';
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
    }


    /**
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getKeys()
    {
        // TODO: Implement getKeys() method.
    }
}
