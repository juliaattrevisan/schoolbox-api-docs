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
use Schoolbox\Filter\Option\Selection\Mapper as FilterSelectionMapper;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Assessment\Term\Mapper as TermMapper;


/**
 * Available Term options:
 * * all
 * * current
 * * previous, and
 * * all terms
 *
 * Term options are keyed by 'current', 'previous' and the term IDs
 *
 */
class TermFilter extends Base
{
    const KEY_ALL      = 'all';        // All terms
    const KEY_FUTURE   = 'future';     // All terms in future
    const KEY_CURRENT  = 'current';    // current term
    const KEY_PREVIOUS = 'previous';   // one before current term
    const KEY_PAST     = 'past';       // All terms in past


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

    /** @var null|Term */
    private $currentTerm;

    /** @var null|Term */
    private $previousTerm;

    /**
     * @var string[]
     */
    private $keys;



    /**
     * @param Filter $filter
     */
    public function __construct(Filter $filter)
    {
        parent::__construct($filter);

        // TODO - create an abstract static method on abstract base class that provides dependencies (similar to modules)
        $this->filterOptionMapper = FilterOptionMapper::getInstance();
        $this->filterDefaultMapper = FilterDefaultMapper::getInstance();
        $this->termMapper = TermMapper::getInstance();
    }


    /**
     * TODO move the common directory part of the string up onto the abstract to avoid duplication
     * @return string
     */
    public function getOptionsTemplate()
    {
        return '@filters/options/term.twig';
    }

    public function getRoleDefaultsTemplate()
    {
        return '@filters/roleDefaults/term.twig';
    }

    public function getPanelTemplate()
    {
        return '@filters/panelFragments/term.twig';
    }


    /**
     * Returns a set of data to inject into this filter's template
     *
     * Intended to be called in twig includes
     *
     * @param string $page
     * @param User $user
     * @return FilterSelection[]
     * @throws MapperException
     */
    public function toContext($page, User $user)
    {
        // Get default options for role
        /** @var GenericCollection|FilterOption[] $optionDefaults */
        $optionDefaults = $this->getOptionDefaults($page)->getOffset($page);


        // Determine which terms should be available for selection
        /** @var GenericCollection|Term[] $terms */
        $terms = TermMapper::getInstance()->findAll()->sortBy('start', 'DESC')->indexBy('id');

        $selectionMapper = FilterSelectionMapper::getInstance();
        $availableSelections = $selectionMapper->createCollection();

        // Is this term available?
        $currentTerm = $this->getCurrentTerm();
        $previousTerm = $this->getPreviousTerm();

        foreach($terms as $term){
            $termFinish = $term->getActualFinish();
            $available = false;
            if($optionDefaults->offsetExists(self::KEY_ALL)){
                $available = true;
            } elseif ($optionDefaults->offsetExists(self::KEY_FUTURE) && $term->getStart()->isFuture()){
                $available = true;
            } elseif ($currentTerm && $optionDefaults->offsetExists(self::KEY_CURRENT) && $currentTerm->getId() === $term->getId()){
                $available = true;
            } elseif ($previousTerm && $optionDefaults->offsetExists(self::KEY_PREVIOUS) && $previousTerm->getId() === $term->getId()){
                $available = true;
            } elseif (!$termFinish->isNull() && $optionDefaults->offsetExists(self::KEY_PAST) && $term->getActualFinish()->isPast()){
                $available = true;
            } elseif ($optionDefaults->offsetExists($term->getId())){
                $available = true;
            }

            if($available){
                $selection = $selectionMapper->createModel();
                $selection
                    ->setUser($user)
                    ->setPage($page)
                    ->setKey((string)$term->getId())
                    ->setSelected(false);
                $availableSelections[$selection->getKey()] = $selection;
            }
        }

        $defaultsPerKey = FilterDefaultMapper::getInstance()->createCollection();
        foreach($optionDefaults as $key => $option){
            foreach($option->getDefaults() as $roleType => $default){
                $defaultsPerKey[$key] = $default;
            }
        }


        // Overlay role-specific settings
        foreach($availableSelections as $selection){

            $term = $terms[$selection->getKey()];
            $termStart  = $term->getStart();
            $termFinish = $term->getActualFinish();

            foreach($defaultsPerKey as $key => $default){
                if(!$default->isSelected()){
                    continue;
                }
                if(self::KEY_ALL === $key){
                    $selection->setSelected(true);
                } elseif(self::KEY_FUTURE === $key && $termStart->isFuture()){
                    $selection->setSelected(true);
                } elseif(self::KEY_PAST === $key && !$termFinish->isNull() && $termFinish->isPast()){
                    $selection->setSelected(true);
                } elseif(self::KEY_CURRENT === $key && $currentTerm && $term->getId() === $currentTerm->getId()){
                    $selection->setSelected(true);
                } elseif(self::KEY_PREVIOUS === $key && $previousTerm && $term->getId() === $previousTerm->getId()){
                    $selection->setSelected(true);
                } elseif((string)$key === (string)$term->getId()){
                    $selection->setSelected(true);
                }
            }
        }


        // Overlay user-specific selections
        /** @var GenericCollection|FilterSelection[] $savedSelections */
        $savedSelections = $selectionMapper->findByColumnList([
            'user_id' => $user->getId(),
            'page' => $page,
        ])->indexBy('key');

        foreach($savedSelections as $key => $savedSelection){
            if($savedSelections->offsetExists($key)){
                $availableSelections[$key]
                    ->setId($savedSelection->getId())
                    ->setSelected($savedSelection->getSelected());
            }
        }

        return [
            'selections' => $availableSelections,
        ];

    }


