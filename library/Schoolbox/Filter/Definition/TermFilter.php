<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Definition;

use Schoolbox\Collection;
use Schoolbox\Filter\Model as Filter;
use Schoolbox\Filter\Option\Defaults\Model as FilterDefault;
use Schoolbox\Filter\Option\Defaults\Mapper as FilterDefaultMapper;
use Schoolbox\Filter\Option\Model as FilterOption;
use Schoolbox\Filter\Option\Mapper as FilterOptionMapper;
use Schoolbox\Filter\Option\Selection\Model as FilterSelection;
use Schoolbox\GenericCollection;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Assessment\Term\Mapper as TermMapper;


/**
 * Available Term options:
 * * current
 * * previous, and
 * * all terms
 *
 * Term options are keyed by 'current', 'previous' and the term IDs
 *
 */
class TermFilter implements IFilterDefinition
{
    const KEY_ALL      = 'all';
    const KEY_CURRENT  = 'current';
    const KEY_PREVIOUS = 'previous';

//    private $inital = [
//        'current'  => TRUE,
//        'previous' => FALSE,
//    ];

    /**
     * @var Filter
     */
    private $filter;

    /** @var FilterOptionMapper */
    private $filterOptionMapper;

    /** @var FilterDefaultMapper */
    private $filterDefaultMapper;

    /** @var TermMapper */
    private $termMapper;

    /**
     * @var GenericCollection|Term[]
     */
    private $terms;

    /**
     * grouped by option.page, then indexed by option.key
     * @var Collection|GenericCollection[]|FilterOption[][]
     */
    private $filterOptionsPerPage;

    /**
     * grouped by key
     * @var Collection|GenericCollection[]|FilterOption[][]
     */
    private $filterOptionsPerKey;

    /**
     * @var string[]
     */
    private $keys = [];

    /**
     * @param Filter $filter
     */
    public function __construct(Filter $filter)
    {
        $this->filter = $filter;

        // TODO - create an abstract static method on abstract base class that provides dependencies (similar to modules)
        $this->filterOptionMapper = FilterOptionMapper::getInstance();
        $this->filterDefaultMapper = FilterDefaultMapper::getInstance();
        $this->termMapper = TermMapper::getInstance();
    }





    public function getTitle()
    {
        return 'Term';
    }

    public function getAdminDescription()
    {
        return 'Select which Terms should be available in the Term filter on the listed pages. You can either select \'all\' to always display
        all terms, or \'current\' | \'previous\' to display whatever term is current or previous, or you can explicitly selects specific terms.';
    }


    /**
     * Return the set of pages where this filter is available
     * @return string[]
     */
    public function getPages()
    {
        return [
            FilterOption::PAGE_PENDING_ASSESSMENTS,
            FilterOption::PAGE_GRADES,
            FilterOption::PAGE_MARK_BOOK,
        ];
    }

    public function getKeys()
    {
        return $this->keys;
    }


