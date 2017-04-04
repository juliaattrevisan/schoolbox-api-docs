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
use Schoolbox\Mapper;


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
     * @inheritdoc
     */
    protected function getCustomKeys()
    {
        return [
            self::KEY_ALL,
            self::KEY_FUTURE,
            self::KEY_CURRENT,
            self::KEY_PREVIOUS,
            self::KEY_PAST,
        ];
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    protected function getTemplateFile()
    {
        return 'term.twig';
    }



    /**
     * If the admin has not configured this option, should it be available in filter panels?
     *
     * @param string $customAbstractKey
     * @param string $page
     * @return bool
     */
    protected function getInitialAvailability($customAbstractKey, $page)
    {
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
        return $isAvailable;
    }


    /**
     * @param GenericCollection|FilterOption\Model[] $pageOptions
     * @param string $page
     * @return GenericCollection|FilterOption\Model[]
     */
    protected function insertDynamicOptions(GenericCollection $pageOptions, $page)
    {
        // Ensure it contains all Term instances
        foreach ($this->terms as $term) {
            if (!$pageOptions->offsetExists($term->getId())) {
                $option = $this->getFilterOptionMapper()->createModel();
                $key = (string)$term->getId();
                $option
                    ->setFilter($this->getFilter())
                    ->setKey($key)
                    ->setPage($page)
                    ->setAvailable($this->getInitialAvailability($key, $page));
                $pageOptions->setOffset($option->getKey(), $option);
            }
        }
        return $pageOptions;
    }

    /**
     * @param GenericCollection|FilterOption\Model[] $pageOptions
     * @return GenericCollection|FilterOption\Model[]
     */
    protected function unsetInvalidOptions(GenericCollection $pageOptions)
    {
        // Discard any options that refer to Terms that no longer exist (eg if deleted)
        foreach ($pageOptions as $filterOption) {
            // Does the filter refer to a specific Term instance?
            $isForSpecificTerm = !in_array($filterOption->getKey(), $this->getCustomKeys(), true);
            if ($isForSpecificTerm && !$this->terms->offsetExists($filterOption->getKey())) {
                $pageOptions->offsetUnset($filterOption->getKey());
            }
        }
        return $pageOptions;
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
     * @inheritdoc
     * @throws Mapper\Exception
     * @todo move common logic into abstract base
     */
    public function getOptionDefaults($pageFilter = null, $roleTypeFilter = null)
    {
        $optionsPerKey = $this->getOptions($pageFilter);

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
        $columnList = ['filter_option_id' => $existingOptions->keys()];
        if($roleTypeFilter){
            $columnList['role_type'] = $roleTypeFilter;
        }
        $existingDefaults = $this->getFilterDefaultMapper()->findByColumnList($columnList);
        $defaultsPerExistingOption = $existingDefaults->groupByCallback(function (FilterDefault\Model $default) {
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
                    // Are we filtering results ?
                    if($roleTypeFilter && $roleTypeFilter !== $roleType){
                        continue;
                    }
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
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getAbstractKeys()
    {
        return array_merge($this->getCustomKeys(), $this->terms->keys());
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

    /* Getters
     */

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

}