    /**
     * @return null|Term
     */
    private function getCurrentTerm()
    {
        if(null === $this->currentTerm){
            $now = \Alaress_Date_Format::now();
            foreach($this->terms as $term){
                if($term->contains($now)){
                    return $term;
                }
            }
        }
        return $this->currentTerm;
    }

    /**
     * @return null|Term
     */
    private function getPreviousTerm()
    {
        if(null === $this->previousTerm){
            $currentTerm = $this->getCurrentTerm();
            if(!$currentTerm){
                return null;
            }

            $lastLoopWasCurrentTerm = false;
            foreach($this->terms as $term){
                if($lastLoopWasCurrentTerm){
                    return $term;
                }
                if($term->getId() === $currentTerm->getId()){
                    $lastLoopWasCurrentTerm = true;
                }
            }
        }

        return $this->previousTerm;
    }


    /**
     * Options include 'all', 'future', 'current', 'previous', 'past' and all terms in the system.
     *
     * Grouped by option.key, then indexed by option.page
     *
     * @param null|string $pageFilter If supplied, get options just for this page, otherwise get options for all available pages
     * @return Collection|GenericCollection[]|FilterOption[][]
     */
    public function getOptions($pageFilter = null)
    {
        $validPages = $this->getPages();

        // Did we supply a page, and the page is not in the valid pages?
        if($pageFilter && !in_array($pageFilter, $validPages, true)){
            return new Collection();
        }

        // Get existing DB values, discarding any that belong to invalid pages
        /** @var Collection|GenericCollection[]|FilterOption[][] $optionsPerPage */
        $optionsPerPage = $this->filterOptionMapper->findByColumnList([
            'filter_id' => $this->getFilter()->getId(),
        ])->filter(function(FilterOption $option) use ($pageFilter, $validPages){

            // Are we filtering for page?
            if($pageFilter && $option->getPage() !== $pageFilter){
                return false;
            }

            return in_array($option->getPage(), $validPages, true);
        })->groupBy('page');


        // Ensure we have a set of options per valid page
        foreach($validPages as $page){

            // Are we filtering by page?
            if($pageFilter && $pageFilter !== $page){
                continue;
            }

            if(!$optionsPerPage->offsetExists($page)){
                $optionsPerPage->setOffset($page, $this->filterOptionMapper->createCollection());
            }
        }

        // Get all terms
        /** @var GenericCollection|Term[] $terms */
        $this->terms = $terms = $this->termMapper->findAll()->sortBy('start', 'DESC')->indexBy('id');

        // Ensure all possible options present in graph
        /** @var GenericCollection|FilterOption[] $pageFilterOptions */
        foreach($optionsPerPage as $page => $pageFilterOptions){
            $pageFilterOptions->indexBy('key');

            // Ensure it contains 'all'
            if(!$pageFilterOptions->offsetExists(self::KEY_ALL)){
                $option = $this->filterOptionMapper->createModel();
                $option
                    ->setFilter($this->getFilter())
                    ->setKey(self::KEY_ALL)
                    ->setPage($page)
                    ->setAvailable(FilterOption::PAGE_PENDING_ASSESSMENTS !== $page);
                $pageFilterOptions->setOffset($option->getKey(), $option);
            }

            // Ensure it contains 'future'
            if(!$pageFilterOptions->offsetExists(self::KEY_FUTURE)){
                $option = $this->filterOptionMapper->createModel();
                $option
                    ->setFilter($this->getFilter())
                    ->setKey(self::KEY_FUTURE)
                    ->setPage($page)
                    ->setAvailable(false);
                $pageFilterOptions->setOffset($option->getKey(), $option);
            }

            // Ensure it contains 'current'
            if(!$pageFilterOptions->offsetExists(self::KEY_CURRENT)){
                $option = $this->filterOptionMapper->createModel();
                $option
                    ->setFilter($this->getFilter())
                    ->setKey(self::KEY_CURRENT)
                    ->setPage($page)
                    ->setAvailable(FilterOption::PAGE_PENDING_ASSESSMENTS === $page);
                $pageFilterOptions->setOffset($option->getKey(), $option);
            }

            // Ensure it contains 'previous'
            if(!$pageFilterOptions->offsetExists(self::KEY_PREVIOUS)){
                $option = $this->filterOptionMapper->createModel();
                $option
                    ->setFilter($this->getFilter())
                    ->setKey(self::KEY_PREVIOUS)
                    ->setPage($page)
                    ->setAvailable(FilterOption::PAGE_PENDING_ASSESSMENTS === $page);
                $pageFilterOptions->setOffset($option->getKey(), $option);
            }

            // Ensure it contains 'past'
            if(!$pageFilterOptions->offsetExists(self::KEY_PAST)){
                $option = $this->filterOptionMapper->createModel();
                $option
                    ->setFilter($this->getFilter())
                    ->setKey(self::KEY_PAST)
                    ->setPage($page)
                    ->setAvailable(FilterOption::PAGE_PENDING_ASSESSMENTS !== $page);
                $pageFilterOptions->setOffset($option->getKey(), $option);
            }

            // Ensure it contains all Term instances
            foreach($terms as $term){
                if(!$pageFilterOptions->offsetExists($term->getId())){
                    $option = $this->filterOptionMapper->createModel();
                    $option
                        ->setFilter($this->getFilter())
                        ->setKey($term->getId())
                        ->setPage($page)
                        ->setAvailable(false);
                    $pageFilterOptions->setOffset($option->getKey(), $option);
                }
            }

            // Discard any options that refer to Terms that no longer exist (eg if deleted)
            foreach($pageFilterOptions as $filterOption){
                // Does the filter refer to a specific Term instance?
                $isForSpecificTerm = !in_array($filterOption->getKey(), [self::KEY_ALL, self::KEY_FUTURE, self::KEY_CURRENT, self::KEY_PAST, self::KEY_PREVIOUS], true);
                if($isForSpecificTerm && !$terms->offsetExists($filterOption->getKey())){
                    $pageFilterOptions->offsetUnset($filterOption->getKey());
                }
            }

            // Order the options. 'All', then 'Current', then 'Previous', then from most recent term back
            $pageFilterOptions->sortByCallback(function(FilterOption $left, FilterOption $right) use ($terms){
                $leftKey = $left->getKey();
                $rightKey = $right->getKey();
                if(self::KEY_ALL === $leftKey){
                    return -1;
                }
                if(self::KEY_FUTURE === $leftKey){
                    return self::KEY_ALL === $right->getKey() ? +1 : -1;
                }
                if(self::KEY_CURRENT === $leftKey){
                    return in_array($rightKey, [self::KEY_ALL, self::KEY_FUTURE], true)  ? +1 : -1;
                }
                if(self::KEY_PREVIOUS === $leftKey){
                    return in_array($rightKey, [self::KEY_ALL, self::KEY_FUTURE, self::KEY_CURRENT], true)  ? +1 : -1;
                }
                if(self::KEY_PAST === $leftKey){
                    return in_array($rightKey, [self::KEY_ALL, self::KEY_FUTURE, self::KEY_CURRENT, self::KEY_PREVIOUS], true)  ? +1 : -1;
                }
                if(self::KEY_FUTURE === $rightKey || self::KEY_PAST === $rightKey){
                    return +1;
                }
                return $terms[$right->getKey()]->compare($terms[$leftKey]);
            });
            $pageFilterOptions->indexBy('key');

            $this->keys = $pageFilterOptions->keys();
        }

        // Convert to ordered by key
        $optionsPerKey = new Collection();

        // Ensure page order is same as getPages()
        foreach($validPages as $page){
            if($pageFilter && $pageFilter !== $page){
                continue;
            }

            $pageFilterOptions = $optionsPerPage[$page];
            foreach($pageFilterOptions as $key => $filterOption){
                if(!$optionsPerKey->offsetExists($key)){
                    $optionsPerKey->setOffset($key, $this->filterOptionMapper->createCollection());
                }
                $optionsPerKey[$key][$page] = $filterOption;
            }
        }

        return $optionsPerKey;
    }