    /**
     * Return all possible options for this filter, grouped by page
     *
     * Options may be pulled from:
     * * DB,
     * * Hard-coded, or
     * * Both
     *
     * filterOption.ID will be NULL if not in DB
     *
     * @return Collection|GenericCollection[]|FilterOption[][]
     * @throws MapperException
     */
    public function buildFilterOptions()
    {
        $validPages = $this->getPages();

        // Get existing DB values, discarding any that belong to invalid pages
        /** @var Collection|GenericCollection[]|FilterOption[][] $this->filterOptionsPerPage */
        $this->filterOptionsPerPage = $this->filterOptionMapper->findByColumnList([
            'filter_id' => $this->filter->getId(),
        ])->filter(function(FilterOption $option) use ($validPages){
            return in_array($option->getPage(), $validPages, true);
        })->groupBy('page');


        // Ensure we have a set of options per page
        foreach($validPages as $page){
            if(!$this->filterOptionsPerPage->offsetExists($page)){
                $this->filterOptionsPerPage->setOffset($page, $this->filterOptionMapper->createCollection());
            }
        }

        // Get all terms
        /** @var GenericCollection|Term[] $terms */
        $this->terms = $terms = $this->termMapper->findAll()->sortBy('start', 'DESC')->indexBy('id');

        // Ensure all possible options present
        /** @var GenericCollection|FilterOption[] $pageFilterOptions */
        foreach($this->filterOptionsPerPage as $page => $pageFilterOptions){
            $pageFilterOptions->indexBy('key');

            // Ensure it contains 'all'
            if(!$pageFilterOptions->offsetExists(self::KEY_ALL)){
                $option = $this->filterOptionMapper->createModel();
                $option
                    ->setFilter($this->filter)
                    ->setKey(self::KEY_ALL)
                    ->setPage($page)
                    ->setSelectable(FilterOption::PAGE_PENDING_ASSESSMENTS !== $page);
                $pageFilterOptions->setOffset($option->getKey(), $option);
            }

            // Ensure it contains 'current'
            if(!$pageFilterOptions->offsetExists(self::KEY_CURRENT)){
                $option = $this->filterOptionMapper->createModel();
                $option
                    ->setFilter($this->filter)
                    ->setKey(self::KEY_CURRENT)
                    ->setPage($page)
                    ->setSelectable(FilterOption::PAGE_PENDING_ASSESSMENTS === $page);
                $pageFilterOptions->setOffset($option->getKey(), $option);
            }

            // Ensure it contains 'previous'
            if(!$pageFilterOptions->offsetExists(self::KEY_PREVIOUS)){
                $option = $this->filterOptionMapper->createModel();
                $option
                    ->setFilter($this->filter)
                    ->setKey(self::KEY_PREVIOUS)
                    ->setPage($page)
                    ->setSelectable(FilterOption::PAGE_PENDING_ASSESSMENTS === $page);
                $pageFilterOptions->setOffset($option->getKey(), $option);
            }

            // Ensure it contains all Terms
            foreach($terms as $term){
                if(!$pageFilterOptions->offsetExists($term->getId())){
                    $option = $this->filterOptionMapper->createModel();
                    $option
                        ->setFilter($this->filter)
                        ->setKey($term->getId())
                        ->setPage($page)
                        ->setSelectable(false);
                    $pageFilterOptions->setOffset($option->getKey(), $option);
                }
            }

            // Discard any options that refer to Terms that no longer exist (eg if deleted)
            foreach($pageFilterOptions as $filterOption){
                // Does the filter refer to a specific Term?
                $isForSpecificTerm = !in_array($filterOption->getKey(), [self::KEY_ALL, self::KEY_CURRENT, self::KEY_PREVIOUS], true);
                if($isForSpecificTerm && !$terms->offsetExists($filterOption->getKey())){
                    $pageFilterOptions->offsetUnset($filterOption->getKey());
                }
            }

            // Order the options. 'Current', then 'Previous', then from most recent term back
            $pageFilterOptions->sortByCallback(function(FilterOption $left, FilterOption $right) use ($terms){
                if(self::KEY_ALL === $left->getKey()){
                    return -1;
                }
                if(self::KEY_CURRENT === $left->getKey()){
                    return self::KEY_ALL === $right->getKey() ? +1 : -1;
                }
                if(self::KEY_PREVIOUS === $left->getKey()){
                    return in_array($right->getKey(), [self::KEY_ALL, self::KEY_CURRENT], true)  ? +1 : -1;
                }
                return $terms[$right->getKey()]->compare($terms[$left->getKey()]);
            });
            $pageFilterOptions->indexBy('key');

            // TODO -don't overwrite every iteration
            $this->keys = $pageFilterOptions->keys();
        }

        // Convert to ordered by key
        $this->filterOptionsPerKey = new Collection();
        /** @var GenericCollection $pageFilterOptions */
        foreach($this->filterOptionsPerPage as $page => $pageFilterOptions){
            foreach($pageFilterOptions as $key => $filterOption){
                if(!$this->filterOptionsPerKey->offsetExists($key)){
                    $this->filterOptionsPerKey->setOffset($key, $this->filterOptionMapper->createCollection());
                }
                $this->filterOptionsPerKey[$key][$page] = $filterOption;
            }
        }

        return $this->filterOptionsPerKey;
    }




    /**
     * @param string $key
     * @return bool
     */
    public function isValidKey($key)
    {
        if(in_array($key, [self::KEY_ALL, self::KEY_CURRENT, self::KEY_PREVIOUS], true)){
            return true;
        }

        return (string)(int)$key === $key;
    }

    public function isValidRoleType($roleType, FilterOption $option)
    {
        $validRoles = FilterOption::getRoleTypesForPage($option->getPage());

        return in_array($roleType, $validRoles, true);
    }


    /**
     * @param string $key
     * @return string
     */
    public function getLabel($key)
    {
        if(self::KEY_ALL === $key){
            return 'All Terms';
        }
        if(self::KEY_CURRENT === $key){
            $code = $this->terms->first()->getCode();
            return "Current ($code)";
        }
        if(self::KEY_PREVIOUS === $key){
            $idx = 0;
            foreach($this->terms as $term){
                if(1 === $idx){
                    $code = $term->getCode();
                    return "Previous ($code)";
                }
                $idx++;
            }
            return '';
        }
        return $this->terms[$key]->getCode();

    }


