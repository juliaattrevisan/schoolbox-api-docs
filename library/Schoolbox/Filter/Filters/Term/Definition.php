<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Term;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter;
use Schoolbox\Filter\Option\Defaults as FilterDefault;
use Schoolbox\Filter\Option as FilterOption;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Term;


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
class Definition extends AbstractFilterDefinition
{

    const KEY_ALL = 'all';        // All terms
    const KEY_FUTURE = 'future';     // All terms in future
    const KEY_CURRENT = 'current';    // current term
    const KEY_PREVIOUS = 'previous';   // one before current term
    const KEY_PAST = 'past';       // All terms in past

    private static $customKeys = [
        self::KEY_ALL,
        self::KEY_FUTURE,
        self::KEY_CURRENT,
        self::KEY_PREVIOUS,
        self::KEY_PAST,
    ];

    /**
     * @var GenericCollection|Term\Model[]
     */
    private $terms;

    /** @var null|Term\Model */
    private $currentTerm;

    /** @var null|Term\Model */
    private $previousTerm;

    /**
     * @param Filter\Model         $filter
     * @param FilterOption\Mapper  $filterOptionMapper
     * @param FilterDefault\Mapper $filterDefaultMapper
     * @param GenericCollection    $terms
     * @param Term\Model|null      $currentTerm
     * @param Term\Model|null      $previousTerm
     */
    public function __construct(
        Filter\Model $filter,
        FilterOption\Mapper $filterOptionMapper,
        FilterDefault\Mapper $filterDefaultMapper,
        GenericCollection $terms,
        Term\Model $currentTerm = null,
        Term\Model $previousTerm = null
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->terms        = $terms;
        $this->currentTerm  = $currentTerm;
        $this->previousTerm = $previousTerm;
    }


    /**
     * @return string[]
     */
    public function getCustomKeys()
    {
        return self::$customKeys;
    }


    /**
     * @return Term\Model[]|GenericCollection
     */
    public function getTerms()
    {
        return $this->terms;
    }


    /**
     * @return Term\Model
     */
    public function getCurrentTerm()
    {
        return $this->currentTerm;
    }


    /**
     * @return Term\Model
     */
    public function getPreviousTerm()
    {
        return $this->previousTerm;
    }


    /**
     * @inheritdoc
     */
    protected function getTemplateFile()
    {
        return 'term.twig';
    }