    /**
     * Returns sets of options, grouped by option.page, then indexed by option.key.
     * Each Option contains a set of Defaults, indexed by roleType
     *
     * @param null|string $pageFilter If supplied, get options just for this page, otherwise get options for all available pages
     * @return Collection|GenericCollection[]|FilterOption[][]
     */
    public function getOptionDefaults($pageFilter = null)
    {
        $optionsPerKey = $this->getOptions();

        // Get the ones which are available (regardless of whether they exist yet in DB)
        $availableOptions = $this->filterOptionMapper->createCollection();
        foreach($optionsPerKey as $key => $keyOptions){
            /** @var FilterOption[] $keyOptions */
            foreach($keyOptions as $page => $option){
                if($option->getAvailable()){
                    $availableOptions->add($option);
                }
            }
        }

        // Find any options that already exist in the DB
        /** @var FilterOption[]|GenericCollection $existingOptions */
        $existingOptions = $availableOptions->filter(function(FilterOption $option){
            return null !== $option->getId();
        })->indexBy('id');

        // Load existing defaults for existing options, and group by option
        $defaultsPerExistingOption = $this->filterDefaultMapper
            ->findByColumn('filter_option_id', $existingOptions->keys())
            ->groupByCallback(function(FilterDefault $default){
                return $default->getFilterOption()->getId();
            });

        // Add defaults to the options, and index defaults by roleType
        foreach($defaultsPerExistingOption as $optionId => $defaults){
            $existingOptions[$optionId]->setDefaults($defaults->indexBy('roleType'));
        }

        // Group the options by page, then index by key

        /** @var Collection|GenericCollection[]|FilterOption[][] $optionsPerPage */
        $optionsPerPage = $availableOptions->groupBy('page');
        foreach($optionsPerPage as $page => $options){
            $options->indexBy('key');
        }

        // Ensure the pages are sorted
        $sortedOptionsPerPage = new Collection();
        foreach($this->getPages() as $page){
            if($pageFilter && $pageFilter !== $page){
                continue;
            }
            $sortedOptionsPerPage[$page] = $optionsPerPage[$page];
        }

        // Ensure all defaults are available for configuration
        foreach($sortedOptionsPerPage as $page => $options){
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

        return $sortedOptionsPerPage;
    }


    /**
     * Return the set of pages where this filter is displayed
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


    /**
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getKeys()
    {
        // Have we not yet determined what keys are available?
        if(!is_array($this->keys)){
            $terms = TermMapper::getInstance()->findAll()->sortBy('start', 'DESC')->indexBy('id');
            $this->keys = array_merge([
                self::KEY_ALL,
                self::KEY_FUTURE,
                self::KEY_CURRENT,
                self::KEY_PREVIOUS,
                self::KEY_PAST,
            ], $terms->keys());

        }
        return $this->keys;
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
        if(self::KEY_FUTURE === $key){
            return 'Future Terms';
        }
        if(self::KEY_CURRENT === $key){
            $currentTerm = $this->getCurrentTerm();
            return $currentTerm ? "Current ({$currentTerm->getCode()})" : '';
        }
        if(self::KEY_PREVIOUS === $key){
            $previousTerm = $this->getPreviousTerm();
            return $previousTerm ? "Previous ({$previousTerm->getCode()})" : '';
        }
        if(self::KEY_PAST === $key){
            return 'Past Terms';
        }
        return $this->terms[$key]->getCode();
    }

}