    /**
     * Returns admin-configured defaults for all relevant roleTypes, grouped by roleType
     * @return GenericCollection|FilterDefault[][]
     */
    public function getDefaults()
    {

    }


    /**
     * Return the admin-configured default selections for specified role type.
     *
     * If not configured in admin, fall back to hard-coded sensible defaults.
     *
     * @param string $roleType
     * @return GenericCollection|FilterDefault[]
     */
    public function getDefaultsForRole($roleType)
    {
        // TODO: Implement getDefaultsForRole() method.
    }


    /**
     * @param User $user
     * @return GenericCollection|FilterSelection[]
     */
    public function getSelectionsForUser(User $user)
    {
        // TODO: Implement getSelectionsForUser() method.
    }


    /**
     * Is this option displayed in the filter?
     *
     * Options will always be displayed if the admin has explicitly selected them.
     * Otherwise it is up to the filter type. Eg, the term Filter defaults to not showing terms older than 4 years if admin has not explicitly selected them.
     *
     * @param FilterOption $filterOption
     * @return bool
     */
    public function isOptionAvailable(FilterOption $filterOption)
    {
        // TODO: Implement isOptionAvailable() method.
    }


    /**
     * Indexed by filterOption.key, then filterOption.page
     *
     * @return Collection|GenericCollection[]|FilterOption[][]
     */
    public function getFilterOptionsByKeyThenPage()
    {
        return $this->filterOptionsPerKey;
    }


    /**
     * Indexed by filterOption.page, then filterOption.key
     *
     * @return Collection|GenericCollection[]|FilterOption[][]
     */
    public function getFilterOptionsByPageThenKey()
    {
        return $this->filterOptionsPerPage;
    }


    /**
     * Grouped by option.page, then indexed by option.key
     *
     * @return Collection|GenericCollection[]|FilterOption[][]
     * @throws MapperException
     */
    public function buildFilterDefaults()
    {
        // Build all possible filter options
        $this->buildFilterOptions();

        // Get the ones which are selectable (regardless of whether they exist yet in DB)
        $selectableOptions = $this->filterOptionMapper->createCollection();
        foreach($this->filterOptionsPerPage as $page => $pageOptions){
            /** @var FilterOption[] $pageOptions */
            foreach($pageOptions as $option){
                if($option->getSelectable()){
                    $selectableOptions->add($option);
                }
            }
        }

        // For any options that exist in DB, load their role defaults
        $existingOptions = $selectableOptions->filter(function(FilterOption $option){
            return null !== $option->getId();
        })->indexBy('id');

//        // Get which filter options are able to be selected for this filter
//        $options = $this->filterOptionMapper->findByColumnList([
//            'filter_id' => $this->filter->getId(),
//            'selectable' => true,
//        ]);

        // Load existing defaults for these options
        $existingDefaults = $this->filterDefaultMapper->findByColumn('filter_option_id', $existingOptions->getColumnValues('id'));

        $defaultsPerOption = $existingDefaults->groupByCallback(function(FilterDefault $default){
            return $default->getFilterOption()->getId();
        });

        foreach($defaultsPerOption as $optionId => $defaults){
            $existingOptions[$optionId]->setDefaults($defaults->indexBy('roleType'));
        }

        /** @var Collection|GenericCollection[]|FilterOption[][] $optionsPerPage */
        $optionsPerPage = $selectableOptions->groupBy('page');
        foreach($optionsPerPage as $page => $options){
            $options->indexBy('key');
        }

        // Ensure all Defaults are available for configuration
        foreach($optionsPerPage as $page => $options){
            /** @var GenericCollection|FilterOption[] $options */
            foreach($options as $key => $option){
                $defaults = $option->getDefaults();
                // Does it contain a Default for all relevant roleTypes?
                foreach(FilterOption::getRoleTypesForPage($page) as $roleType){
                    if (!$defaults->offsetExists($roleType)) {
                        $default = $this->filterDefaultMapper->createModel();
                        $default
                            ->setFilterOption($option)
                            ->setRoleType($roleType)
                            ->setSelected(self::KEY_CURRENT === $key && FilterOption::PAGE_PENDING_ASSESSMENTS === $page);
                        $defaults[$roleType] = $default;
                    }
                }
            }
        }

        return $optionsPerPage;
    }
}