    /**
     * Options include 'all', 'future', 'current', 'previous', 'past' and all terms in the system.
     *
     * Grouped by option.key, then indexed by option.page
     *
     * @param null|string $pageFilter If supplied, get options just for this page, otherwise get options for all available pages
     * @return Collection|GenericCollection[]|FilterOption\Model[][]
     */
    public function getOptions($pageFilter = null)
    {
        $validPages = $this->getPages();

        // Did we supply a page, and the page is not in the valid pages?
        if ($pageFilter && !in_array($pageFilter, $validPages, true)) {
            return new Collection();
        }

        // Get existing DB values, discarding any that belong to invalid pages
        /** @var Collection|GenericCollection[]|FilterOption\Model[][] $optionsPerPage */
        $optionsPerPage = $this->getFilterOptionMapper()->findByColumnList([
            'filter_id' => $this->getFilter()->getId(),
        ])->filter(function (FilterOption\Model $option) use ($pageFilter, $validPages) {

            // Are we filtering for page?
            if ($pageFilter && $option->getPage() !== $pageFilter) {
                return false;
            }

            return in_array($option->getPage(), $validPages, true);
        })->groupBy('page');


        // Ensure we have a set of options per valid page
        foreach ($validPages as $page) {

            // Are we filtering by page?
            if ($pageFilter && $pageFilter !== $page) {
                continue;
            }

            if (!$optionsPerPage->offsetExists($page)) {
                $optionsPerPage->setOffset($page, $this->getFilterOptionMapper()->createCollection());
            }
        }

        // Ensure all possible options present in graph
        /** @var GenericCollection|FilterOption\Model[] $pageOptions */
        foreach ($optionsPerPage as $page => $pageOptions) {
            $pageOptions->indexBy('key');

            // Ensure it contains 'all', 'current', etc
            foreach ($this->getCustomKeys() as $customAbstractKey) {
                if (!$pageOptions->offsetExists($customAbstractKey)) {
                    $option = $this->getFilterOptionMapper()->createModel();

                    $isAvailable = false;
                    switch ($customAbstractKey) {
                        case self::KEY_ALL:
                            $isAvailable = FilterOption\Model::PAGE_PENDING_ASSESSMENTS !== $page;
                            break;
                        case self::KEY_FUTURE:
                            $isAvailable = false;
                            break;
                        case self::KEY_CURRENT:
                            $isAvailable = FilterOption\Model::PAGE_PENDING_ASSESSMENTS === $page;
                            break;
                        case self::KEY_PREVIOUS:
                            $isAvailable = FilterOption\Model::PAGE_PENDING_ASSESSMENTS === $page;
                            break;
                        case self::KEY_PAST:
                            $isAvailable = FilterOption\Model::PAGE_PENDING_ASSESSMENTS !== $page;
                            break;
                    }
                    $option
                        ->setFilter($this->getFilter())
                        ->setKey($customAbstractKey)
                        ->setPage($page)
                        ->setAvailable($isAvailable);
                    $pageOptions->setOffset($option->getKey(), $option);
                }
            }


            // Ensure it contains all Term instances
            foreach ($this->terms as $term) {
                if (!$pageOptions->offsetExists($term->getId())) {
                    $option = $this->getFilterOptionMapper()->createModel();
                    $option
                        ->setFilter($this->getFilter())
                        ->setKey($term->getId())
                        ->setPage($page)
                        ->setAvailable(false);
                    $pageOptions->setOffset($option->getKey(), $option);
                }
            }

            // Discard any options that refer to Terms that no longer exist (eg if deleted)
            foreach ($pageOptions as $filterOption) {
                // Does the filter refer to a specific Term instance?
                $isForSpecificTerm = !in_array($filterOption->getKey(), $this->getCustomKeys(), true);
                if ($isForSpecificTerm && !$this->terms->offsetExists($filterOption->getKey())) {
                    $pageOptions->offsetUnset($filterOption->getKey());
                }
            }

            // Order the options. 'All', then 'Current', then 'Previous', then from most recent term back
            $this->sortFilterOptions($pageOptions)->indexBy('key');
        }

        // Convert to ordered by abstract key
        $optionsPerKey = new Collection();

        // Ensure page order is same as getPages()
        foreach ($validPages as $page) {
            if ($pageFilter && $pageFilter !== $page) {
                continue;
            }

            $pageOptions = $optionsPerPage[$page];
            foreach ($pageOptions as $abstractKey => $filterOption) {
                if (!$optionsPerKey->offsetExists($abstractKey)) {
                    $optionsPerKey->setOffset($abstractKey, $this->getFilterOptionMapper()->createCollection());
                }
                $optionsPerKey[$abstractKey][$page] = $filterOption;
            }
        }
        return $optionsPerKey;
    }


    /**
     * @param GenericCollection|FilterOption\Model[] $options
     * @return GenericCollection
     */
    protected function sortFilterOptions(GenericCollection $options)
    {
        return $options->sortByCallback(function (FilterOption\Model $left, FilterOption\Model $right) {
            $leftKey  = $left->getKey();
            $rightKey = $right->getKey();
            if (self::KEY_ALL === $leftKey) {
                return -1;
            }
            if (self::KEY_FUTURE === $leftKey) {
                return self::KEY_ALL === $right->getKey() ? +1 : -1;
            }
            if (self::KEY_CURRENT === $leftKey) {
                return in_array($rightKey, [self::KEY_ALL, self::KEY_FUTURE], true) ? +1 : -1;
            }
            if (self::KEY_PREVIOUS === $leftKey) {
                return in_array($rightKey, [self::KEY_ALL, self::KEY_FUTURE, self::KEY_CURRENT], true) ? +1 : -1;
            }
            if (self::KEY_PAST === $leftKey) {
                return in_array($rightKey, [self::KEY_ALL, self::KEY_FUTURE, self::KEY_CURRENT, self::KEY_PREVIOUS], true) ? +1 : -1;
            }
            if (self::KEY_FUTURE === $rightKey || self::KEY_PAST === $rightKey) {
                return +1;
            }
            return $this->terms[$right->getKey()]->compare($this->terms[$leftKey]);
        });
    }


    /**
     * Returns sets of options, grouped by option.page, then indexed by option.key.
     * Each Option contains a set of Defaults, indexed by roleType
     *
     * @param null|string $pageFilter If supplied, get options just for this page, otherwise get options for all available pages
     * @return Collection|GenericCollection[]|FilterOption\Model[][]
     */
    public function getOptionDefaults($pageFilter = null)
    {
        $optionsPerKey = $this->getOptions();

        // Get the ones which are available (regardless of whether they exist yet in DB)
        $availableOptions = $this->getFilterOptionMapper()->createCollection();
        foreach ($optionsPerKey as $key => $keyOptions) {
            /** @var FilterOption\Model[] $keyOptions */
            foreach ($keyOptions as $page => $option) {
                if ($option->getAvailable()) {
                    $availableOptions->add($option);
                }
            }
        }

        // Find any options that already exist in the DB
        /** @var FilterOption\Model[]|GenericCollection $existingOptions */
        $existingOptions = $availableOptions->filter(function (FilterOption\Model $option) {
            return null !== $option->getId();
        })->indexBy('id');

        // Load existing defaults for existing options, and group by option
        $defaultsPerExistingOption = $this->getFilterDefaultMapper()
                                          ->findByColumn('filter_option_id', $existingOptions->keys())
                                          ->groupByCallback(function (FilterDefault\Model $default) {
                                              return $default->getFilterOption()->getId();
                                          });

        // Add defaults to the options, and index defaults by roleType
        foreach ($defaultsPerExistingOption as $optionId => $defaults) {
            $existingOptions[$optionId]->setDefaults($defaults->indexBy('roleType'));
        }

        // Group the options by page, then index by key

        /** @var Collection|GenericCollection[]|FilterOption\Model[][] $optionsPerPage */
        $optionsPerPage = $availableOptions->groupBy('page');
        foreach ($optionsPerPage as $page => $options) {
            $options->indexBy('key');
        }

        // Ensure the pages are sorted
        $sortedOptionsPerPage = new Collection();
        foreach ($this->getPages() as $page) {
            if ($pageFilter && $pageFilter !== $page) {
                continue;
            }
            $sortedOptionsPerPage[$page] = $optionsPerPage[$page];
        }

        // Ensure all defaults are available for configuration
        foreach ($sortedOptionsPerPage as $page => $options) {
            /** @var GenericCollection|FilterOption\Model[] $options */
            foreach ($options as $key => $option) {
                $defaults = $option->getDefaults();
                // Does it contain a Default for all relevant roleTypes?
                foreach (FilterOption\Model::getRoleTypesForPage($page) as $roleType) {
                    if (!$defaults->offsetExists($roleType)) {
                        $default = $this->getFilterDefaultMapper()->createModel();
                        $default
                            ->setFilterOption($option)
                            ->setRoleType($roleType)
                            ->setSelected(self::KEY_CURRENT === $key && FilterOption\Model::PAGE_PENDING_ASSESSMENTS === $page);
                        $defaults[$roleType] = $default;
                    }
                }
            }
        }

        return $sortedOptionsPerPage;
    }


    /**
     * Return the set of pages where this filter is displayed
     *
     * @return string[]
     */
    public function getPages()
    {
        return [
            FilterOption\Model::PAGE_PENDING_ASSESSMENTS,
            FilterOption\Model::PAGE_GRADES,
            FilterOption\Model::PAGE_MARK_BOOK,
        ];
    }


    /**
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getAbstractKeys()
    {
        return array_merge(self::$customKeys, $this->terms->keys());
    }




    public function getLabel($key)
    {
        if (Definition::KEY_ALL === $key) {
            return 'All Terms';
        }
        if (Definition::KEY_FUTURE === $key) {
            return 'Future Terms';
        }
        if (Definition::KEY_CURRENT === $key) {
            return $this->currentTerm ? "Current ({$this->currentTerm->getCode()})" : '';
        }
        if (Definition::KEY_PREVIOUS === $key) {
            return $this->previousTerm ? "Previous ({$this->previousTerm->getCode()})" : '';
        }
        if (Definition::KEY_PAST === $key) {
            return 'Past Terms';
        }
        return $this->terms[$key]->getCode();
    }

}
